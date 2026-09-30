<?php

declare(strict_types=1);

namespace ConductorCore\Crypt;

use ConductorCore\Config\EnvironmentConfig;
use ConductorCore\Exception\CryptException;

use function base64_decode;
use function base64_encode;
use function count;
use function explode;
use function random_bytes;
use function sodium_crypto_secretbox;
use function sodium_crypto_secretbox_keygen;
use function sodium_crypto_secretbox_open;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

use const SODIUM_CRYPTO_SECRETBOX_MACBYTES;
use const SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

/**
 * The `enc:v1:<keyId>:<base64(nonce . secretbox)>` envelope: libsodium secretbox under a 32-byte
 * key, a fresh random nonce per value. It is the envelope the clicktap middleware writes with
 * `rmg/lib-crypt-sodium`, so a value `crypt:encrypt` prints works in conductor YAML and in the
 * middleware alike (CTAP-1967, CTAP-1968).
 *
 * Conductor carries its own copy rather than requiring that library: `conductor/*` is public and
 * must install without RMG's private registry (CTAP-2082). The envelope is the contract; the
 * golden value in `SodiumCryptTest` fails the moment the two implementations drift apart.
 *
 * `encrypt()` is idempotent (an envelope of any version is returned unchanged); `decrypt()` passes a
 * non-envelope through unchanged and never returns a mangled value.
 */
final class SodiumCrypt implements CryptInterface
{
    private const PREFIX  = 'enc:';
    private const VERSION = 'v1';

    public function __construct(private readonly SodiumKeyRing $keys)
    {
    }

    /** A new key for `CONDUCTOR_CRYPT_KEY`: base64 of 32 random bytes. */
    public static function generateKey(): string
    {
        return base64_encode(sodium_crypto_secretbox_keygen());
    }

    public function encrypt(string $plaintext): string
    {
        if ($this->isEncrypted($plaintext)) {
            return $plaintext;
        }

        ['id' => $keyId, 'material' => $material] = $this->keys->current();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX . self::VERSION . ':' . $keyId . ':'
            . base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $material));
    }

    public function decrypt(string $value): string
    {
        if (! $this->isEncrypted($value)) {
            return $value;
        }

        [, $version, $keyId, $encoded] = explode(':', $value, 4);

        $payload = base64_decode($encoded, true);
        if ($payload === false) {
            throw new CryptException('Malformed encryption envelope: payload is not valid base64.');
        }

        if ($version !== self::VERSION) {
            // Never passed through: nothing here can recover a value written by a newer cipher.
            throw new CryptException(sprintf(
                'This value was encrypted with envelope version "%s"; this conductor reads only "%s".'
                . ' Upgrade conductor/core or re-encrypt the value.',
                $version,
                self::VERSION,
            ));
        }

        $material = $this->keys->find($keyId);
        if ($material === null) {
            throw new CryptException(sprintf(
                'no key with id %s is carried by %s or %s',
                $keyId,
                EnvironmentConfig::CRYPT_KEY_VARIABLE,
                EnvironmentConfig::CRYPT_KEYS_PREVIOUS_VARIABLE,
            ));
        }

        if (strlen($payload) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new CryptException('Malformed encryption envelope: payload is too short to hold a nonce and tag.');
        }

        $plaintext = sodium_crypto_secretbox_open(
            substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $material,
        );
        if ($plaintext === false) {
            throw new CryptException(sprintf(
                'Failed to decrypt a value under key "%s": authentication failed. The ciphertext is'
                . ' corrupted or was modified after encryption.',
                $keyId,
            ));
        }

        return $plaintext;
    }

    public function isEncrypted(string $value): bool
    {
        if (! str_starts_with($value, self::PREFIX)) {
            return false;
        }

        $parts = explode(':', $value, 4);

        return count($parts) === 4 && $parts[1] !== '' && $parts[2] !== '' && $parts[3] !== '';
    }
}
