<?php

namespace ConductorCore\Console\Filesystem;

use ConductorCore\Exception;
use ConductorCore\Filesystem\MountManager\MountManager;
use ConductorCore\MonologConsoleHandlerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class FilesystemRmCommand
 *
 * @todo    Add support for glob patterns?
 * @package ConductorCore\Console\Filesystem
 */
class FilesystemRmCommand extends Command
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
     */
    protected function configure()
    {
        $filesystemAdapterNames = $this->mountManager->getFilesystemPrefixes();
        $this->setName('filesystem:rm')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                "Path in the format {adapter}://{path}.\nAvailable Adapters: <comment>" . implode(
                    ', ',
                    $filesystemAdapterNames
                ) . '</comment>'
            )
            ->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Delete directory recursively.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not error if file does not exist or there is an error deleting the file.')
            ->setDescription('Delete a file or directory from a filesystem.')
            ->setHelp("This command deletes a file or directory from a filesystem.");
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

        $path = $input->getArgument('path');
        list($prefix,) = $this->mountManager->filterPrefix([$path]);
        $path = trim(substr($path, strlen($prefix) + 3), '/');
        $filesystem = $this->mountManager->getFilesystem($prefix);
        $force = $input->getOption('force');

        if (!$filesystem->has($path)) {
            if ($force) {
                return 0;
            }

            throw new Exception\RuntimeException("Path \"$path\" does not exist.");
        }

        $metaData = $this->normalizeMetadata($filesystem->getMetadata($path));

        if ('dir' == $metaData['type']) {
            if (!$input->getOption('recursive')) {
                throw new Exception\RuntimeException("Path \"$path\" is a directory. Provide --recursive argument "
                    . "if you really want to delete it.");
            }

            $successful = $filesystem->deleteDir($path);
            if (!$successful && !$force) {
                throw new Exception\RuntimeException("Error deleting directory \"$path\".");
            }
        } else {
            $successful = $filesystem->delete($path);
            if (!$successful && !$force) {
                throw new Exception\RuntimeException("Error deleting file \"$path\".");
            }
        }

        return 0;
    }


    /**
     * @param array  $metaData
     *
     * @return array
     */
    private function normalizeMetadata(array $metaData): array
    {
        if (empty($metaData['type'])) {
            $metaData['type'] = 'dir';
        }

        return $metaData;
    }

}
