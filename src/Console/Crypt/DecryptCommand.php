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
 * Prints the plaintext of an `enc:v1:<keyId>:…` value (the key it names must be in
 * `CONDUCTOR_CRYPT_KEY` or `CONDUCTOR_CRYPT_KEYS_PREVIOUS`) or of an `ENC[defuse/php-encryption,…]`
 * value (`CONDUCTOR_CRYPT_KEY` must still hold the defuse key). Anything else is refused rather
 * than echoed back (CTAP-1968).
 */
class DecryptCommand extends Command
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
        $this->setName('crypt:decrypt')
            ->addArgument('ciphertext', InputArgument::OPTIONAL, 'Ciphertext to decrypt.')
            ->setDescription('Decrypt an enc:v1:… or ENC[…] value with the configured keys.')
            ->setHelp(
                "Decrypts an enc:v1:<keyId>:<base64> value with the key it names, from CONDUCTOR_CRYPT_KEY or\n"
                . "CONDUCTOR_CRYPT_KEYS_PREVIOUS, or an ENC[defuse/php-encryption,...] value with the defuse key\n"
                . "still held in CONDUCTOR_CRYPT_KEY."
            )
            ->addOption('file', null, InputOption::VALUE_OPTIONAL, 'File path to read ciphertext from.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ciphertext = $input->getArgument('ciphertext');
        $file       = $input->getOption('file');

        if (! $ciphertext && ! $file) {
            throw new Exception\RuntimeException('<ciphertext> or --file must be given.');
        }

        if ($file) {
            if (! file_exists($file) || ! is_file($file) || ! is_readable($file)) {
                throw new Exception\RuntimeException(sprintf('Path "%s" must be readable file.', $file));
            }

            $ciphertext = trim((string) file_get_contents($file));
        }

        $ciphertext = (string) $ciphertext;
        if (! $this->crypt->isEncrypted($ciphertext)) {
            throw new Exception\CryptException(
                'Not an encrypted value: expected enc:v1:<keyId>:<base64> or ENC[defuse/php-encryption,...].',
            );
        }

        $this->injectOutputIntoLogger($output, $this->logger);
        $output->writeln($this->crypt->decrypt($ciphertext));

        return self::SUCCESS;
    }
}
