<?php

declare(strict_types=1);

namespace ConductorCore\Console\Crypt;

use ConductorCore\Crypt\CryptInterface;
use ConductorCore\Exception;
use ConductorCore\MonologConsoleHandlerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function file_exists;
use function file_get_contents;
use function is_file;
use function is_readable;
use function sprintf;
use function trim;

/**
 * Prints `enc:v1:<keyId>:<base64>` for a message, encrypted under `CONDUCTOR_CRYPT_KEY`. No
 * `ENC[…]` wrapper: the same string works in conductor YAML and in the middleware (CTAP-1968).
 *
 * Built without checking for a key: Symfony instantiates every command to render the list, and a
 * conductor with no key must still boot (CTAP-1730). With no key, encrypting fails at use with the
 * message that says how to fix it.
 */
class EncryptCommand extends Command
{
    use MonologConsoleHandlerAwareTrait;

    private LoggerInterface $logger;

    public function __construct(
        private readonly CryptInterface $crypt,
        ?LoggerInterface $logger = null,
        ?string $name = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('crypt:encrypt')
            ->addArgument('message', InputArgument::OPTIONAL, 'Message to encrypt')
            ->setDescription('Encrypt a message under CONDUCTOR_CRYPT_KEY; prints enc:v1:<keyId>:<base64>.')
            ->setHelp(
                "Encrypts a message under CONDUCTOR_CRYPT_KEY (a sodium key from crypt:generate-key) and prints\n"
                . "the enc:v1:<keyId>:<base64> envelope, ready to paste into configuration as-is."
            )
            ->addOption('file', null, InputOption::VALUE_OPTIONAL, 'File path to read message from.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = $input->getArgument('message');
        $file    = $input->getOption('file');

        if (! $message && ! $file) {
            throw new Exception\RuntimeException('<message> or --file must be given.');
        }

        if ($file) {
            if (! file_exists($file) || ! is_file($file) || ! is_readable($file)) {
                throw new Exception\RuntimeException(sprintf('Path "%s" must be readable file.', $file));
            }

            $message = trim((string) file_get_contents($file));
        }

        $this->injectOutputIntoLogger($output, $this->logger);
        $output->writeln($this->crypt->encrypt((string) $message));

        return self::SUCCESS;
    }
}
