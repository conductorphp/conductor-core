<?php

namespace ConductorCore\Console\Filesystem;

use ConductorCore\Exception;
use ConductorCore\Filesystem\MountManager\MountManager;
use ConductorCore\MonologConsoleHandlerAwareTrait;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FileNotFoundException;
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
                'paths',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                "Space-separated paths in the format {adapter}://{path}.\nAvailable Adapters: <comment>" . implode(
                    ', ',
                    $filesystemAdapterNames
                ) . '</comment>'
            )
            ->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Delete directories recursively.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not error if a file does not exist or there is an error deleting a file.')
            ->setDescription('Delete files from a filesystem.')
            ->setHelp("This command deletes files from a filesystem.");
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
        $this->mountManager->setWorkingDirectory(getcwd());

        $hasErrors = false;
        $paths = $input->getArgument('paths');
        foreach ($paths as $path) {
            [$prefix, $arguments] = $this->mountManager->filterPrefix([$path]);
            $path = $arguments[0];
            $filesystem = $this->mountManager->getFilesystem($prefix);
            $force = $input->getOption('force');

            if (!$filesystem->has($path)) {
                if ($force) {
                    continue;
                }

                throw new Exception\RuntimeException("Path \"$path\" does not exist.");
            }

            $result = $this->deletePath($input, $filesystem, $path, $force);
            if (false === $result) {
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            throw new Exception\RuntimeException(sprintf(
                'An error occurred deleting paths: "%s".',
                implode('", "', $paths)
            ));
        }

        return 0;
    }

    /**
     * @param InputInterface $input
     * @param FilesystemOperator $filesystem
     * @param string $path
     * @param $force
     * @throws FileNotFoundException
     */
    private function deletePath(InputInterface $input, FilesystemOperator $filesystem, string $path, $force): void
    {
        $isDir = $filesystem->directoryExists($path);
        if ($isDir) {
            if (!$input->getOption('recursive')) {
                throw new Exception\RuntimeException("Path \"$path\" is a directory. Provide --recursive argument "
                    . "if you really want to delete it.");
            }

            $filesystem->deleteDirectory($path);
        } else {
            $filesystem->delete($path);
        }
    }

}
