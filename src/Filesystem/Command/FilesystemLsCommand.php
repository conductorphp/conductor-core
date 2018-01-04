<?php

namespace DevopsToolCore\Filesystem\Command;

use DevopsToolCore\Filesystem\FilesystemAdapterManager;
use DevopsToolCore\MonologConsoleHandlerAwareTrait;
use League\Flysystem\AdapterInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FilesystemLsCommand extends Command
{
    use MonologConsoleHandlerAwareTrait;

    /**
     * @var AdapterInterface
     */
    private $filesystemAdapter;
    /**
     * @var FilesystemAdapterManager
     */
    private $filesystemManager;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * DatabaseExportCommand constructor.
     *
     * @param FilesystemAdapterManager $filesystemManager
     * @param LoggerInterface|null     $logger
     * @param string|null              $name
     */
    public function __construct(
        FilesystemAdapterManager $filesystemManager,
        LoggerInterface $logger = null,
        $name = null
    ) {
        $this->filesystemManager = $filesystemManager;
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
        $filesystemAdapterNames = $this->filesystemManager->getAdapterNames();
        $this->setName('filesystem:ls')
            ->addArgument(
                'adapter',
                InputArgument::REQUIRED,
                "Name of adapter to use.\nAvailable Adapters: <comment>" . implode(', ', $filesystemAdapterNames) . '</comment>'
            )
            ->addArgument('directory', InputArgument::REQUIRED, 'Directory to list.')
            ->addOption('recursive', null,InputOption::VALUE_NONE, 'List directory recursively.')
            ->setDescription('List a directory on a filesystem.')
            ->setHelp("This command list a directory on a filesystem.");
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
        $this->filesystemAdapter = $this->filesystemManager->getAdapter($input->getArgument('adapter'));

        $tableOutput = new Table($output);
        $tableOutput->setHeaders(['Path', 'Type', 'Size', 'Last Updated']);

        $directory = rtrim($input->getArgument('directory'), '/');
        $directoryIsEmpty = true;
        foreach ($this->filesystemAdapter->listContents($directory, $input->getOption('recursive')) as $file) {
            $path = trim(substr($file['path'], strlen($directory)), '/');
            if (!$path) {
                continue;
            }
            $directoryIsEmpty = false;
            $metaData = $this->filesystemAdapter->getMetadata($file['path']);

            $tableOutput->addRow([
                $path,
                !empty($metaData['type']) ? $metaData['type'] : 'dir',
                !empty($metaData['size']) ? $metaData['size'] : '',
                !empty($file['timestamp']) ? date('Y-m-d H:i:s T', $file['timestamp']) : '',
            ]);
        }

        if ($directoryIsEmpty) {
            $this->logger->notice("Directory \"$directory\" is empty.");
        } else {
            $tableOutput->render();
        }
        return 0;
    }

}
