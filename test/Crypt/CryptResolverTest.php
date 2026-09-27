<?php

declare(strict_types=1);

namespace ConductorCoreTest\Crypt;

use ConductorCore\Config\EnvironmentConfig;
use ConductorCore\Crypt\CryptInterface;
use ConductorCore\Crypt\CryptResolver;
use ConductorCore\Crypt\CryptResolverFactory;
use ConductorCore\Exception\CryptException;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function str_starts_with;

/**
 * CTAP-1968. One `CryptInterface` for the container and the config walker, dispatching on the
 * envelope, so a configuration mixing `enc:v1:` and `ENC[…]` values decrypts each with its own key.
 */
final class CryptResolverTest extends TestCase
{
    private const DEFUSE_KEY = 'def00000de54d8d8cb4804e9748968de2edc1b130ba31df5c5fa0eb662bfe4c6d2caaec614eb5de3628bd3220331f21b3e3b6ccb1332a7691d081b6317c721657ded544f';
    private const SODIUM_KEY = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';
    private const SODIUM_VALUE = 'enc:v1:630dcd2966c43366:jMXfEsjW2rV1az2mT9WTo+AhUlLjQoS6NNAmtSXuNxqKW2vZrfHRC/MwcwaxX66JX248';

    private function defuseValue(string $plaintext): string
    {
        return 'ENC[defuse/php-encryption,' . Crypto::encrypt($plaintext, Key::loadFromAsciiSafeString(self::DEFUSE_KEY)) . ']';
    }

    /** The transition state: a sodium key is current, the retired defuse key rides in CONDUCTOR_CRYPT_KEYS_PREVIOUS. */
    #[Test]
    public function mixedEnvelopesEachDecryptWithTheirOwnKey(): void
    {
        $crypt = (new CryptResolverFactory())->fromKeys(self::SODIUM_KEY, 'AQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQE=, ' . self::DEFUSE_KEY);

        $this->assertSame('Encrypt me!', $crypt->decrypt(self::SODIUM_VALUE));
        $this->assertSame('legacy', $crypt->decrypt($this->defuseValue('legacy')));
        $this->assertSame('plain', $crypt->decrypt('plain'));
        $this->assertTrue($crypt->isEncrypted(self::SODIUM_VALUE));
        $this->assertTrue($crypt->isEncrypted($this->defuseValue('x')));
        $this->assertFalse($crypt->isEncrypted('plain'));
        $this->assertTrue(str_starts_with($crypt->encrypt('new'), 'enc:v1:'), 'new values are always sodium');
    }

    /** `CONDUCTOR_CRYPT_KEY` holding the old defuse key: legacy values read, and writing says what to do. */
    #[Test]
    public function aDefuseKeyInTheVariableServesOnlyTheDefuseSide(): void
    {
        $crypt = (new CryptResolverFactory())->fromKeys(self::DEFUSE_KEY, null);

        $this->assertSame('legacy', $crypt->decrypt($this->defuseValue('legacy')));

        try {
            $crypt->encrypt('new');
            $this->fail('a defuse key cannot write');
        } catch (CryptException $e) {
            $this->assertStringContainsString('Set CONDUCTOR_CRYPT_KEY', $e->getMessage());
        }

        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('no key with id 630dcd2966c43366');
        $crypt->decrypt(self::SODIUM_VALUE);
    }

    /** Sodium keys only, no defuse key anywhere: a leftover legacy value fails naming the fix. */
    #[Test]
    public function aSodiumKeyInTheVariableRefusesALegacyValueByName(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('neither CONDUCTOR_CRYPT_KEY nor CONDUCTOR_CRYPT_KEYS_PREVIOUS holds a defuse (def000…) key');

        (new CryptResolverFactory())->fromKeys(self::SODIUM_KEY, null)->decrypt($this->defuseValue('x'));
    }

    #[Test]
    public function theFactoryReadsTheMergedConfigForTheContainer(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            ['config', ['crypt_key' => null, 'crypt_keys_previous' => self::SODIUM_KEY]],
        ]);

        $crypt = (new CryptResolverFactory())($container, CryptInterface::class);

        $this->assertInstanceOf(CryptResolver::class, $crypt);
        $this->assertSame('Encrypt me!', $crypt->decrypt(self::SODIUM_VALUE), 'retired keys alone still read');
    }

    #[Test]
    public function theFactoryReturnsNullForAKeylessEnvironmentSoValuesPassThrough(): void
    {
        $factory = new CryptResolverFactory();

        $this->assertNull($factory->fromEnvironmentConfig(new EnvironmentConfig('local')));
        $this->assertInstanceOf(CryptResolver::class, $factory->fromEnvironmentConfig(new EnvironmentConfig('local', self::SODIUM_KEY)));
        $this->assertInstanceOf(CryptResolver::class, $factory->fromEnvironmentConfig(new EnvironmentConfig('local', null, self::SODIUM_KEY)));
    }
}
