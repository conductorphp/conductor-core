<?php

namespace ConductorCore\Console\Database;

use ConductorCore\Database\DatabaseAdapterManager;
use ConductorCore\Exception;
use ConductorCore\MonologConsoleHandlerAwareTrait;
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

    private DatabaseAdapterManager $databaseAdapterManager;
    private LoggerInterface $logger;

    public function __construct(
        DatabaseAdapterManager $databaseImportAdapterManager,
        LoggerInterface        $logger = null,
        string                 $name = null
    ) {
        $this->databaseAdapterManager = $databaseImportAdapterManager;
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $adapterNames = $this->databaseAdapterManager->getAdapterNames();
        $this->setName('database:metadata')
            ->addOption(
                'adapter',
                null,
                InputOption::VALUE_OPTIONAL,
                'Database adapter configuration to use. Configured adapters: <comment>' . implode(', ', $adapterNames)
                . '</comment>',
                'default'
            )
            ->addOption('unit', null, InputOption::VALUE_REQUIRED, 'Unit to display sizes (B, KB, MB, or GB)', 'MB')
            ->addOption('precision', null, InputOption::VALUE_REQUIRED, 'Size display precision', '2')
            ->addOption('sort', null, InputOption::VALUE_REQUIRED, 'Sort key (name, size)', 'name')
            ->addOption('reverse-sort', null, InputOption::VALUE_NONE, 'Reverse sort')
            ->setDescription('Get database metadata.')
            ->setHelp("This command gets database metadata.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->injectOutputIntoLogger($output, $this->logger);
        $this->logger->info('Getting database metadata.');
        $databaseAdapter = $this->databaseAdapterManager->getAdapter($input->getOption('adapter'));
        $databases = $databaseAdapter->getDatabaseMetadata();
        $databases = $this->sort($databases, $input->getOption('sort'), $input->getOption('reverse-sort'));
        $databases = $this->format(
            $databases,
            $input->getOption('unit'),
            $input->getOption('precision')
        );

        $outputTable = new Table($output);
        $outputTable
            ->setHeaders(['Database', 'Size in ' . $input->getOption('unit')]);
        foreach ($databases as $name => $database) {
            $outputTable->addRow([$name, $database['size']]);
        }
        $outputTable->render();
        return self::SUCCESS;
    }

    private function sort(array $databases, string $sort, bool $reverseSort): array
    {
        switch ($sort) {
            case 'name':
                ksort($databases);
                break;

            case 'size':
                uasort(
                    $databases,
                    static function ($a, $b) {
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

    private function format(array $databases, string $unit, int $precision): array
    {
        $bytesToUnit = match ($unit) {
            'B' => 1,
            'KB' => 1024 ** 1,
            'MB' => 1024 ** 2,
            'GB' => 1024 ** 3,
            default => throw new Exception\DomainException("Invalid \$unit \"$unit\"."),
        };

        foreach ($databases as &$database) {
            $database['size'] = round($database['size'] / $bytesToUnit, $precision);
        }
        unset($database);
        return $databases;
    }
}
