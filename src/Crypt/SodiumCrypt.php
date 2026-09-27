<?php

declare(strict_types=1);

namespace ConductorCore\Crypt;

use ConductorCore\Config\EnvironmentConfig;
use ConductorCore\Exception\CryptException;
use Rmg\Lib\Crypt\Api\EncryptionServiceInterface;
use Rmg\Lib\Crypt\Api\Exception\ExceptionInterface as CryptLibException;
use Rmg\Lib\Crypt\Sodium\Exception\EncryptionKeyNotFoundException;
use Throwable;

use function sprintf;

/**
 * The `enc:v1:<keyId>:<base64>` envelope, read and written by `rmg/lib-crypt-sodium`: the same
 * library the middleware encrypts its secret attributes with, so one cipher (libsodium secretbox),
 * one envelope, one key format (base64 of 32 bytes), one key id (a SHA-256 fingerprint) and one
 * rotation model (a current key plus retired keys) serve the whole product, and a value
 * `crypt:encrypt` prints works in conductor YAML and in the middleware alike (CTAP-1967, CTAP-1968,
 * CTAP-1970).
 *
 * This class only adapts the library's contract to {@see CryptInterface}: the encryption service
 * is injected already built with conductor's key variables ({@see CryptResolverFactory}), so every
 * library message names `CONDUCTOR_CRYPT_KEY` / `CONDUCTOR_CRYPT_KEYS_PREVIOUS`. The one message
 * rewritten here is the unknown-key one, whose library text is about the middleware's snapshot
 * restores; conductor says which two variables could carry the key.
 *
 * `encrypt()` is idempotent (an envelope of any version is returned unchanged); `decrypt()` passes a
 * non-envelope through unchanged and never returns a mangled value.
 */
final class SodiumCrypt implements CryptInterface
{
    public function __construct(private readonly EncryptionServiceInterface $encryptionService)
    {
    }

    public function encrypt(string $plaintext): string
    {
        try {
            return $this->encryptionService->encrypt($plaintext);
        } catch (CryptLibException $e) {
            throw new CryptException($e->getMessage(), 0, $e);
        }
    }

    public function decrypt(string $value): string
    {
        try {
            return $this->encryptionService->decrypt($value);
        } catch (EncryptionKeyNotFoundException $e) {
            throw new CryptException(sprintf(
                'no key with id %s is carried by %s or %s',
                $e->getKeyId(),
                EnvironmentConfig::CRYPT_KEY_VARIABLE,
                EnvironmentConfig::CRYPT_KEYS_PREVIOUS_VARIABLE,
            ), 0, $e);
        } catch (CryptLibException $e) {
            throw new CryptException($e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            // ext-sodium throws its own SodiumException for a payload too short to hold a nonce and tag.
            throw new CryptException(sprintf('Malformed encryption envelope: %s', $e->getMessage()), 0, $e);
        }
    }

    public function isEncrypted(string $value): bool
    {
        return $this->encryptionService->isEncrypted($value);
    }
}
