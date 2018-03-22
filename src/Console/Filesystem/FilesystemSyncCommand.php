<?php

namespace ConductorCore\Console\Filesystem;

use ConductorCore\Filesystem\MountManager\MountManager;
use ConductorCore\MonologConsoleHandlerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FilesystemSyncCommand extends Command
{
    use MonologConsoleHandlerAwareTrait;

    /**
     * @var MountManager
     */
    private $mountManager;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * DatabaseExportCommand constructor.
     *
     * @param MountManager         $mountManager
     * @param LoggerInterface|null $logger
     * @param string|null          $name
     */
    public function __construct(
        MountManager $mountManager,
        LoggerInterface $logger = null,
        string $name = null
    ) {
        $this->mountManager = $mountManager;
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
        parent::__construct($name);
    }

    /**
     * @return void
     *
     * @todo Add option for excludes and option for includes
     * @todo Add option for whether to copy over permissions. Only "visibility" is possible with Flysystem since not
     *       all filesystems have the same concept of permissions
     */
    protected function configure()
    {

        $filesystemAdapterNames = $this->mountManager->getFilesystemPrefixes();
        $this->setName('filesystem:sync')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                "Source path in the format {adapter}://{path}.\nAvailable Adapters: <comment>" . implode(
                    ', ',
                    $filesystemAdapterNames
                )
                . '</comment>'
            )
            ->addArgument(
                'destination',
                InputArgument::REQUIRED,
                "Destination path in the format {adapter}://{path}.\nAvailable Adapters: <comment>" . implode(
                    ', ',
                    $filesystemAdapterNames
                )
                . '</comment>'
            )
            ->addOption('delete', null, InputOption::VALUE_NONE, 'Delete files in destination filesystem that do not exist in source')
            ->addOption('ignore-timestamps', null, InputOption::VALUE_NONE, 'Push all files, even if a newer matching file exists on the destination')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Path to exclude, in rsync format')
            ->addOption('include', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Excluded path to include, in rsync format')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Batch size for copy and delete operations', 100)
            ->setDescription(
                'Copy a directory from a source filesystem directory to a destination filesystem directory.'
            )
            ->setHelp(
                "This command copies a directory from a source filesystem directory to a destination filesystem directory."
            );
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
        $this->mountManager->setLogger($this->logger);
        $source = $input->getArgument('source');
        $destination = $input->getArgument('destination');

        $options = [
            'delete' => $input->getOption('delete'),
            'ignore_timestamps' => $input->getOption('ignore-timestamps'),
            'excludes' => $input->getOption('exclude'),
            'includes' => $input->getOption('include'),
            'batch_size' => $input->getOption('batch-size'),
        ];
        $this->mountManager->sync($source, $destination, $options);
        return 0;
    }

}
