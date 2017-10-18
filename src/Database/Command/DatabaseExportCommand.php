<?php

namespace DevopsToolCore\Database\Command;

use App\Exception\DomainException;
use DevopsToolCore\Database\ImportExportAdapter\DatabaseImportExportAdapterInterface;
use DevopsToolCore\Database\ImportExportAdapter\MydumperDatabaseAdapter;
use DevopsToolCore\Database\ImportExportAdapter\MysqldumpDatabaseAdapter;
use DevopsToolCore\Database\ImportExportAdapter\MysqlTabDelimitedDatabaseAdapter;
use DevopsToolCore\MonologConsoleHandler;
use DevopsToolCore\ShellCommandHelper;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseExportCommand extends Command
{
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
                InputOption::VALUE_REQUIRED,
                sprintf(
                    'Format to export in. Must be "%s" or "%s".',
                    DatabaseImportExportAdapterInterface::FORMAT_MYDUMPER,
                    DatabaseImportExportAdapterInterface::FORMAT_SQL,
                    DatabaseImportExportAdapterInterface::FORMAT_TAB_DELIMITED
                ),
                DatabaseImportExportAdapterInterface::FORMAT_SQL
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

        $database = $input->getArgument('database');
        $filename = $input->getArgument('filename');
        if (!$filename) {
            $filename = './' . $database . '-' . date('Y-m-d-H-i-s-w');
        }
        $format = $input->getOption('format');
        $ignoreTables = $input->getOption('ignore-tables') ? explode(',', $input->getOption('ignore-tables')) : [];

        $logger = $this->getLogger($output);
        $shellCommandHelper = new ShellCommandHelper($logger);
        switch ($format) {
            case DatabaseImportExportAdapterInterface::FORMAT_MYDUMPER:
                $adapter = new MydumperDatabaseAdapter(null, $shellCommandHelper, $logger);
                break;

            case DatabaseImportExportAdapterInterface::FORMAT_SQL:
                $adapter = new MysqldumpDatabaseAdapter(null, $shellCommandHelper, $logger);
                break;

            case DatabaseImportExportAdapterInterface::FORMAT_TAB_DELIMITED:
                $adapter = new MysqlTabDelimitedDatabaseAdapter(null, $shellCommandHelper, $logger);
                break;

            default:
                throw new DomainException(
                    sprintf(
                        'Invalid format "%s" specified.',
                        $format
                    )
                );
        }

        $output->writeln("Exporting database \"$database\"...");
        $adapter->exportToFile(
            $database,
            $filename,
            $ignoreTables,
            $format,
            !$input->getOption('no-remove-definers')
        );
        $output->writeln("Database \"$database\" exported to \"$filename\"!");
        return 0;
    }

    /**
     * @param OutputInterface $output
     *
     * @return Logger
     */
    protected function getLogger(OutputInterface $output)
    {
        $logger = new Logger('database:export');
        $logger->pushHandler(new MonologConsoleHandler($output));
        return $logger;
    }

}
