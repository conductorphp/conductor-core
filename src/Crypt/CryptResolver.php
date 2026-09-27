<?php

declare(strict_types=1);

namespace ConductorCore\Crypt;

/**
 * Picks the implementation by the value's envelope: `enc:` → sodium, `ENC[…]` → defuse, anything
 * else is not encrypted and passes through. New values are always written by the sodium side.
 * Two `CryptInterface`s rather than two concrete classes so either can be substituted (CTAP-1968).
 */
final class CryptResolver implements CryptInterface
{
    public function __construct(
        private readonly CryptInterface $sodium,
        private readonly CryptInterface $defuse,
    ) {
    }

    public function encrypt(string $plaintext): string
    {
        return $this->sodium->encrypt($plaintext);
    }

    public function decrypt(string $value): string
    {
        if ($this->sodium->isEncrypted($value)) {
            return $this->sodium->decrypt($value);
        }

        if ($this->defuse->isEncrypted($value)) {
            return $this->defuse->decrypt($value);
        }

        return $value;
    }

    public function isEncrypted(string $value): bool
    {
        return $this->sodium->isEncrypted($value) || $this->defuse->isEncrypted($value);
    }
}
