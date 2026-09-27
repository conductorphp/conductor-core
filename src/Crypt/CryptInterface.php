<?php

declare(strict_types=1);

namespace ConductorCore\Crypt;

use ConductorCore\Exception\CryptException;

/**
 * Encrypts and decrypts one configuration value (CTAP-1968).
 *
 * The container hands out this interface, never a concrete class. The implementation it binds is
 * {@see CryptResolver}, which recognizes each of the two envelopes conductor can read and delegates
 * to the implementation that owns it:
 *
 * - `enc:v1:<keyId>:<base64>` — {@see SodiumCrypt}. The envelope `rmg/mezzio-module-crypt-sodium`
 *   writes, so one string works in conductor YAML and in the middleware. What `crypt:encrypt` prints.
 * - `ENC[defuse/php-encryption,<ciphertext>]` — {@see DefuseCrypt}, read-only, what every value
 *   encrypted before core 6.1 looks like. Removed in the next major.
 *
 * A value that is neither passes through {@see self::decrypt()} unchanged; it is not encrypted, and
 * the caller (the config walker, the CLI) decides whether that is fine. A value that IS an envelope
 * and cannot be opened throws; it is never returned as-is and never turned into an empty string.
 */
interface CryptInterface
{
    /**
     * @throws CryptException No key to encrypt under, or the value is a retired envelope that can
     *         no longer be written.
     */
    public function encrypt(string $plaintext): string;

    /**
     * Returns `$value` unchanged when it is not an envelope this implementation recognizes.
     *
     * @throws CryptException The value is an envelope but cannot be opened: the key it names is
     *         not configured, the envelope is malformed, or authentication failed.
     */
    public function decrypt(string $value): string;

    /** Structural check only: the value carries an envelope, whether or not a key for it is configured. */
    public function isEncrypted(string $value): bool;
}
