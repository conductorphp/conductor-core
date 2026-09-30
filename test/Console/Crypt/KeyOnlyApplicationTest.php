<?php

declare(strict_types=1);

namespace ConductorCoreTest\Console\Crypt;

use ConductorCore\Console\Crypt\KeyOnlyApplication;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function putenv;
use function str_starts_with;
use function trim;

/**
 * CTAP-2052: the crypt commands run from the key variables alone, without the project config that
 * `bin/conductor` would otherwise load (and fail on) first.
 */
final class KeyOnlyApplicationTest extends TestCase
{
    private const SODIUM_KEY = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';

    protected function tearDown(): void
    {
        putenv('CONDUCTOR_CRYPT_KEY');
        putenv('CONDUCTOR_CRYPT_KEYS_PREVIOUS');
    }

    /** @param list<string> $argv */
    #[Test]
    #[DataProvider('cryptCommands')]
    public function theCryptCommandsGetTheKeyOnlyApplication(array $argv): void
    {
        self::assertNotNull((new KeyOnlyApplication())->forInput(new ArgvInput($argv)));
    }

    /** @return array<string, array{list<string>}> */
    public static function cryptCommands(): array
    {
        return [
            'generate-key'        => [['conductor', 'crypt:generate-key']],
            'encrypt'             => [['conductor', 'crypt:encrypt', 'secret']],
            'decrypt'             => [['conductor', 'crypt:decrypt', 'enc:v1:x:y']],
            'option before name'  => [['conductor', '-q', 'crypt:encrypt', 'secret']],
        ];
    }

    /** @param list<string> $argv */
    #[Test]
    #[DataProvider('otherCommands')]
    public function everythingElseBootsTheFullApplication(array $argv): void
    {
        self::assertNull((new KeyOnlyApplication())->forInput(new ArgvInput($argv)));
    }

    /** @return array<string, array{list<string>}> */
    public static function otherCommands(): array
    {
        return [
            'deploy'     => [['conductor', 'app:deploy']],
            'list'       => [['conductor', 'list']],
            'no command' => [['conductor']],
        ];
    }

    #[Test]
    public function encryptThenDecryptRoundTripsUnderTheEnvironmentKey(): void
    {
        putenv('CONDUCTOR_CRYPT_KEY=' . self::SODIUM_KEY);

        $encrypted = $this->runCommand(['command' => 'crypt:encrypt', 'message' => 'Encrypt me!']);
        self::assertTrue(str_starts_with($encrypted, 'enc:v1:'), $encrypted);

        self::assertSame('Encrypt me!', $this->runCommand(['command' => 'crypt:decrypt', 'ciphertext' => $encrypted]));
    }

    /** @param array<string, string> $input */
    private function runCommand(array $input): string
    {
        $application = (new KeyOnlyApplication())->forInput(new ArrayInput($input));
        self::assertNotNull($application);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $output = new BufferedOutput();
        $application->run(new ArrayInput($input), $output);

        return trim($output->fetch());
    }
}
