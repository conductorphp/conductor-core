<?php

namespace DevopsToolCore\Database\Command;

use DevopsToolCore\Database\DatabaseExportAdapterFactory;
use DevopsToolCore\Database\DatabaseExportAdapterInterface;
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
     * @var DatabaseExportAdapterInterface
     */
    private $databaseExportAdapter;
    /**
     * @var DatabaseExportAdapterFactory
     */
    private $databaseExportAdapterFactory;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * DatabaseExportCommand constructor.
     *
     * @param DatabaseExportAdapterFactory $databaseExportAdapterFactory
     * @param LoggerInterface|null         $logger
     * @param string|null                  $name
     */
    public function __construct(
        DatabaseExportAdapterFactory $databaseExportAdapterFactory,
        LoggerInterface $logger = null,
        $name = null
    ) {
        $this->databaseExportAdapterFactory = $databaseExportAdapterFactory;
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
        $supportedFormats = $this->databaseExportAdapterFactory->getSupportedFormats();
        $this->setName('database:export')
            ->addArgument(
                'format',
                InputArgument::REQUIRED,
                "Format to export to.\nSupported formats: <comment>" . implode(', ', $supportedFormats) . '</comment>'
            )
            ->addArgument('database', InputArgument::REQUIRED, 'Database to export.')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Directory to write export to. Must be a writable directory.',
                './'
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
        $this->databaseExportAdapter = $this->databaseExportAdapterFactory->create($input->getArgument('format'));
        $this->databaseExportAdapter->setLogger($this->logger);

        $database = $input->getArgument('database');
        $path = $input->getArgument('path');
        $ignoreTables = $input->getOption('ignore-tables') ? explode(',', $input->getOption('ignore-tables')) : [];

        $this->logger->info("Exporting database \"$database\"...");
        $filename = $this->databaseExportAdapter->exportToFile(
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
