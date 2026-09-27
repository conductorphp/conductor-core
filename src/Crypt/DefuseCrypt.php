<?php

declare(strict_types=1);

namespace ConductorCore\Crypt;

use ConductorCore\Config\EnvironmentConfig;
use ConductorCore\Exception\CryptException;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Throwable;

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
 */
final class DefuseCrypt implements CryptInterface
{
    public const ENCRYPTION_TYPE = 'defuse/php-encryption';

    private const ENVELOPE = '%^ENC\[([^,]+),(.*)\]$%';

    /** Defuse's ASCII-safe key format starts with this; a sodium key is base64 of 32 bytes. */
    public const KEY_PREFIX = 'def000';

    /** @param string|null $key The defuse key, or null when no configured key is one. */
    public function __construct(private readonly ?string $key)
    {
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

        if (! $this->hasDefuseKey()) {
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

        try {
            return Crypto::decrypt($ciphertext, Key::loadFromAsciiSafeString((string) $this->key));
        } catch (Throwable $e) {
            throw new CryptException(sprintf('%s value could not be decrypted: %s', self::ENCRYPTION_TYPE, $e->getMessage()), 0, $e);
        }
    }

    public function isEncrypted(string $value): bool
    {
        return preg_match(self::ENVELOPE, $value) === 1;
    }

    private function hasDefuseKey(): bool
    {
        return $this->key !== null && str_starts_with($this->key, self::KEY_PREFIX);
    }
}
