<?php

namespace ConductorCore\Filesystem\MountManager\Plugin;

use ConductorCore\Exception;
use ConductorCore\Filesystem\MountManager\MountManager;
use ConductorCore\ForkManager;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class SyncPlugin implements SyncPluginInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    /**
     * @param string $from in the format {prefix}://{path}
     * @param string $to in the format {prefix}://{path}
     *
     * @return bool True if all operations succeeded; False if any operations failed
     * @throws FilesystemException
     */
    public function sync(
        MountManager $mountManager,
        string       $from,
        string       $to,
        array        $config = []
    ): bool {
        if (!$mountManager->has($from)) {
            throw new Exception\RuntimeException("Path \"$from\" does not exist.");
        }
        $isDir = $mountManager->directoryExists($from);

        $hasErrors = false;
        $fileExists = $mountManager->has($to);
        if (!$isDir) {
            $sourceIsNewer = false;
            if ($fileExists) {
                $sourceIsNewer = $mountManager->lastModified($from) > $mountManager->lastModified($to);
            }

            if (!$fileExists || $sourceIsNewer) {
                $result = $this->putFile($mountManager, $from, $to, $config);
                if (false === $result) {
                    $hasErrors = true;
                }
            }
        } else {
            $result = $this->syncDirectory($mountManager, $from, $to, $config);
            if (false === $result) {
                $hasErrors = true;
            }
        }

        return !$hasErrors;
    }

    /**
     * This method is the same as copy, except that it does a putStream rather than writeStream, allowing it to write
     * even if the file exists.
     */
    private function putFile(MountManager $mountManager, string $from, string $to, array $config): bool
    {
        $this->logger->debug("Copying file $from to $to");
        [$prefixFrom, $from] = $mountManager->getPrefixAndPath($from);
        $buffer = $mountManager->getFilesystem($prefixFrom)->readStream($from);

        if ($buffer === false) {
            $this->logger->error(sprintf(
                'Failed to open stream "%s://%s" for read.',
                $prefixFrom,
                $from
            ));

            return false;
        }

        [$prefixTo, $to] = $mountManager->getPrefixAndPath($to);

        $mountManager->getFilesystem($prefixTo)->writeStream($to, $buffer, $config);

        if (is_resource($buffer)) {
            fclose($buffer);
        }

        return true;
    }

    /**
     * @return bool True if all operations succeeded; False if any operations failed
     */
    private function syncDirectory(MountManager $mountManager, string $from, string $to, array $config): bool
    {
        $hasErrors = false;
        /**
         * @var DirectoryListing $filesToPush
         * @var DirectoryListing $filesToDelete
         */
        [$filesToPush, $filesToDelete] = $this->determineFilesToPushAndDelete($mountManager, $from, $to, $config);

        $result = $this->putFiles(
            $mountManager,
            $from,
            $to,
            $config,
            $filesToPush
        );
        if ($result === false) {
            $hasErrors = true;
        }

        if (!empty($config['delete'])) {
            $result = $this->deleteFiles($mountManager, $to, $filesToDelete, $config);
            if ($result === false) {
                $hasErrors = true;
            }
        }

        return !$hasErrors;
    }

    /**
     * @return DirectoryListing[]
     * @throws FilesystemException
     */
    private function determineFilesToPushAndDelete(
        MountManager $mountManager,
        string       $from,
        string       $to,
        array        $config
    ): array {
        $this->logger->info('Calculating file sync list');

        $delete = !empty($config['delete']);
        $excludes = !empty($config['excludes']) ? $config['excludes'] : [];
        $includes = !empty($config['includes']) ? $config['includes'] : [];

        [, $pathFrom] = $mountManager->getPrefixAndPath($from);
        [, $pathTo] = $mountManager->getPrefixAndPath($to);

        $sourceFiles = $this->applyExcludesAndIncludes(
            $mountManager,
            $pathFrom,
            $mountManager->listContents($from, true),
            $excludes,
            $includes
        );
        $filesToPush = $filesToDelete = null;

        if (!$mountManager->has($to)) {
            $filesToPush = $sourceFiles;
        } else {

            $this->logger->debug('Comparing source and destination to determine file operations needed...');
            $destinationFiles = $this->applyExcludesAndIncludes(
                $mountManager,
                $pathTo,
                $mountManager->listContents($to, true),
                $excludes,
                $includes
            );

            $indexedDestinationFiles = [];
            $tempDestinationFiles = $destinationFiles->toArray();
            $destinationFiles = new DirectoryListing($tempDestinationFiles);
            foreach ($tempDestinationFiles as $tempDestinationFile) {
                [, $path] = $mountManager->getPrefixAndPath($tempDestinationFile['path']);
                $relativePath = substr($path, strlen($pathTo) + 1);
                $indexedDestinationFiles[$relativePath] = [
                    'lastModified' => $tempDestinationFile['lastModified'],
                ];
            }
            unset($tempDestinationFiles);

            $indexedSourceFiles = [];
            $tempSourceFiles = $sourceFiles->toArray();
            foreach ($tempSourceFiles as $tempSourceFile) {
                [, $path] = $mountManager->getPrefixAndPath($tempSourceFile['path']);
                $relativePath = substr($path, strlen($pathFrom) + 1);
                $indexedSourceFiles[$relativePath] = [
                    'lastModified' => $tempSourceFile['lastModified'],
                ];
            }
            $sourceFiles = new DirectoryListing($tempSourceFiles);

            $filesToPush = $sourceFiles;
            $filesToPush = $filesToPush->filter(function (StorageAttributes $attributes) use ($mountManager, $pathFrom, $config, $indexedDestinationFiles) {
                [, $path] = $mountManager->getPrefixAndPath($attributes->path());
                $relativePath = substr($path, strlen($pathFrom) + 1);
                if (!isset($indexedDestinationFiles[$relativePath])) {
                    return true;
                }

                if (!empty($config['ignore_timestamps'])) {
                    return true;
                }

                if ($attributes->isFile() && $attributes->lastModified() >= $indexedDestinationFiles[$relativePath]['lastModified']) {
                    return true;
                }

                return false;
            });

            if ($delete) {
                $filesToDelete = $destinationFiles->filter(function (StorageAttributes $attributes) use ($mountManager, $pathTo, $indexedSourceFiles) {
                    [, $path] = $mountManager->getPrefixAndPath($attributes->path());
                    $relativePath = substr($path, strlen($pathTo) + 1);
                    if (!isset($indexedSourceFiles[$relativePath])) {
                        return true;
                    }

                    return false;
                });
            }
        }


        $this->logger->debug('Removing implicit files from sync list');
        $filesToPush = $this->removeImplicitDirectoriesForPush($filesToPush);
        if ($delete && $filesToDelete) {
            $filesToDelete = $this->removeImplicitFilesForDelete($filesToDelete);
        }

        return [$filesToPush, $filesToDelete];
    }

    private function applyExcludesAndIncludes(
        MountManager     $mountManager,
        string           $basePath,
        DirectoryListing $files,
        array            $excludes = [],
        array            $includes = []
    ): DirectoryListing {
        if (!$excludes) {
            return $files;
        }

        return $files->filter(function ($file) use ($mountManager, $basePath, $includes, $excludes) {
            [, $path] = $mountManager->getPrefixAndPath($file->path());
            $relativePath = substr($path, strlen($basePath) + 1);
            return !($this->isMatch($relativePath, $excludes)
                && !$this->isMatch(
                    $relativePath,
                    $includes
                ));
        });
    }

    /**
     * @param array $rsyncPatterns Array of match patterns in rsync exclude/include format
     * @see https://linux.die.net/man/1/rsync at "Include/Exclude Pattern Rules"
     */
    private function isMatch(string $file, array $rsyncPatterns): bool
    {
        $regexPatterns = [];
        foreach ($rsyncPatterns as $rsyncPattern) {
            $pattern = str_replace('\*', '*', preg_quote($rsyncPattern, '%'));
            $pattern = preg_replace('%([^*])$%', '$1(/|$)', $pattern);
            $pattern = preg_replace('%^([^*/])%', '(^|/)$1', $pattern);
            $pattern = preg_replace('%^/%', '^', $pattern);
            $pattern = str_replace('*', '.*', $pattern);
            $pattern = "%$pattern%";
            $regexPatterns[] = $pattern;
        }

        foreach ($regexPatterns as $pattern) {
            if (preg_match($pattern, $file)) {
                return true;
            }
        }

        return false;
    }

    private function removeImplicitDirectoriesForPush(DirectoryListing $filesToPush): DirectoryListing
    {
        $tempFilesToPush = $filesToPush->toArray();
        $implicitDirectories = [];
        foreach ($tempFilesToPush as $file) {
            $directories = explode('/', $file->path());
            do {
                array_pop($directories);
                if ($directories) {
                    $implicitDirectory = implode('/', $directories);
                    if (isset($implicitDirectories[$implicitDirectory])) {
                        break;
                    }
                    $implicitDirectories[$implicitDirectory] = true;
                }
            } while ($directories);
        }
        $filesToPush = new DirectoryListing($tempFilesToPush);

        return $filesToPush->filter(fn(StorageAttributes $attributes) => !($attributes->isDir() && isset($implicitDirectories[$attributes->path()]))
        );
    }

    private function removeImplicitFilesForDelete(DirectoryListing $filesToDelete): DirectoryListing
    {
        $tempFilesToDelete = $filesToDelete->toArray();
        $filesToDelete = new DirectoryListing($tempFilesToDelete);
        $directories = [];
        foreach ($tempFilesToDelete as $file) {
            if ($file->isDir()) {
                $directories[$file->path()] = true;
            }
        }
        if (!$directories) {
            return $filesToDelete;
        }
        $directories = array_keys($directories);
        return $filesToDelete->filter(function (StorageAttributes $attributes) use ($directories) {
            foreach ($directories as $directory) {
                if ($attributes->path() != $directory && 0 === strpos($attributes->path(), $directory)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * @return bool True if all operations succeeded; False if any operations failed
     */
    private function putFiles(
        MountManager     $mountManager,
        string           $from,
        string           $to,
        array            $config,
        DirectoryListing $filesToPush
    ): bool {
        $hasErrors = false;
        [$prefixFrom, $pathFrom] = $mountManager->getPrefixAndPath($from);
        $pathFrom = trim($pathFrom, '/');
        [$prefixTo, $pathTo] = $mountManager->getPrefixAndPath($to);
        $pathTo = trim($pathTo, '/');
        $destinationFilesystem = $mountManager->getFilesystem($prefixTo);

        $batchSize = !empty($config['batch_size']) ? $config['batch_size'] : 100;
        $maxConcurrency = !empty($config['max_concurrency']) ? $config['max_concurrency'] : 10;

        $this->logger->info(
            sprintf(
                'Copying files in batches of %s',
                number_format($batchSize)
            )
        );

        /**
         * Skip directory creation for AWS S3 because directories have no direct meaning and are just prefixes for other
         * objects. The number of calls to create a dir can be large even when a dir has already been synced. S3 does
         * not detect whether the dir needs to be created or not correctly.
         *
         * @todo Review this. A special case for an adapter is not a great way to handle this.
         */
        $skipDirectorCreation = false;
        try {
            $reflection = new \ReflectionClass($destinationFilesystem);
            $adapterProperty = $reflection->getProperty('adapter');
            $adapterProperty->setAccessible(true);
            $adapter = $adapterProperty->getValue($destinationFilesystem);

            if ($adapter instanceof \League\Flysystem\AwsS3V3\AwsS3V3Adapter) {
                $skipDirectorCreation = true;
            }
        } catch (\Exception $e) {
            // If we can't determine the adapter type, assume it's not S3
            $this->logger->debug("Could not determine filesystem adapter type: " . $e->getMessage());
            return false;
        }

        $batchNumber = 1;
        $fileNumber = 0;
        /** @var FileAttributes $file */
        foreach ($filesToPush as $file) {
            if ($fileNumber % $batchSize == 0) {
                $this->logger->info(sprintf(
                    'Processing copy batch %s',
                    number_format($batchNumber)
                ));

                $forkManager = new ForkManager($this->logger);
                $forkManager->setMaxConcurrency($maxConcurrency);
            }

            $executor = function () use (
                $mountManager,
                $destinationFilesystem,
                $prefixFrom,
                $pathFrom,
                $prefixTo,
                $pathTo,
                $config,
                $file,
                $skipDirectorCreation
            ) {
                $from = $file->path();
                $to = "$prefixTo://$pathTo" . substr($file->path(), strlen("$prefixFrom://$pathFrom"));
                if (!$file->isDir()) {
                    $this->logger->debug("Copying file $from to $to");
                    $mountManager->copy($from, $to, $config);
                } elseif (!$skipDirectorCreation) {
                    $this->logger->debug("Creating directory $to");
                    $destinationFilesystem->createDirectory($pathTo);
                }
            };

            $forkManager->addWorker($executor);
            $fileNumber++;

            if ($fileNumber % $batchSize === 0) {
                try {
                    $forkManager->execute();
                    unset($forkManager);
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                    $hasErrors = true;
                }
            }
        }

        if (isset($forkManager)) {
            try {
                $forkManager->execute();
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                $hasErrors = true;
            }
        }

        return !$hasErrors;
    }

    /**
     * @return bool True if all operations succeeded; False if any operations failed
     */
    private function deleteFiles(MountManager $mountManager, string $to, DirectoryListing $filesToDelete, array $config): bool
    {
        $hasErrors = false;
        [$prefixTo,] = $mountManager->getPrefixAndPath($to);
        $destinationFilesystem = $mountManager->getFilesystem($prefixTo);

        $batchSize = !empty($config['batch_size']) ? $config['batch_size'] : 100;
        $maxConcurrency = !empty($config['max_concurrency']) ? $config['max_concurrency'] : 10;

        $this->logger->info(
            sprintf(
                'Deleting files in batches of %s',
                number_format($batchSize)
            )
        );

        $batchNumber = 1;
        $fileNumber = 0;
        /** @var FileAttributes $file */
        foreach ($filesToDelete as $file) {
            if ($fileNumber % $batchSize == 0) {
                $this->logger->info(sprintf(
                    'Processing copy batch %s',
                    number_format($batchNumber)
                ));

                $forkManager = new ForkManager($this->logger);
                $forkManager->setMaxConcurrency($maxConcurrency);
            }

            $executor = function () use (
                $mountManager,
                $destinationFilesystem,
                $file
            ) {
                [, $pathTo] = $mountManager->getPrefixAndPath($file->path());
                if ($file->isDir()) {
                    $this->logger->debug("Deleting directory {$file->path()}");
                    $destinationFilesystem->deleteDirectory($pathTo);
                } else {
                    $this->logger->debug("Deleting file {$file->path()}");
                    $destinationFilesystem->delete($pathTo);
                }
            };

            $forkManager->addWorker($executor);
            $fileNumber++;

            if ($fileNumber % $batchSize === 0) {
                try {
                    $forkManager->execute();
                    unset($forkManager);
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                    $hasErrors = true;
                }
            }
        }

        if (isset($forkManager)) {
            try {
                $forkManager->execute();
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                $hasErrors = true;
            }
        }

        return !$hasErrors;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
