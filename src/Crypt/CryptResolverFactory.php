<?php

declare(strict_types=1);

namespace ConductorCore\Crypt;

use ConductorCore\Config\EnvironmentConfig;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Rmg\Lib\Crypt\Sodium\Cipher\SodiumSecretboxCipher;
use Rmg\Lib\Crypt\Sodium\CipherRegistry;
use Rmg\Lib\Crypt\Sodium\EncryptionKeyProvider;
use Rmg\Lib\Crypt\Sodium\EncryptionService;
use Rmg\Lib\Crypt\Sodium\Envelope;

use function array_filter;
use function array_map;
use function array_values;
use function implode;
use function is_string;
use function preg_split;
use function str_starts_with;
use function trim;

/**
 * Builds the {@see CryptResolver} from the two key values, wherever they come from: the merged
 * config (`crypt_key` / `crypt_keys_previous`, for the container) or the {@see EnvironmentConfig}
 * a project's `config/config.php` has already resolved (for the config post-processor, which runs
 * before there is a container).
 *
 * Each key goes to the reader that can use it, told apart by shape: a defuse key is ASCII-safe
 * with a `def000` prefix, a sodium key is base64 of 32 bytes. So a configuration in transition
 * decrypts both envelopes from one pair of variables:
 *
 *     CONDUCTOR_CRYPT_KEY=<sodium key>              # new values, crypt:encrypt writes under it
 *     CONDUCTOR_CRYPT_KEYS_PREVIOUS=<old defuse key> # the ENC[…] values not yet re-encrypted
 *
 * and retiring the defuse key is the same procedure as any rotation: re-encrypt, then drop it. A
 * `CONDUCTOR_CRYPT_KEY` that still holds the defuse key serves the defuse reader only; the sodium
 * side then has no current key and says so if asked to write.
 */
final class CryptResolverFactory implements FactoryInterface
{
    /** @param string $requestedName */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): CryptResolver
    {
        $config = $container->get('config');

        return $this->fromKeys(
            is_string($config['crypt_key'] ?? null) ? $config['crypt_key'] : null,
            is_string($config['crypt_keys_previous'] ?? null) ? $config['crypt_keys_previous'] : null,
        );
    }

    /**
     * Null when no key of either kind is configured: `ENC[…]` and `enc:` values are then left as
     * written, so a conductor with no key still boots (the deploy plan asserts the key; CTAP-1963).
     */
    public function fromEnvironmentConfig(EnvironmentConfig $environmentConfig): ?CryptResolver
    {
        if (! $environmentConfig->hasCryptKeys()) {
            return null;
        }

        return $this->fromKeys($environmentConfig->cryptKey, $environmentConfig->cryptKeysPrevious);
    }

    public function fromKeys(?string $currentKey, ?string $previousKeys): CryptResolver
    {
        $currentKey = $currentKey === null ? null : trim($currentKey);
        $previous   = array_values(array_filter(
            array_map(trim(...), preg_split('/[\r\n,]+/', $previousKeys ?? '') ?: []),
            static fn (string $key): bool => $key !== '',
        ));

        $defuseKey = null;
        if ($currentKey !== null && self::isDefuseKey($currentKey)) {
            $defuseKey  = $currentKey;
            $currentKey = null;
        }

        $sodiumPrevious = [];
        foreach ($previous as $key) {
            if (self::isDefuseKey($key)) {
                $defuseKey ??= $key;
            } else {
                $sodiumPrevious[] = $key;
            }
        }

        // The library's provider, built from conductor's VALUES and told conductor's variable NAMES:
        // an empty string (not null) for an unset key, so it never falls back to reading the
        // application's ENCRYPTION_KEY from the environment, and every message it produces names
        // CONDUCTOR_CRYPT_KEY / CONDUCTOR_CRYPT_KEYS_PREVIOUS.
        $keyProvider = new EncryptionKeyProvider(
            $currentKey ?? '',
            implode(',', $sodiumPrevious),
            EnvironmentConfig::CRYPT_KEY_VARIABLE,
            EnvironmentConfig::CRYPT_KEYS_PREVIOUS_VARIABLE,
        );

        return new CryptResolver(
            new SodiumCrypt(new EncryptionService(
                $keyProvider,
                new Envelope(),
                new CipherRegistry(new SodiumSecretboxCipher()),
            )),
            new DefuseCrypt($defuseKey),
        );
    }

    private static function isDefuseKey(string $key): bool
    {
        return str_starts_with($key, DefuseCrypt::KEY_PREFIX);
    }
}
