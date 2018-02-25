<?php

namespace ConductorCore\Shell\Command;

use ConductorCore\MonologConsoleHandlerAwareTrait;
use ConductorCore\Shell\ShellAdapterManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExecCommand extends Command
{
    use MonologConsoleHandlerAwareTrait;
    /**
     * @var ShellAdapterManager
     */
    private $shellAdapterManager;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ExecCommand constructor.
     *
     * @param ShellAdapterManager  $shellAdapterManager
     * @param LoggerInterface|null $logger
     * @param null                 $name
     */
    public function __construct(
        ShellAdapterManager $shellAdapterManager,
        LoggerInterface $logger = null,
        string $name = null
    ) {
        $this->shellAdapterManager = $shellAdapterManager;
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
        $adapterNames = $this->shellAdapterManager->getAdapterNames();
        $this->setName('shell:exec')
            ->addArgument('cmd', InputArgument::REQUIRED, 'Command to execute.')
            ->addOption(
                'adapter',
                null,
                InputOption::VALUE_OPTIONAL,
                'Shell adapter configuration to use. Configured adapters: <comment>' . implode(', ', $adapterNames)
                . '</comment>',
                'local'
            )
            ->addOption(
                'priority',
                null,
                InputOption::VALUE_REQUIRED,
                'Priority of -1 (low), 0 (normal), or 1 (high)',
                '0'
            )
            ->setDescription('Executes shell command on a given adapter.')
            ->setHelp("This command executes a shell command on a given adapter.");
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->injectOutputIntoLogger($output, $this->logger);
        $adapter = $this->shellAdapterManager->getAdapter($input->getOption('adapter'));
        if ($adapter instanceof LoggerAwareInterface) {
            $adapter->setLogger($this->logger);
        }
        $output->write($adapter->runShellCommand($input->getArgument('cmd')));
        return 0;
    }

}
