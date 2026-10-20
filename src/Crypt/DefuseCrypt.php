<?php

declare(strict_types=1);

namespace ConductorCore\Crypt;

use ConductorCore\Config\EnvironmentConfig;
use ConductorCore\Exception\CryptException;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Throwable;

use function array_filter;
use function array_values;
use function count;
use function is_string;
use function preg_match;
use function sprintf;
use function str_starts_with;

/**
 * The `ENC[defuse/php-encryption,<ciphertext>]` envelope every value encrypted before core 6.1
 * carries. Read-only: `crypt:encrypt` writes the sodium envelope ({@see SodiumCrypt}), and this
 * reader exists for one release so a configuration converts value by value rather than in one
 * deploy. Removed in the next major (CTAP-1968).
 *
 * Reads with the defuse key, recognizable by its `def000` prefix (defuse's ASCII-safe key format),
 * from `CONDUCTOR_CRYPT_KEY` while that variable still holds it, or from `CONDUCTOR_CRYPT_KEYS_PREVIOUS`
 * once a sodium key has taken its place ({@see CryptResolverFactory} sorts them). A sodium key is
 * never tried against a defuse value: with no defuse key anywhere the value fails by name, because
 * the fix is to re-encrypt it, not to find a key.
 *
 * Several defuse keys may be configured, such as two apps' retired keys in one shared
 * `CONDUCTOR_CRYPT_KEYS_PREVIOUS`. A defuse value names no key, so each is tried in order until one
 * passes the integrity check (CTAP-2786).
 */
final class DefuseCrypt implements CryptInterface
{
    public const ENCRYPTION_TYPE = 'defuse/php-encryption';

    private const ENVELOPE = '%^ENC\[([^,]+),(.*)\]$%';

    /** Defuse's ASCII-safe key format starts with this; a sodium key is base64 of 32 bytes. */
    public const KEY_PREFIX = 'def000';

    /** @var list<string> */
    private readonly array $keys;

    /**
     * @param string|list<string>|null $keys The defuse keys, in the order to try them; a key that is
     *                                       not a defuse key is ignored. Null or none: no defuse key.
     */
    public function __construct(string|array|null $keys)
    {
        $this->keys = array_values(array_filter(
            (array) $keys,
            static fn (mixed $key): bool => is_string($key) && str_starts_with($key, self::KEY_PREFIX),
        ));
    }

    public function encrypt(string $plaintext): string
    {
        throw new CryptException(sprintf(
            'Writing %s values is retired: crypt:encrypt writes the enc:v1:<keyId>:… envelope under a'
            . ' sodium %s. Existing ENC[…] values stay readable until the next conductor/core major.',
            self::ENCRYPTION_TYPE,
            EnvironmentConfig::CRYPT_KEY_VARIABLE,
        ));
    }

    public function decrypt(string $value): string
    {
        if (preg_match(self::ENVELOPE, $value, $matches) !== 1) {
            return $value;
        }

        [, $type, $ciphertext] = $matches;

        if ($type !== self::ENCRYPTION_TYPE) {
            throw new CryptException(sprintf(
                'Unsupported encryption type "%s". The only ENC[…] type conductor reads is "%s".',
                $type,
                self::ENCRYPTION_TYPE,
            ));
        }

        if ($this->keys === []) {
            throw new CryptException(sprintf(
                'this is a %s value (ENC[…]) but neither %s nor %s holds a defuse (%s…) key. List the'
                . ' key it was encrypted under in %s and re-encrypt the value with crypt:encrypt, which'
                . ' writes the enc:v1:<keyId>:… envelope; then drop the defuse key.',
                self::ENCRYPTION_TYPE,
                EnvironmentConfig::CRYPT_KEY_VARIABLE,
                EnvironmentConfig::CRYPT_KEYS_PREVIOUS_VARIABLE,
                self::KEY_PREFIX,
                EnvironmentConfig::CRYPT_KEYS_PREVIOUS_VARIABLE,
            ));
        }

        $failure = null;
        foreach ($this->keys as $key) {
            try {
                return Crypto::decrypt($ciphertext, Key::loadFromAsciiSafeString($key));
            } catch (Throwable $e) {
                $failure = $e;
            }
        }

        throw new CryptException(sprintf(
            '%s value could not be decrypted with %s: %s',
            self::ENCRYPTION_TYPE,
            count($this->keys) === 1 ? 'the defuse key' : sprintf('any of the %d defuse keys', count($this->keys)),
            $failure?->getMessage() ?? 'no key',
        ), 0, $failure);
    }

    public function isEncrypted(string $value): bool
    {
        return preg_match(self::ENVELOPE, $value) === 1;
    }
}
