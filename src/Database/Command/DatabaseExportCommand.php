<?php

namespace DevopsToolCore\Database\Command;

use DevopsToolCore\Database\DatabaseExportAdapterFactory;
use DevopsToolCore\Database\DatabaseExportAdapterInterface;
use DevopsToolCore\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseExportCommand extends Command
{
    /**
     * @var DatabaseExportAdapterInterface
     */
    private $databaseExportAdapter;
    /**
     * @var DatabaseExportAdapterFactory
     */
    private $databaseExportAdapterFactory;

    public function __construct(DatabaseExportAdapterFactory $databaseExportAdapterFactory, $name = null)
    {
        parent::__construct($name);
        $this->databaseExportAdapterFactory = $databaseExportAdapterFactory;
    }

    protected function configure()
    {
        $this->setName('database:export')
            ->addArgument('database', InputArgument::REQUIRED, 'Database to export.')
            ->addArgument('filename', InputArgument::OPTIONAL, 'Filename to export database to.')
            ->addOption(
                'path',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory to write export files to.',
                '.'
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        error_reporting(-1);
        $output->writeln(
            [
                'Database: Export',
                '============',
                '',
            ]
        );

        $this->databaseExportAdapter = $this->databaseExportAdapterFactory->create($input->getOption('format'));

        if (!$this->databaseExportAdapter->isUsable()) {
            throw new Exception\RuntimeException(
                sprintf(
                    'The given database export adapter "%s" is not usable in this environment.',
                    get_class($this->databaseExportAdapter)
                )
            );
        }

        $database = $input->getArgument('database');
        $filename = $input->getArgument('filename');
        if (!$filename) {
            $filename = './' . $database . '-' . date('Y-m-d-H-i-s-w');
        }
        $ignoreTables = $input->getOption('ignore-tables') ? explode(',', $input->getOption('ignore-tables')) : [];

        $output->writeln("Exporting database \"$database\"...");
        $this->databaseExportAdapter->exportToFile(
            $database,
            $filename,
            $ignoreTables,
            !$input->getOption('no-remove-definers')
        );
        $fullFilename = realpath("$filename.{$this->databaseExportAdapter->getFileExtension()}");
        $output->writeln("Database \"$database\" exported to \"$fullFilename\"!");
        return 0;
    }

}
