<?php

namespace DevopsToolCore\Database\Command;

use DevopsToolCore\Database\DatabaseMetadataProviderInterface;
use DevopsToolCore\Exception;
use DevopsToolCore\MonologConsoleHandlerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseMetadataCommand extends Command
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
        $this->setName('database:metadata')
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
            ->setDescription('Get database metadata.')
            ->setHelp("This command gets database metadata.");
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
        $this->logger->info('Getting database metadata...');
        $this->databaseMetaDataProvider->selectConnection($input->getOption('connection'));
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

    /**
     * @param array  $databases
     * @param string $sort
     * @param bool   $reverseSort
     *
     * @return array
     */
    private function sort(array $databases, $sort, $reverseSort)
    {
        switch ($sort) {
            case 'name':
                ksort($databases);
                break;

            case 'size':
                uasort(
                    $databases,
                    function ($a, $b) {
                        return $a['size'] > $b['size'];
                    }
                );
                break;

            default:
                throw new Exception\DomainException("Invalid sort option \"$sort\".");
        }

        if ($reverseSort) {
            $databases = array_reverse($databases, true);
        }

        return $databases;
    }

    /**
     * @param array  $databases
     * @param string $unit
     * @param int    $precision
     *
     * @return array
     */
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
