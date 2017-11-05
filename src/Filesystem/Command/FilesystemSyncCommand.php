<?php

namespace DevopsToolCore\Filesystem\Command;

use DevopsToolCore\Exception;
use DevopsToolCore\Filesystem\Filesystem;
use DevopsToolCore\Filesystem\FilesystemAdapterProvider;
use DevopsToolCore\MonologConsoleHandlerAwareTrait;
use Emgag\Flysystem\Hash\HashPlugin;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FilesystemSyncCommand extends Command
{
    use MonologConsoleHandlerAwareTrait;

    /**
     * @var FilesystemAdapterProvider
     */
    private $filesystemAdapterProvider;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * DatabaseExportCommand constructor.
     *
     * @param FilesystemAdapterProvider $filesystemAdapterProvider
     * @param LoggerInterface|null      $logger
     * @param string|null               $name
     */
    public function __construct(
        FilesystemAdapterProvider $filesystemAdapterProvider,
        LoggerInterface $logger = null,
        $name = null
    ) {
        $this->filesystemAdapterProvider = $filesystemAdapterProvider;
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
        // @todo Add option for excludes and option for includes
        // @todo Add option for whether to copy over permissions
        // @todo Add option for whether to copy over timestamps
        $filesystemAdapterNames = $this->filesystemAdapterProvider->getAdapterNames();
        $this->setName('filesystem:sync')
            ->addArgument(
                'source_adapter',
                InputArgument::REQUIRED,
                "Name of adapter to copy from.\nAvailable Adapters: <comment>" . implode(', ', $filesystemAdapterNames)
                . '</comment>'
            )
            ->addArgument('source_directory', InputArgument::REQUIRED, 'Source directory to copy from.')
            ->addArgument(
                'destination_adapter',
                InputArgument::REQUIRED,
                "Name of adapter to copy to.\nAvailable Adapters: <comment>" . implode(', ', $filesystemAdapterNames)
                . '</comment>'
            )
            ->addArgument('destination_directory', InputArgument::REQUIRED, 'Destination directory to copy to.')
            ->setDescription(
                'Copy a directory from a source filesystem directory to a destination filesystem directory.'
            )
            ->setHelp(
                "This command copies a directory from a source filesystem directory to a destination filesystem directory."
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->injectOutputIntoLogger($output, $this->logger);
        $sourceFilesystemAdapter = $this->filesystemAdapterProvider->get($input->getArgument('source_adapter'));
        $sourceDirectory = rtrim($input->getArgument('source_directory'), '/');
        $destinationFilesystemAdapter = $this->filesystemAdapterProvider->get(
            $input->getArgument('destination_adapter')
        );
        $destinationDirectory = rtrim($input->getArgument('destination_directory'), '/');

        $sourceFilesystem = new Filesystem($sourceFilesystemAdapter);
        $sourceFilesystem->addPlugin(new HashPlugin);
        $destinationFilesystem = new Filesystem($destinationFilesystemAdapter);
        $destinationFilesystem->addPlugin(new HashPlugin);

        $files = $sourceFilesystem->listContents($sourceDirectory, true);
        foreach ($files as $file) {
            $destinationFilename = "$destinationDirectory/${file['basename']}";
            if ($file['type'] == 'dir') {
                if (!$destinationFilesystem->has($destinationFilename)) {
                    $destinationFilesystem->createDir($destinationFilename);
                    $this->logger->debug('Created directory "' . $destinationFilename . '".');
                }
                continue;
            }

            $sourceFilename = $file['path'];
            if ($destinationFilesystem->has($destinationFilename)) {
                $sourceTimestamp = $sourceFilesystem->getTimestamp($sourceFilename);
                $destinationTimestamp = $destinationFilesystem->getTimestamp($destinationFilename);

                if ($sourceTimestamp == $destinationTimestamp) {
                    $this->logger->debug('Skipped file "' . $sourceFilename . '"; timestamps match.');
                    continue;
                }

                $sourceHash = $sourceFilesystem->hash($sourceFilename);
                $destinationHash = $destinationFilesystem->hash($destinationFilename);
                if ($sourceHash == $destinationHash) {
                    $this->logger->debug('Skipped file "' . $sourceFilename . '"; File hashes match.');
                    continue;
                }
            }

            $stream = $sourceFilesystem->readStream($file['path']);
            // @todo Add option to set timestamp on destination file to match that of source file
            // @todo Add option to set permissions on destination file to match that of source file
            if (!$destinationFilesystem->putStream($destinationFilename, $stream)) {
                throw new Exception\RuntimeException(
                    'Error writing to destination file "' . $destinationFilename . '".'
                );
            }
            $this->logger->debug('Copied file "' . $sourceFilename . '" to "' . $destinationFilename . '".');
        }

        return 0;
    }

}
