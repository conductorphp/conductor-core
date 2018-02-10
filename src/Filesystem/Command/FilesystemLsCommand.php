<?php

namespace ConductorCore\Filesystem\Command;

use ConductorCore\Filesystem\MountManager\MountManager;
use ConductorCore\MonologConsoleHandlerAwareTrait;
use League\Flysystem\FilesystemInterface;
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
        $this->setName('filesystem:ls')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                "Path in the format {adapter}://{path}.\nAvailable Adapters: <comment>" . implode(
                    ', ',
                    $filesystemAdapterNames
                ) . '</comment>'
            )
            ->addOption('recursive', null, InputOption::VALUE_NONE, 'List directory recursively.')
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

        $tableOutput = new Table($output);
        $tableOutput->setHeaders(['Path', 'Type', 'Size', 'Last Updated']);

        $path = $input->getArgument('path');
        list($prefix,) = $this->mountManager->filterPrefix([$path]);
        $path = trim(substr($path, strlen($prefix) + 3), '/');
        $filesystem = $this->mountManager->getFilesystem($prefix);

        if ('.' !== $path && !$filesystem->has($path)) {
            $this->logger->notice("Path \"$path\" does not exist.");
            return 0;
        }

        $metaData = $this->getFileMetadata($filesystem, $path);
        $basePath = $path;
        if ('file' == $metaData['type']) {
            if (false !== strpos($path, '/')) {
                $basePath = preg_replace('%(.*)/[^/]+%', '$1', $path);
            }
        }

        $this->appendOutputRow($tableOutput, $metaData, $basePath);
        if ('dir' == $metaData['type']) {
            foreach ($filesystem->listContents($path, $input->getOption('recursive')) as $file) {
                $metaData = $this->getFileMetadata($filesystem, $file['path']);
                $this->appendOutputRow($tableOutput, $metaData, $basePath);
            }
        }

        $tableOutput->render();
        return 0;
    }

    /**
     * @param FilesystemInterface $filesystem
     * @param string $path
     *
     * @return array
     */
    private function getFileMetadata($filesystem, $path)
    {
        if ('.' !== $path) {
            $metaData = $filesystem->getMetadata($path);
        }

        if (empty($metaData)) {
            // Some filesystems will report no metadata for directories, because they only exist in paths
            // of objects, but are not actually directories themselves. For example, in AWS S3 buckets.
            $metaData = [
                'path' => $path,
                'type' => 'dir',
            ];
        }
        return $metaData;
    }

    /**
     * @param Table $tableOutput
     * @param array $metaData
     * @param string|null $basePath
     */
    protected function appendOutputRow(Table $tableOutput, $metaData, $basePath = null)
    {
        $path = empty($basePath) || '.' == $basePath ? $metaData['path'] : substr($metaData['path'], strlen($basePath) + 1);
        if (empty($path)) {
            if ('file' == $metaData['type']) {
                $path = $metaData['path'];
            } else {
                $path = '.';
            }
        }
        if (isset($metaData['size'])) {
            $size = $this->humanFileSize($metaData['size']);
        } else {
            $size = '';
        }

        $tableOutput->addRow(
            [
                $path,
                isset($metaData['type']) ? $metaData['type'] : 'dir',
                $size,
                isset($metaData['timestamp']) ? date('Y-m-d H:i:s T', $metaData['timestamp']) : '',
            ]
        );
    }

    private function humanFileSize($size)
    {
        if ($size >= 1 << 30) {
            return number_format($size / (1 << 30), 1) . "G";
        }

        if ($size >= 1 << 20) {
            return number_format($size / (1 << 20), 1) . "M";
        }
        if ($size >= 1 << 10) {
            return number_format($size / (1 << 10), 1) . "K";
        }

        return $size;
    }

}
