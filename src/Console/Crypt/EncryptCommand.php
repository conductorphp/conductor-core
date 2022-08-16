<?php

namespace ConductorCore\Console\Crypt;

use ConductorCore\Crypt\Crypt;
use ConductorCore\Exception;
use ConductorCore\MonologConsoleHandlerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EncryptCommand extends Command
{
    use MonologConsoleHandlerAwareTrait;

    private Crypt $crypt;
    private string $key;
    private LoggerInterface $logger;

    public function __construct(
        Crypt            $crypt,
        string           $key,
        ?LoggerInterface $logger = null,
        ?string          $name = null
    ) {
        $this->crypt = $crypt;
        $this->key = $key;
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
        parent::__construct($name);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('crypt:encrypt')
            ->addArgument('message', InputArgument::OPTIONAL, 'Message to encrypt')
            ->setDescription('Encrypt a message using configured key.')
            ->setHelp("This command encrypts a message using the configured key.")
            ->addOption(
                'file',
                null,
                InputOption::VALUE_OPTIONAL,
                'File path to read message from.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = $input->getArgument('message');
        $file = $input->getOption('file');

        if (!$message && !$file) {
            throw new Exception\RuntimeException(
                '<message> or --file must be given.'
            );
        }

        if ($file) {
            if (!file_exists($file) || !is_file($file) || !is_readable($file)) {
                throw new Exception\RuntimeException(sprintf(
                    'Path "%s" must be readable file.',
                    $file
                ));
            }

            $message = trim(file_get_contents($file));
        }

        $this->injectOutputIntoLogger($output, $this->logger);
        $output->writeln($this->crypt->encrypt($message, $this->key));
        return self::SUCCESS;
    }
}
