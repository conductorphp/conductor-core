<?php

namespace ConductorCore\Filesystem\Command;

use ConductorCore\Exception;
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

        $metaData = $this->normalizeMetadata($this->getFilesystemMetadata($filesystem, $path), $path);
        $isFile = ('file' == $metaData['type']);

        if ($isFile) {
            $hasFile = true;
            $contents = null;
        } else {
            $hasFile = $filesystem->has($path);
            $contents = $filesystem->listContents($path, $input->getOption('recursive'));
        }

        if (!($hasFile || $contents)) {
            if (!$hasFile && false !== strpos($path, '/')) {
                $parentPath = preg_replace('%(.+)/[^/]+%', '$1', $path);
                $parentContents = $filesystem->listContents($parentPath);
                if ($parentContents) {
                    foreach ($parentContents as $parentContent) {
                        if ($path == $parentContent['path']) {
                            $tableOutput->render();
                            return 0;
                        }
                    }
                }
            }
            throw new Exception\RuntimeException("Path \"$path\" does not exist.");
        }

        $this->appendOutputRow($tableOutput, $metaData);
        if ($contents) {
            foreach ($contents as $file) {
                $metaData = $this->normalizeMetadata($this->getFilesystemMetadata($filesystem, $file['path']), $path);
                $this->appendOutputRow($tableOutput, $metaData);
            }
        }

        $tableOutput->render();
        return 0;
    }

    /**
     * @param Table $tableOutput
     * @param array $metaData
     */
    private function appendOutputRow(Table $tableOutput, array $metaData): void
    {
        $tableOutput->addRow(
            [
                $metaData['path'],
                isset($metaData['type']) ? $metaData['type'] : 'dir',
                $metaData['size'],
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

    /**
     * @param array $metaData
     * @param string $basePath
     *
     * @return array
     */
    private function normalizeMetadata(array $metaData, string $basePath): array
    {
        if (!empty($basePath) && '.' != $basePath) {
            $metaData['path'] = substr($metaData['path'], strlen($basePath) + 1);
        }

        if (empty($metaData['path'])) {
            $metaData['path'] = '.';
        }

        if (empty($metaData['type'])) {
            $metaData['type'] = 'dir';
        }

        if (isset($metaData['size'])) {
            $metaData['size'] = $this->humanFileSize($metaData['size']);
        } else {
            $metaData['size'] = '';
        }

        return $metaData;
    }

    /**
     * @param FilesystemInterface $filesystem
     * @param string $path
     *
     * @return array
     */
    private function getFilesystemMetadata(FilesystemInterface $filesystem, string $path): array
    {
        // Get metadata. Some file adapters will return info for dirs, some will return false, and some will
        // throw an exception
        try {
            $metaData = $filesystem->getMetadata($path) ?? [];
        } catch (\Exception $e) {
            // Do nothing
        }

        if (empty($metaData)) {
            $metaData = [
                'type' => 'dir',
                'path' => $path,
            ];
        }
        return $metaData;
    }

}
