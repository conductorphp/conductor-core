<?php

namespace ConductorCore\Crypt\Command;

use ConductorCore\Exception;
use ConductorCore\Crypt\Crypt;
use ConductorCore\MonologConsoleHandlerAwareTrait;
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
     * @param string|null          $key
     * @param LoggerInterface|null $logger
     * @param string|null          $name
     */
    public function __construct(
        Crypt $crypt,
        string $key = null,
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
        if (empty($this->key)) {
            // Makes an assumption that this command is being built within Zend Expressive. Considering this ok
            // because we have to allow the factory to generate this class without the key, but we still want to be
            // able to show a useful error message explaining how to fix.
            throw new Exception\RuntimeException(
                'Configuration key "crypt_key" must be set. '
                . 'This can be generated with the crypt:generate-key command and must be added '
                . 'to config/autoload/local.php'
            );
        }

        $message = $input->getArgument('message');
        $this->injectOutputIntoLogger($output, $this->logger);
        $output->writeln($this->crypt->encrypt($message, $this->key));
        return 0;
    }
}
