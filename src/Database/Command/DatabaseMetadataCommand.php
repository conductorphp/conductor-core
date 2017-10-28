<?php

namespace DevopsToolCore\Database\Command;

use DevopsToolCore\Database\DatabaseMetadataProviderInterface;
use DevopsToolCore\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseMetadataCommand extends Command
{
    /**
     * @var DatabaseMetadataProviderInterface
     */
    private $databaseMetaDataProvider;

    public function __construct(DatabaseMetadataProviderInterface $databaseMetaDataProvider, $name = null)
    {
        parent::__construct($name);
        $this->databaseMetaDataProvider = $databaseMetaDataProvider;
    }

    protected function configure()
    {
        $this->setName('database:metadata')
            ->addOption('unit', null, InputOption::VALUE_REQUIRED, 'Unit to display sizes (B, KB, MB, or GB)', 'MB')
            ->addOption('precision', null, InputOption::VALUE_REQUIRED, 'Size display precision', '2')
            ->addOption('sort', null, InputOption::VALUE_REQUIRED, 'Sort key (name, size)', 'name')
            ->addOption('reverse-sort', null, InputOption::VALUE_NONE, 'Reverse sort')
            ->setDescription('Get database metadata.')
            ->setHelp("This command gets database metadata.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $databases = $this->databaseMetaDataProvider->getDatabaseMetadata();
        $databases = $this->sort($databases, $input->getOption('sort'), $input->getOption('reverse-sort'));
        $databases = $this->format(
            $databases,
            $input->getOption('unit'),
            $input->getOption('precision')
        );

        $outputTable = new Table($output);
        $outputTable
            ->setHeaders(array('Database', 'Size in ' . $input->getOption('unit')));
        foreach ($databases as $name => $database) {
            $outputTable->addRow([$name, $database['size']]);
        }
        $outputTable->render();
        return 0;
    }

    private function sort(array $databases, $sort, $reverseSort)
    {
        switch ($sort) {
            case 'name':
                ksort($databases);
                break;

            case 'size':
                uasort($databases, function($a, $b) {
                    return $a['size'] > $b['size'];
                });
                break;

            default:
                throw new Exception\DomainException("Invalid sort option \"$sort\".");
        }

        if ($reverseSort) {
            $databases = array_reverse($databases, true);
        }

        return $databases;
    }

    private function format($databases, $unit, $precision)
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

        foreach ($databases as &$database) {
            $database['size'] = round($database['size'] / $bytesToUnit, $precision);
        }
        unset($database);
        return $databases;
    }
}
