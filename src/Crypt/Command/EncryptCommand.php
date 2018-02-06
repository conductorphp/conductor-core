<?php

namespace DevopsToolCore\Crypt\Command;

use DevopsToolCore\Crypt\Crypt;
use DevopsToolCore\MonologConsoleHandlerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EncryptCommand extends Command
{
    use MonologConsoleHandlerAwareTrait;
    /**
     * @var Crypt
     */
    private $crypt;
    /**
     * @var string
     */
    private $key;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * GenerateKeyCommand constructor.
     *
     * @param Crypt                $crypt
     * @param string               $key
     * @param LoggerInterface|null $logger
     * @param string|null          $name
     */
    public function __construct(
        Crypt $crypt,
        string $key,
        LoggerInterface $logger = null,
        string $name = null
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
    protected function configure()
    {
        $this->setName('crypt:encrypt')
            ->addArgument('message', InputArgument::REQUIRED, 'Message to encrypt')
            ->setDescription('Encrypt a message using configured key.')
            ->setHelp("This command encrypts a message using the configured key.");
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $message = $input->getArgument('message');
        $this->injectOutputIntoLogger($output, $this->logger);
        $output->writeln($this->crypt->encrypt($message, $this->key));
        return 0;
    }
}
