<?php

declare(strict_types=1);

namespace ConductorCoreTest\Console\Crypt;

use ConductorCore\Console\Crypt\DecryptCommand;
use ConductorCore\Console\Crypt\DecryptCommandFactory;
use ConductorCore\Console\Crypt\EncryptCommand;
use ConductorCore\Console\Crypt\EncryptCommandFactory;
use ConductorCore\Console\Crypt\GenerateKeyCommand;
use ConductorCore\Crypt\CryptInterface;
use ConductorCore\Crypt\CryptResolverFactory;
use ConductorCore\Exception\CryptException;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Tester\CommandTester;

use function base64_decode;
use function str_starts_with;
use function strlen;
use function trim;

/**
 * CTAP-1968 (and CTAP-1730: a conductor with no key must still build every command, because Symfony
 * instantiates them all to render the list; the refusal belongs at use, with the fix in the message).
 */
final class CryptCommandsTest extends TestCase
{
    private const SODIUM_KEY = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';
    private const DEFUSE_KEY = 'def00000de54d8d8cb4804e9748968de2edc1b130ba31df5c5fa0eb662bfe4c6d2caaec614eb5de3628bd3220331f21b3e3b6ccb1332a7691d081b6317c721657ded544f';

    private function crypt(?string $key): CryptInterface
    {
        return (new CryptResolverFactory())->fromKeys($key, null);
    }

    #[Test]
    public function theFactoriesBuildBothCommandsFromAContainerWithNoKey(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            [CryptInterface::class, $this->crypt(null)],
        ]);

        $this->assertInstanceOf(DecryptCommand::class, (new DecryptCommandFactory())($container, DecryptCommand::class));
        $this->assertInstanceOf(EncryptCommand::class, (new EncryptCommandFactory())($container, EncryptCommand::class));
    }

    #[Test]
    public function encryptWithoutAKeyFailsNamingTheVariable(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('Set CONDUCTOR_CRYPT_KEY');

        (new CommandTester(new EncryptCommand($this->crypt(null))))->execute(['message' => 'Encrypt me!']);
    }

    #[Test]
    public function encryptPrintsTheSodiumEnvelopeWithoutAnEncWrapperAndDecryptReadsItBack(): void
    {
        $crypt = $this->crypt(self::SODIUM_KEY);

        $encrypt = new CommandTester(new EncryptCommand($crypt));
        $encrypt->execute(['message' => 'Encrypt me!']);
        $ciphertext = trim($encrypt->getDisplay());
        $this->assertTrue(str_starts_with($ciphertext, 'enc:v1:630dcd2966c43366:'), $ciphertext);

        $decrypt = new CommandTester(new DecryptCommand($crypt));
        $decrypt->execute(['ciphertext' => $ciphertext]);
        $this->assertSame('Encrypt me!', trim($decrypt->getDisplay()));
    }

    #[Test]
    public function decryptStillReadsALegacyValueWithTheDefuseKey(): void
    {
        $legacy = 'ENC[defuse/php-encryption,' . Crypto::encrypt('legacy', Key::loadFromAsciiSafeString(self::DEFUSE_KEY)) . ']';

        $decrypt = new CommandTester(new DecryptCommand($this->crypt(self::DEFUSE_KEY)));
        $decrypt->execute(['ciphertext' => $legacy]);

        $this->assertSame('legacy', trim($decrypt->getDisplay()));
    }

    #[Test]
    public function decryptRefusesAValueThatIsNotEncryptedRatherThanEchoingIt(): void
    {
        $this->expectException(CryptException::class);
        $this->expectExceptionMessage('Not an encrypted value');

        (new CommandTester(new DecryptCommand($this->crypt(self::SODIUM_KEY))))->execute(['ciphertext' => 'plain']);
    }

    #[Test]
    public function generateKeyPrintsOnlyABase64KeyOf32Bytes(): void
    {
        $tester = new CommandTester(new GenerateKeyCommand());
        $tester->execute([]);

        $this->assertSame(32, strlen((string) base64_decode(trim($tester->getDisplay()), true)));
    }
}
