<?php

namespace DevopsToolCore\Database\Command;

use DevopsToolCore\MonologConsoleHandler;
use DevopsToolCore\ShellCommandHelper;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseSizesCommand extends Command
{
    protected $_command
        = "mysql -t -e 'SELECT table_schema AS \"DevopsToolCore\Database\", 
	                                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS \"Size (MB)\" 
										FROM information_schema.TABLES 
										GROUP BY table_schema;'";

    protected function configure()
    {
        $this->setName('database:sizes')
            ->setDescription('Export a database.')
            ->setHelp("This command exports a database.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        error_reporting(-1);
        $logger = $this->getLogger($output);
        $shellCommandHelper = new ShellCommandHelper($logger);
        $output->writeln($shellCommandHelper->runShellCommand($this->_command));
        return 0;
    }

    /**
     * @param OutputInterface $output
     *
     * @return Logger
     */
    protected function getLogger(OutputInterface $output)
    {
        $logger = new Logger('database-export');
        $logger->pushHandler(new MonologConsoleHandler($output));
        return $logger;
    }
}
