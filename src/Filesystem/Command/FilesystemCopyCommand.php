<?php

namespace DevopsToolCore\Filesystem\Command;

use DevopsToolCore\Exception;
use DevopsToolCore\Filesystem\Filesystem;
use DevopsToolCore\Filesystem\FilesystemAdapterProvider;
use DevopsToolCore\MonologConsoleHandlerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FilesystemCopyCommand extends Command
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
        $filesystemAdapterNames = $this->filesystemAdapterProvider->getAdapterNames();
        $this->setName('filesystem:copy')
            ->addArgument(
                'source_adapter',
                InputArgument::REQUIRED,
                "Name of adapter to copy from.\nAvailable Adapters: <comment>" . implode(', ', $filesystemAdapterNames)
                . '</comment>'
            )
            ->addArgument('source_file', InputArgument::REQUIRED, 'Source file to copy from.')
            ->addArgument(
                'destination_adapter',
                InputArgument::REQUIRED,
                "Name of adapter to copy to.\nAvailable Adapters: <comment>" . implode(', ', $filesystemAdapterNames)
                . '</comment>'
            )
            ->addArgument('destination_file', InputArgument::REQUIRED, 'Destination file to copy to.')
            ->setDescription(
                'Copy a single file from a source filesystem directory to a destination filesystem directory.'
            )
            ->setHelp(
                "This command copies a single file from a source filesystem directory to a destination filesystem directory."
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
        $sourceFile = $input->getArgument('source_file');
        $destinationFilesystemAdapter = $this->filesystemAdapterProvider->get(
            $input->getArgument('destination_adapter')
        );
        $destinationFile = $input->getArgument('destination_file');

        $sourceFilesystem = new Filesystem($sourceFilesystemAdapter);
        $destinationFilesystem = new Filesystem($destinationFilesystemAdapter);

        $stream = $sourceFilesystem->readStream($sourceFile);

        if (!$stream) {
            throw new Exception\RuntimeException('Unable to read source file "' . $sourceFile . '".');
        }

        if (!$destinationFilesystem->writeStream($destinationFile, $stream)) {
            throw new Exception\RuntimeException('Error writing to destination file "' . $destinationFile . '".');
        }

        return 0;
    }

}
