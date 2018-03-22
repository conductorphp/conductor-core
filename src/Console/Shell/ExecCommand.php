<?php

namespace ConductorCore\Console\Shell;

use ConductorCore\Exception;
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
                'working-directory',
                null,
                InputOption::VALUE_REQUIRED,
                'Current working directory to run command from',
                null
            )
            ->addOption(
                'env',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Environment Variables (--env test1=123 --env test2=456)',
                null
            )
            ->addOption(
                'priority',
                null,
                InputOption::VALUE_REQUIRED,
                'Priority of -1 (low), 0 (normal), or 1 (high)',
                0
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

        $environmentVariables = [];
        if ($input->getOption('env')) {
            foreach ($input->getOption('env') as $value) {
                if (false === strpos($value, '=')) {
                    throw new Exception\InvalidArgumentException(
                        'Environment variables must be specified in the format --env myvar1=myval1 --env myvar2=myval2.'
                    );
                }
                [$key, $value] = explode('=', $value);
                $environmentVariables[$key] = $value;
            }
        }

        $output->write(
            $adapter->runShellCommand(
                $input->getArgument('cmd'),
                $input->getOption('working-directory'),
                $environmentVariables,
                $input->getOption('priority')
            )
        );
        return 0;
    }

}
