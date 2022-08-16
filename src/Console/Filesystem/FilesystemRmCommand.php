<?php

namespace ConductorCore\Console\Filesystem;

use ConductorCore\Exception;
use ConductorCore\Filesystem\MountManager\MountManager;
use ConductorCore\MonologConsoleHandlerAwareTrait;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @todo    Add support for glob patterns?
 */
class FilesystemRmCommand extends Command
{
    use MonologConsoleHandlerAwareTrait;

    private MountManager $mountManager;
    private LoggerInterface $logger;

    public function __construct(
        MountManager     $mountManager,
        ?LoggerInterface $logger = null,
        ?string          $name = null
    ) {
        $this->mountManager = $mountManager;
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure(): void
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

    protected function execute(InputInterface $input, OutputInterface $output): int
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

            try {
                $this->deletePath($input, $filesystem, $path, $force);
            } catch (FilesystemException $e) {
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            throw new Exception\RuntimeException(sprintf(
                'An error occurred deleting paths: "%s".',
                implode('", "', $paths)
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @throws FilesystemException
     */
    private function deletePath(InputInterface $input, FilesystemOperator $filesystem, string $path): void
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
