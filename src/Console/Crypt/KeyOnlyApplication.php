<?php

declare(strict_types=1);

namespace ConductorCore\Console\Crypt;

use ConductorCore\Config\EnvironmentConfig;
use ConductorCore\Crypt\CryptResolverFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;

use function getenv;
use function in_array;
use function is_string;

/**
 * Runs the crypt commands without loading the project's configuration (CTAP-2052).
 *
 * `bin/conductor` builds the container from `config/container.php`, which loads, interpolates and
 * decrypts the whole project config before any command runs. The crypt commands need none of it,
 * only the key variables, but they failed with everything else whenever the config could not load:
 *
 * - `crypt:generate-key` on a host that has not set the database variables yet
 *   ("Configuration references 6 undefined variables: DATABASE_ROOT_USER …");
 * - `crypt:encrypt` in a project whose config still holds ciphertext under someone else's key:
 *   the one command that re-encrypts those values could not start because of them.
 *
 * The keys come from `CONDUCTOR_CRYPT_KEY` / `CONDUCTOR_CRYPT_KEYS_PREVIOUS`, exactly as the
 * container reads them ({@see EnvironmentConfig}), so the result is the same either way.
 */
final class KeyOnlyApplication
{
    public const COMMANDS = ['crypt:generate-key', 'crypt:encrypt', 'crypt:decrypt'];

    public function __construct(private readonly CryptResolverFactory $cryptResolverFactory = new CryptResolverFactory())
    {
    }

    /** Null when the input names any other command, which then boots the full application. */
    public function forInput(InputInterface $input): ?Application
    {
        $command = $input->getFirstArgument();
        if (! is_string($command) || ! in_array($command, self::COMMANDS, true)) {
            return null;
        }

        $crypt = $this->cryptResolverFactory->fromKeys(
            $this->variable(EnvironmentConfig::CRYPT_KEY_VARIABLE),
            $this->variable(EnvironmentConfig::CRYPT_KEYS_PREVIOUS_VARIABLE),
        );

        $application = new Application('Application console');
        $application->addCommand(new GenerateKeyCommand());
        $application->addCommand(new EncryptCommand($crypt));
        $application->addCommand(new DecryptCommand($crypt));

        return $application;
    }

    private function variable(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
