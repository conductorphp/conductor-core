<?php

namespace DevopsToolCore\Database\Command;

use DevopsToolCore\Database\DatabaseImportExportAdapterManager;
use DevopsToolCore\MonologConsoleHandlerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseExportCommand extends Command
{
    use MonologConsoleHandlerAwareTrait;

    /**
     * @var DatabaseImportExportAdapterManager
     */
    private $databaseImportExportAdapterManager;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * DatabaseExportCommand constructor.
     *
     * @param DatabaseImportExportAdapterManager $databaseImportExportAdapterManager
     * @param LoggerInterface|null               $logger
     * @param string|null                        $name
     */
    public function __construct(
        DatabaseImportExportAdapterManager $databaseImportExportAdapterManager,
        LoggerInterface $logger = null,
        string $name = null
    ) {
        $this->databaseImportExportAdapterManager = $databaseImportExportAdapterManager;
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
        $adapterNames = $this->databaseImportExportAdapterManager->getAdapterNames();
        $this->setName('database:export')
            ->addArgument('database', InputArgument::REQUIRED, 'Database to export.')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Directory to write export to. Must be a writable directory.',
                './'
            )
            ->addOption(
                'adapter',
                null,
                InputOption::VALUE_OPTIONAL,
                'Database import/export adapter configuration to use. Configured adapters: <comment>' . implode(', ', $adapterNames) . '</comment>',
                'default'
            )
            ->addOption(
                'ignore-tables',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma separated list of tables to ignore data from'
            )
            ->addOption(
                'no-remove-definers',
                null,
                InputOption::VALUE_NONE,
                'Do not remove definers from triggers, routines, views, and events.'
            )
            ->setDescription('Export a database.')
            ->setHelp("This command exports a database.");
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
        $adapter = $this->databaseImportExportAdapterManager->getAdapter($input->getOption('adapter'));
        $adapter->setLogger($this->logger);

        $database = $input->getArgument('database');
        $path = $input->getArgument('path');
        $ignoreTables = $input->getOption('ignore-tables') ? explode(',', $input->getOption('ignore-tables')) : [];

        $this->logger->info("Exporting database \"$database\"...");
        $filename = $adapter->exportToFile(
            $database,
            $path,
            [
                'ignore_tables' => $ignoreTables,
                'remove_definers' => !$input->getOption('no-remove-definers'),
            ]
        );
        $this->logger->info("Database \"$database\" exported to \"$filename\"!");
        return 0;
    }

}
