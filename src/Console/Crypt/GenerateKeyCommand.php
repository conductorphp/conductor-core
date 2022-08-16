<?php

namespace ConductorCore\Console\Crypt;

use ConductorCore\Crypt\Crypt;
use ConductorCore\MonologConsoleHandlerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateKeyCommand extends Command
{
    use MonologConsoleHandlerAwareTrait;

    private Crypt $crypt;
    private LoggerInterface $logger;

    public function __construct(
        Crypt            $crypt,
        ?LoggerInterface $logger = null,
        ?string          $name = null
    ) {
        $this->crypt = $crypt;
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('crypt:generate-key')
            ->setDescription('Generate a key to be used for encrypting configuration values.')
            ->setHelp("This command generates a key to be used for encrypting configuration values.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->injectOutputIntoLogger($output, $this->logger);
        $output->writeln($this->crypt->generateKey());
        return self::SUCCESS;
    }
}
