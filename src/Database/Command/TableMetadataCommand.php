<?php

namespace DevopsToolCore\Database\Command;

use DevopsToolCore\Database\DatabaseMetaDataProviderInterface;
use DevopsToolCore\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TableMetadataCommand extends Command
{
    /**
     * @var DatabaseMetaDataProviderInterface
     */
    private $databaseMetaDataProvider;

    public function __construct(DatabaseMetaDataProviderInterface $databaseMetaDataProvider, $name = null)
    {
        parent::__construct($name);
        $this->databaseMetaDataProvider = $databaseMetaDataProvider;
    }

    protected function configure()
    {
        $this->setName('database:table:metadata')
            ->addArgument('database', InputArgument::REQUIRED, 'Database to get table sizes from')
            ->addOption('unit', null, InputOption::VALUE_REQUIRED, 'Unit to display sizes (B, KB, MB, or GB)', 'MB')
            ->addOption('precision', null, InputOption::VALUE_REQUIRED, 'Size display precision', '2')
            ->addOption('sort', null, InputOption::VALUE_REQUIRED, 'Sort key (name, size)', 'name')
            ->addOption('reverse-sort', null, InputOption::VALUE_NONE, 'Reverse sort')
            ->setDescription('Get database table metadata.')
            ->setHelp("This command gets database table metadata.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
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
