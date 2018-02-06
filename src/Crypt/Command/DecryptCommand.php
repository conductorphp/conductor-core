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

class DecryptCommand extends Command
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
        $this->setName('crypt:decrypt')
            ->addArgument('ciphertext', InputArgument::REQUIRED, 'Ciphertext to decrypt.')
            ->setDescription('Decrypt ciphertext using configured key.')
            ->setHelp("This command decrypts ciphertext using the configured key.");
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ciphertext = $input->getArgument('ciphertext');
        $this->injectOutputIntoLogger($output, $this->logger);
        $output->writeln($this->crypt->decrypt($ciphertext, $this->key));
        return 0;
    }
}
