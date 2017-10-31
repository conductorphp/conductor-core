<?php

namespace DevopsToolCore\Database\Command;

use DevopsToolCore\Database\DatabaseMetadataProviderInterface;
use DevopsToolCore\Exception;
use DevopsToolCore\MonologConsoleHandlerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TableMetadataCommand extends Command
{
    use MonologConsoleHandlerAwareTrait;
    /**
     * @var DatabaseMetadataProviderInterface
     */
    private $databaseMetaDataProvider;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * DatabaseMetadataCommand constructor.
     *
     * @param DatabaseMetadataProviderInterface $databaseMetaDataProvider
     * @param LoggerInterface|null              $logger
     * @param null                              $name
     */
    public function __construct(
        DatabaseMetadataProviderInterface $databaseMetaDataProvider,
        LoggerInterface $logger = null,
        $name = null
    ) {
        $this->databaseMetaDataProvider = $databaseMetaDataProvider;
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
        $this->setName('database:table:metadata')
            ->addArgument('database', InputArgument::REQUIRED, 'Database to get table sizes from')
            ->addOption(
                'connection',
                null,
                InputOption::VALUE_OPTIONAL,
                'Database connection configuration to use.',
                'default'
            )
            ->addOption('unit', null, InputOption::VALUE_REQUIRED, 'Unit to display sizes (B, KB, MB, or GB)', 'MB')
            ->addOption('precision', null, InputOption::VALUE_REQUIRED, 'Size display precision', '2')
            ->addOption('sort', null, InputOption::VALUE_REQUIRED, 'Sort key (name, size)', 'name')
            ->addOption('reverse-sort', null, InputOption::VALUE_NONE, 'Reverse sort')
            ->setDescription('Get database table metadata.')
            ->setHelp("This command gets database table metadata.");
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
        $this->logger->info('Getting table metadata for database "' . $input->getArgument('database') . '"...');
        $this->databaseMetaDataProvider->selectConnection($input->getOption('connection'));
        $tables = $this->getTables($input);
        $outputTable = new Table($output);
        $outputTable
            ->setHeaders(array('Database', 'Rows', 'Size in ' . $input->getOption('unit')));
        foreach ($tables as $name => $table) {
            $outputTable->addRow([$name, $table['rows'], $table['size']]);
        }
        $outputTable->render();
        return 0;
    }

    /**
     * @param InputInterface $input
     *
     * @return array
     */
    private function getTables(InputInterface $input)
    {
        $tables = $this->databaseMetaDataProvider->getTableMetadata($input->getArgument('database'));
        $tables = $this->sort($tables, $input->getOption('sort'), $input->getOption('reverse-sort'));
        $tables = $this->format(
            $tables,
            $input->getOption('unit'),
            $input->getOption('precision')
        );
        return $tables;
    }

    /**
     * @param array  $databases
     * @param string $sort
     * @param bool   $reverseSort
     *
     * @return array
     */
    private function sort(array $tables, $sort, $reverseSort)
    {
        switch ($sort) {
            case 'name':
                ksort($tables);
                break;

            case 'size':
                uasort(
                    $tables,
                    function ($a, $b) {
                        return $a['size'] > $b['size'];
                    }
                );
                break;

            default:
                throw new Exception\DomainException("Invalid sort option \"$sort\".");
        }

        if ($reverseSort) {
            $tables = array_reverse($tables, true);
        }

        return $tables;
    }

    /**
     * @param array  $databases
     * @param string $unit
     * @param int    $precision
     *
     * @return array
     */
    private function format($tables, $unit, $precision)
    {
        switch ($unit) {
            case 'B':
                $bytesToUnit = 1;
                break;

            case 'KB':
                $bytesToUnit = pow(1024, 1);
                break;

            case 'MB':
                $bytesToUnit = pow(1024, 2);
                break;

            case 'GB':
                $bytesToUnit = pow(1024, 3);
                break;

            default:
                throw new Exception\DomainException("Invalid \$unit \"$unit\".");
        }

        foreach ($tables as &$table) {
            $table['rows'] = number_format($table['rows']);
            $table['size'] = round($table['size'] / $bytesToUnit, $precision);
        }
        unset($table);
        return $tables;
    }


}
