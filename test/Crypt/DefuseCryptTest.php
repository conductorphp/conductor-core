<?php

declare(strict_types=1);

namespace ConductorCoreTest\Crypt;

use ConductorCore\Crypt\DefuseCrypt;
use ConductorCore\Exception\CryptException;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CTAP-1968. The `ENC[defuse/php-encryption,…]` reader kept for one release: reads with a defuse
 * key, refuses a sodium key by name, never writes.
 */
final class DefuseCryptTest extends TestCase
{
    private const DEFUSE_KEY = 'def00000de54d8d8cb4804e9748968de2edc1b130ba31df5c5fa0eb662bfe4c6d2caaec614eb5de3628bd3220331f21b3e3b6ccb1332a7691d081b6317c721657ded544f';
    private const SODIUM_KEY = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';

    private function legacyValue(string $plaintext): string
    {
        return 'ENC[defuse/php-encryption,' . Crypto::encrypt($plaintext, Key::loadFromAsciiSafeString(self::DEFUSE_KEY)) . ']';
    }

    #[Test]
    public function aLegacyValueStillDecryptsWithTheDefuseKey(): void
    {
        $crypt = new DefuseCrypt(self::DEFUSE_KEY);
        $value = $this->legacyValue('Encrypt me!');

        $this->assertTrue($crypt->isEncrypted($value));
        $this->assertSame('Encrypt me!', $crypt->decrypt($value));
        $this->assertSame('plain', $crypt->decrypt('plain'));
        $this->assertFalse($crypt->isEncrypted('enc:v1:abc:def'));
    }

    #[Test]
    public function aSodiumKeyIsNotTriedAgainstALegacyValue(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('neither CONDUCTOR_CRYPT_KEY nor CONDUCTOR_CRYPT_KEYS_PREVIOUS holds a defuse (def000…) key');

        (new DefuseCrypt(self::SODIUM_KEY))->decrypt($this->legacyValue('x'));
    }

    #[Test]
    public function noKeyAtAllFailsTheSameWay(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('neither CONDUCTOR_CRYPT_KEY nor CONDUCTOR_CRYPT_KEYS_PREVIOUS holds a defuse (def000…) key');

        (new DefuseCrypt(null))->decrypt($this->legacyValue('x'));
    }

    #[Test]
    public function theWrongDefuseKeyFailsAsADecryptionError(): void
    {
        $otherKey = Key::createNewRandomKey()->saveToAsciiSafeString();

        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('defuse/php-encryption value could not be decrypted');

        (new DefuseCrypt($otherKey))->decrypt($this->legacyValue('x'));
    }

    #[Test]
    public function anUnknownEncTypeIsRefused(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('Unsupported encryption type "rot13"');

        (new DefuseCrypt(self::DEFUSE_KEY))->decrypt('ENC[rot13,abc]');
    }

    #[Test]
    public function writingIsRetired(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('Writing defuse/php-encryption values is retired');

        (new DefuseCrypt(self::DEFUSE_KEY))->encrypt('x');
    }
}
