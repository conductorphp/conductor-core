<?php

namespace ConductorCore\Console\Filesystem;

use ConductorCore\Exception;
use ConductorCore\Filesystem\MountManager\MountManager;
use ConductorCore\MonologConsoleHandlerAwareTrait;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @todo    Add support for glob patterns?
 */
class FilesystemLsCommand extends Command
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
     * @throws FilesystemException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->injectOutputIntoLogger($output, $this->logger);
        $this->mountManager->setWorkingDirectory(getcwd());

        $tableOutput = new Table($output);
        $tableOutput->setHeaders(['Path', 'Type', 'Size', 'Last Updated']);

        $path = $input->getArgument('path');
        [$prefix, $arguments] = $this->mountManager->filterPrefix([$path]);
        $path = $arguments[0];

        $filesystem = $this->mountManager->getFilesystem($prefix);
        $isRoot = ltrim($path, '/.') === '';

        if (!$isRoot && !$filesystem->has($path)) {
            throw new Exception\RuntimeException("Path \"$path\" does not exist.");
        }
        $isDirectory = $isRoot || $filesystem->directoryExists($path);
        $metaData = [
            'path' => $path,
            'type' => $isDirectory ? 'dir' : 'file',
            'size' => $isRoot || $isDirectory ? 0 : $filesystem->fileSize($path),
            'lastModified' => $isRoot || $isDirectory ? null : $filesystem->lastModified($path),
        ];

        $metaData = $this->normalizeMetadata($metaData, $path);
        $this->appendOutputRow($tableOutput, $metaData);

        if ($isDirectory) {
            $contents = $filesystem->listContents($path, $input->getOption('recursive'));
            // @todo Make these checks asynchronous
            foreach ($contents as $file) {
                $isDirectory = $file->isDir();
                $metaData = [
                    'path' => $file->path(),
                    'type' => $isDirectory ? 'dir' : 'file',
                    'size' => $isDirectory ? 0 : $filesystem->fileSize($file->path()),
                    'lastModified' => $isDirectory ? null : $filesystem->lastModified($file->path()),
                ];
                $metaData = $this->normalizeMetadata($metaData, $path);
                $this->appendOutputRow($tableOutput, $metaData);
            }
        }

        $tableOutput->render();
        return self::SUCCESS;
    }

    private function normalizeMetadata(array $metaData, string $basePath): array
    {
        if (empty($basePath)) {
            $basePath = '.';
        } else {
            $basePath = rtrim($basePath, '/.');
        }

        if (empty($metaData['path'])) {
            $metaData['path'] = '.';
        }

        if (empty($metaData['type'])) {
            $metaData['type'] = 'dir';
        }

        if ($metaData['path'] === $basePath) {
            if ('file' === $metaData['type']) {
                $parts = explode('/', $metaData['path']);
                $metaData['path'] = array_pop($parts);
            } else {
                $metaData['path'] = '.';
            }
        } elseif ('.' !== $basePath) {
            $metaData['path'] = substr($metaData['path'], strlen($basePath) + 1);
        }

        if (isset($metaData['size'])) {
            $metaData['size'] = $this->humanFileSize($metaData['size']);
        } else {
            $metaData['size'] = '';
        }

        return $metaData;
    }

    private function humanFileSize(int $size): string
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

    private function appendOutputRow(Table $tableOutput, array $metaData): void
    {
        $tableOutput->addRow(
            [
                $metaData['path'],
                    $metaData['type'] ?? 'dir',
                $metaData['size'],
                isset($metaData['lastModified']) ? date('Y-m-d H:i:s T', $metaData['lastModified']) : '',
            ]
        );
    }

}
