<?php

namespace ConductorCoreTest\Console\Crypt;

use ConductorCore\Console\Crypt\DecryptCommand;
use ConductorCore\Console\Crypt\DecryptCommandFactory;
use ConductorCore\Console\Crypt\EncryptCommand;
use ConductorCore\Console\Crypt\EncryptCommandFactory;
use ConductorCore\Crypt\Crypt;
use ConductorCore\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A conductor whose secrets are `${VAR}` placeholders has no crypt_key, and its merged config carries
 * the key as an explicit null. Symfony instantiates every registered command to render the list, so
 * a command that refuses a null key in its constructor takes the whole CLI down with it
 * (CTAP-1730). The refusal belongs at use: run crypt:encrypt or crypt:decrypt with no key and you
 * get the message that says how to fix it; run anything else and the key is never looked at.
 */
class CryptCommandsWithoutKeyTest extends TestCase
{
    private const KEY = 'def00000de54d8d8cb4804e9748968de2edc1b130ba31df5c5fa0eb662bfe4c6d2caaec614eb5de3628bd3220331f21b3e3b6ccb1332a7691d081b6317c721657ded544f';

    public function testBothCommandsCanBeBuiltWithoutAKey(): void
    {
        $crypt = new Crypt();

        $this->assertInstanceOf(DecryptCommand::class, new DecryptCommand($crypt, null));
        $this->assertInstanceOf(EncryptCommand::class, new EncryptCommand($crypt, null));
    }

    /** The factories read the merged config, where a missing key is an explicit null, not an absent index. */
    public function testTheFactoriesBuildBothCommandsFromAConfigWithANullKey(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            ['config', ['crypt_key' => null]],
            [Crypt::class, new Crypt()],
        ]);

        $this->assertInstanceOf(DecryptCommand::class, (new DecryptCommandFactory())($container, DecryptCommand::class));
        $this->assertInstanceOf(EncryptCommand::class, (new EncryptCommandFactory())($container, EncryptCommand::class));
    }

    public function testEncryptWithoutAKeyFailsWithTheConfigurationMessage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configuration key "crypt_key" must be set.');

        (new CommandTester(new EncryptCommand(new Crypt(), null)))->execute(['message' => 'Encrypt me!']);
    }

    public function testDecryptWithoutAKeyFailsWithTheConfigurationMessage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configuration key "crypt_key" must be set.');

        (new CommandTester(new DecryptCommand(new Crypt(), null)))->execute(['ciphertext' => 'def5020...']);
    }

    /** A conductor that does configure a key is unchanged: the commands still round-trip. */
    public function testTheCommandsStillRoundTripWithAKey(): void
    {
        $crypt = new Crypt();

        $encrypt = new CommandTester(new EncryptCommand($crypt, self::KEY));
        $encrypt->execute(['message' => 'Encrypt me!']);
        $ciphertext = trim($encrypt->getDisplay());
        $this->assertNotSame('Encrypt me!', $ciphertext);

        $decrypt = new CommandTester(new DecryptCommand($crypt, self::KEY));
        $decrypt->execute(['ciphertext' => $ciphertext]);
        $this->assertSame('Encrypt me!', trim($decrypt->getDisplay()));
    }
}
