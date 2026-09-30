<?php

declare(strict_types=1);

namespace ConductorCoreTest\Crypt;

use ConductorCore\Crypt\CryptResolverFactory;
use ConductorCore\Crypt\SodiumCrypt;
use ConductorCore\Crypt\CryptInterface;
use ConductorCore\Exception\CryptException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_decode;
use function base64_encode;
use function explode;
use function str_repeat;
use function str_starts_with;
use function strlen;

/**
 * CTAP-1968 / CTAP-2082. The `enc:v1:<keyId>:<base64>` envelope, conductor's own copy of the one the
 * middleware writes with rmg/lib-crypt-sodium, wired with conductor's key variables. The fixture
 * below was produced by the middleware; if it stops decrypting, the two copies have drifted apart.
 */
final class SodiumCryptTest extends TestCase
{
    /** bytes 0..31 */
    private const KEY    = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';
    private const KEY_ID = '630dcd2966c43366';

    /**
     * Produced by `Rmg\Crypt\Sodium\EncryptionService` (clicktap middleware, 2026-09-26) under
     * self::KEY as ENCRYPTION_KEY, plaintext "Encrypt me!". If this stops decrypting, the two
     * implementations have drifted apart.
     */
    private const MODULE_CIPHERTEXT = 'enc:v1:630dcd2966c43366:jMXfEsjW2rV1az2mT9WTo+AhUlLjQoS6NNAmtSXuNxqKW2vZrfHRC/MwcwaxX66JX248';

    private function crypt(?string $current = self::KEY, ?string $previous = null): CryptInterface
    {
        return (new CryptResolverFactory())->fromKeys($current, $previous);
    }

    #[Test]
    public function aValueTheMiddlewareModuleEncryptedDecrypts(): void
    {
        $this->assertSame('Encrypt me!', $this->crypt()->decrypt(self::MODULE_CIPHERTEXT));
    }

    #[Test]
    public function roundTripUsesTheModulesEnvelopeShape(): void
    {
        $crypt      = $this->crypt();
        $ciphertext = $crypt->encrypt('Encrypt me!');

        $this->assertTrue(str_starts_with($ciphertext, 'enc:v1:' . self::KEY_ID . ':'));
        [, , , $payload] = explode(':', $ciphertext, 4);
        $this->assertSame(24 + 16 + strlen('Encrypt me!'), strlen((string) base64_decode($payload, true)), 'nonce . ciphertext-with-tag');
        $this->assertSame('Encrypt me!', $crypt->decrypt($ciphertext));
        $this->assertNotSame($ciphertext, $crypt->encrypt('Encrypt me!'), 'a fresh nonce per value');
    }

    #[Test]
    public function encryptIsIdempotentAndDecryptPassesPlaintextThrough(): void
    {
        $crypt = $this->crypt();

        $this->assertSame(self::MODULE_CIPHERTEXT, $crypt->encrypt(self::MODULE_CIPHERTEXT));
        $this->assertSame('enc:v9:x:y', $crypt->encrypt('enc:v9:x:y'), 'any envelope version, known or not');
        $this->assertSame('plain', $crypt->decrypt('plain'));
        $this->assertTrue($crypt->isEncrypted(self::MODULE_CIPHERTEXT));
        $this->assertFalse($crypt->isEncrypted('plain'));
    }

    #[Test]
    public function aValueUnderARetiredKeyDecryptsWhileThatKeyIsListedAsPrevious(): void
    {
        $newKey = SodiumCrypt::generateKey();

        $this->assertSame('Encrypt me!', $this->crypt($newKey, self::KEY)->decrypt(self::MODULE_CIPHERTEXT));
        $this->assertSame('Encrypt me!', $this->crypt(null, self::KEY)->decrypt(self::MODULE_CIPHERTEXT), 'no current key, only retired ones');
    }

    #[Test]
    public function aValueUnderAKeyListedNowhereFailsNamingTheKeyIdAndBothVariables(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('no key with id 630dcd2966c43366 is carried by CONDUCTOR_CRYPT_KEY or CONDUCTOR_CRYPT_KEYS_PREVIOUS');

        $this->crypt(SodiumCrypt::generateKey())->decrypt(self::MODULE_CIPHERTEXT);
    }

    #[Test]
    public function encryptingWithoutAKeyFailsNamingTheVariable(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('No encryption key configured. Set CONDUCTOR_CRYPT_KEY');

        $this->crypt(null)->encrypt('Encrypt me!');
    }

    #[Test]
    public function aMalformedEnvelopeFailsSayingSo(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('payload is not valid base64');

        $this->crypt()->decrypt('enc:v1:' . self::KEY_ID . ':not*base64');
    }

    /** ext-sodium refuses a payload shorter than nonce + tag with its own exception; it surfaces as ours. */
    #[Test]
    public function aTruncatedPayloadFailsAsAMalformedEnvelope(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('Malformed encryption envelope');

        $this->crypt()->decrypt('enc:v1:' . self::KEY_ID . ':' . base64_encode('short'));
    }

    /** A malformed CONDUCTOR_CRYPT_KEY is reported under conductor's variable name, not the application's. */
    #[Test]
    public function aMalformedKeyIsReportedUnderConductorsVariableName(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('CONDUCTOR_CRYPT_KEY decodes to 3 bytes; expected exactly 32');

        $this->crypt(base64_encode('abc'))->encrypt('x');
    }

    #[Test]
    public function aMalformedRetiredKeyIsReportedByPositionUnderConductorsVariableName(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('CONDUCTOR_CRYPT_KEYS_PREVIOUS entry #2 is not valid base64');

        $this->crypt(self::KEY, base64_encode(str_repeat("\x01", 32)) . ',not*base64')->decrypt('enc:v1:0000000000000000:' . base64_encode(str_repeat("\x00", 60)));
    }

    #[Test]
    public function tamperedCiphertextFailsAuthenticationNamingTheKeyId(): void
    {
        $tampered = 'enc:v1:' . self::KEY_ID . ':' . base64_encode(str_repeat("\x00", 24 + 16 + 11));

        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('Failed to decrypt a value under key "630dcd2966c43366": authentication failed');

        $this->crypt()->decrypt($tampered);
    }

    #[Test]
    public function anUnknownEnvelopeVersionIsRefusedNotPassedThrough(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('envelope version "v2"');

        $this->crypt()->decrypt('enc:v2:' . self::KEY_ID . ':' . base64_encode(str_repeat("\x00", 60)));
    }

    #[Test]
    public function theGeneratedKeyIsBase64Of32BytesAndEncrypts(): void
    {
        $key = SodiumCrypt::generateKey();

        $this->assertSame(32, strlen((string) base64_decode($key, true)));
        $this->assertTrue($this->crypt($key)->isEncrypted($this->crypt($key)->encrypt('x')));
    }
}
