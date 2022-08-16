<?php

namespace ConductorCore\Filesystem\MountManager\Plugin;

use ConductorCore\Exception;
use ConductorCore\Filesystem\MountManager\MountManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ConductorCore\ForkManager;

class SyncPlugin implements SyncPluginInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * SyncPlugin constructor.
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    /**
     * @param string $from in the format {prefix}://{path}
     * @param string $to   in the format {prefix}://{path}
     * @param array  $config
     *
     * @return bool True if all operations succeeded; False if any operations failed
     */
    public function sync(MountManager $mountManager, string $from, string $to, array $config = []): bool
    {
        if (!$mountManager->has($from)) {
            throw new Exception\RuntimeException("Path \"$from\" does not exist.");
        }

        $hasErrors = false;
        $metadata = $mountManager->getMetadata($from);
        $fileExists = $mountManager->has($to);
        if ($metadata && 'file' == $metadata['type']) {
            $sourceIsNewer = false;
            if ($fileExists) {
                $toMetadata = $mountManager->getMetadata($to);
                $sourceIsNewer = $toMetadata && $metadata['timestamp'] > $toMetadata['timestamp'];
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
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * This method is the same as copy, except that it does a putStream rather than writeStream, allowing it to write
     * even if the file exists.
     *
     * @param MountManager $mountManager
     * @param string       $from
     * @param string       $to
     * @param array        $config
     *
     * @return bool
     */
    private function putFile(MountManager $mountManager, string $from, string $to, array $config): bool
    {
        $this->logger->debug("Copying file $from to $to");
        list($prefixFrom, $from) = $mountManager->getPrefixAndPath($from);
        $buffer = $mountManager->getFilesystem($prefixFrom)->readStream($from);

        if ($buffer === false) {
            $this->logger->error(sprintf(
                'Failed to open stream "%s://%s" for read.',
                $prefixFrom,
                $from
            ));

            return false;
        }

        list($prefixTo, $to) = $mountManager->getPrefixAndPath($to);

        $result = $mountManager->getFilesystem($prefixTo)->putStream($to, $buffer, $config);

        if (is_resource($buffer)) {
            fclose($buffer);
        }

        return $result;
    }

    /**
     * @param MountManager $mountManager
     * @param string       $from
     * @param string       $to
     * @param array        $config
     * @return bool True if all operations succeeded; False if any operations failed
     */
    private function syncDirectory(MountManager $mountManager, string $from, string $to, array $config): bool
    {
        $hasErrors = false;
        list($filesToPush, $filesToDelete) = $this->determineFilesToPushAndDelete($mountManager, $from, $to, $config);

        $batchSize = !empty($config['batch_size']) ? $config['batch_size'] : 100;
        if ($filesToPush) {
            $numBatches = ceil(count($filesToPush) / $batchSize);
            $this->logger->info(
                sprintf(
                    'Copying %s file(s) in %s %s of %s',
                    number_format(count($filesToPush)),
                    number_format($numBatches),
                    ($numBatches == 1) ? 'batch' : 'batches',
                    number_format($batchSize)
                )
            );
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
        } else {
            $this->logger->info('No files to copy');
        }

        if (!empty($config['delete'])) {
            if ($filesToDelete) {
                $numBatches = ceil(count($filesToDelete) / $batchSize);
                $this->logger->info(
                    sprintf(
                        'Deleting %s file(s) in %s %s of %s',
                        number_format(count($filesToDelete)),
                        number_format($numBatches),
                        ($numBatches == 1) ? 'batch' : 'batches',
                        number_format($batchSize)
                    )
                );
                $result = $this->deleteFiles($mountManager, $to, $filesToDelete, $config);
                if ($result === false) {
                    $hasErrors = true;
                }
            } else {
                $this->logger->info('No files to delete');
            }
        }

        return !$hasErrors;
    }


    /**
     * @param array $filesToPush
     *
     * @return array
     */
    private function removeImplicitDirectoriesForPush(array $filesToPush): array
    {
        $implicitDirectories = [];
        foreach ($filesToPush as $file) {
            if (false === strpos($file['relative_path'], '/')) {
                continue;
            }

            $directories = explode('/', $file['relative_path']);
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

        foreach ($filesToPush as $key => $file) {
            // If this file is an implicit directory, there is no need to push it
            if ('dir' == $file['type'] && isset($implicitDirectories[$file['relative_path']])) {
                unset($filesToPush[$key]);
                continue;
            }
        }
        return $filesToPush;
    }

    /**
     * @param array $filesToDelete
     *
     * @return array
     */
    private function removeImplicitFilesForDelete(array $filesToDelete): array
    {
        $directories = [];
        foreach ($filesToDelete as $file) {
            if ('dir' == $file['type']) {
                $directories[$file['relative_path']] = true;
            }
        }

        if (!$directories) {
            return $filesToDelete;
        }

        $directories = array_keys($directories);
        foreach ($filesToDelete as $key => $file) {
            foreach ($directories as $directory) {
                if ($file['relative_path'] != $directory && 0 === strpos($file['relative_path'], $directory)) {
                    unset($filesToDelete[$key]);
                    break;
                }
            }
        }

        return $filesToDelete;
    }

    /**
     * @param array $files
     * @param array $excludes
     * @param array $includes
     *
     * @return array
     */
    private function applyExcludesAndIncludes(array $files, array $excludes = [], array $includes = []): array
    {
        if (!$excludes) {
            return $files;
        }

        foreach ($files as $key => $file) {
            if ($this->isMatch($file['relative_path'], $excludes)
                && !$this->isMatch(
                    $file['relative_path'],
                    $includes
                )) {
                unset($files[$key]);
            }
        }

        // Reindex to avoid confusion when comparing last index to count
        return array_values($files);
    }

    /**
     * @param string $file
     * @param array  $rsyncPatterns Array of match patterns in rsync exclude/include format
     * @see https://linux.die.net/man/1/rsync at "Include/Exclude Pattern Rules"
     *
     * @return bool
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

    /**
     * @param MountManager $mountManager
     * @param string       $from
     * @param string       $to
     * @param array        $config
     *
     * @return array
     */
    private function determineFilesToPushAndDelete(
        MountManager $mountManager,
        string $from,
        string $to,
        array $config
    ): array {
        $this->logger->info('Calculating file sync list');

        $delete = !empty($config['delete']);
        $excludes = !empty($config['excludes']) ? $config['excludes'] : [];
        $includes = !empty($config['includes']) ? $config['includes'] : [];

        $filesToPush = $filesToDelete = [];
        list(, $pathTo) = $mountManager->getPrefixAndPath($to);
        $pathTo = trim($pathTo, '/');

        // @todo Remove some of these debug statements
        $this->logger->debug('Retrieving source file list');
        $sourceFiles = $this->applyExcludesAndIncludes($mountManager->listContents($from, true), $excludes, $includes);

        if ('.' != $pathTo && !$mountManager->has($to)) {
            // Source files should all be pushed since destination does not exist
            $filesToPush = $sourceFiles;
        } else {
            if (!$sourceFiles) {
                if ($delete) {
                    $this->logger->debug('Retrieving destination file list');
                    // no need to go recursive if we are deleting all, unless there are excludes
                    if ($excludes) {
                        $destinationFiles = $this->applyExcludesAndIncludes(
                            $mountManager->listContents($to, true),
                            $excludes,
                            $includes
                        );
                    } else {
                        $destinationFiles = $mountManager->listContents($to);
                    }
                    $filesToDelete = $destinationFiles;
                }
            } else {
                $this->logger->debug('Retrieving destination file list');
                $destinationFiles = $this->applyExcludesAndIncludes(
                    $mountManager->listContents($to, true),
                    $excludes,
                    $includes
                );

                if (!$destinationFiles) {
                    // Source files should all be pushed since destination has no files
                    $filesToPush = $sourceFiles;
                } else {
                    // Files exist on source and destination. We must compare them now to sync

                    $this->logger->debug('Determining files to push');
                    // Index by relative path so that we can more easily do comparisons
                    $sourceFiles = array_combine(array_column($sourceFiles, 'relative_path'), $sourceFiles);
                    $destinationFiles = array_combine(
                        array_column($destinationFiles, 'relative_path'),
                        $destinationFiles
                    );

                    if (!empty($config['ignore_timestamps'])) {
                        $filesToPush = $sourceFiles;
                    } else {
                        foreach ($sourceFiles as $sourceFile) {
                            $relativePath = $sourceFile['relative_path'];
                            // If file already exists on destination and is directory or is newer, skip it
                            if (isset($destinationFiles[$relativePath])
                                && ('dir' == $destinationFiles[$relativePath]['type']
                                    || (
                                        !empty($destinationFiles[$relativePath]['timestamp'])
                                        && !empty($sourceFile['timestamp'])
                                        && $destinationFiles[$relativePath]['timestamp'] >= $sourceFile['timestamp']))
                            ) {
                                continue;
                            }

                            $filesToPush[] = $sourceFile;
                        }
                    }

                    $this->logger->debug('Determining files to delete');
                    if ($delete) {
                        $filesToDelete = array_diff_key($destinationFiles, $sourceFiles);
                    }
                }
            }
        }

        if ($filesToPush || ($delete && $filesToDelete)) {
            $this->logger->debug('Removing implicit files from sync list');
            if ($filesToPush) {
                $filesToPush = $this->removeImplicitDirectoriesForPush($filesToPush);
            }

            if ($delete && $filesToDelete) {
                $filesToDelete = $this->removeImplicitFilesForDelete($filesToDelete);
            }
        }

        return [$filesToPush, $filesToDelete];
    }

    /**
     * @param MountManager $mountManager
     * @param string       $from
     * @param string       $to
     * @param array        $config
     * @param array        $filesToPush
     * @return bool True if all operations succeeded; False if any operations failed
     */
    private function putFiles(
        MountManager $mountManager,
        string $from,
        string $to,
        array $config,
        array $filesToPush
    ): bool {
        $hasErrors = false;
        list($prefixFrom, $pathFrom) = $mountManager->getPrefixAndPath($from);
        $pathFrom = trim($pathFrom, '/');
        list($prefixTo, $pathTo) = $mountManager->getPrefixAndPath($to);
        $pathTo = trim($pathTo, '/');
        $destinationFilesystem = $mountManager->getFilesystem($prefixTo);

        $batchSize = !empty($config['batch_size']) ? $config['batch_size'] : 100;
        $maxConcurrency = !empty($config['max_concurrency']) ? $config['max_concurrency'] : 10;

        $batchNumber = 1;
        $numBatches = ceil(count($filesToPush) / $batchSize);
        while ($batch = array_splice($filesToPush, 0, $batchSize)) {
            $this->logger->info(
                'Processing copy batch ' . number_format($batchNumber) . '/' . number_format($numBatches)
            );

            $forkManager = new ForkManager($this->logger);
            $forkManager->setMaxConcurrency($maxConcurrency);

            foreach ($batch as $file) {
                $executor = function () use (
                    $mountManager,
                    $destinationFilesystem,
                    $prefixFrom,
                    $pathFrom,
                    $prefixTo,
                    $pathTo,
                    $config,
                    $file
                ) {
                    if ('file' == $file['type']) {
                        $from = "$prefixFrom://$pathFrom/{$file['relative_path']}";
                        $to = "$prefixTo://$pathTo/{$file['relative_path']}";
                        $result = $mountManager->putFile($from, $to, $config);
                        if ($result === false) {
                            throw new Exception\RuntimeException(sprintf(
                                'Failed to copy file "%s" to "%s".',
                                $from,
                                $to
                            ));
                        }
                    } else {
                        $to = "$pathTo/{$file['relative_path']}";
                        $this->logger->debug("Creating directory $prefixTo://$to");
                        if (!$destinationFilesystem->has($to)) {
                            $result = $destinationFilesystem->createDir($to);
                            if ($result === false) {
                                throw new Exception\RuntimeException(sprintf(
                                    'Failed to create directory "%s".',
                                    $to,
                                ));
                            }
                        }
                    }
                };

                $forkManager->addWorker($executor);
            }

            try {
                $forkManager->execute();
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                $hasErrors = true;
            }
            $batchNumber++;
        };

        return !$hasErrors;
    }

    /**
     * @param MountManager $mountManager
     * @param string       $to
     * @param array        $filesToDelete
     * @param array        $config
     * @return bool True if all operations succeeded; False if any operations failed
     */
    private function deleteFiles(MountManager $mountManager, string $to, array $filesToDelete, array $config): bool
    {
        $hasErrors = false;
        list($prefixTo,) = $mountManager->getPrefixAndPath($to);
        $destinationFilesystem = $mountManager->getFilesystem($prefixTo);

        $batchSize = !empty($config['batch_size']) ? $config['batch_size'] : 100;
        $maxConcurrency = !empty($config['max_concurrency']) ? $config['max_concurrency'] : 10;

        $batchNumber = 1;
        $numBatches = ceil(count($filesToDelete) / $batchSize);

        while ($batch = array_slice($filesToDelete, $batchSize * ($batchNumber - 1), $batchSize)) {
            $this->logger->info(
                'Processing delete batch ' . number_format($batchNumber) . '/' . number_format($numBatches)
            );

            $forkManager = new ForkManager($this->logger);
            $forkManager->setMaxConcurrency($maxConcurrency);

            foreach ($batch as $file) {
                $executor = function () use (
                    $mountManager,
                    $destinationFilesystem,
                    $batch,
                    $prefixTo,
                    $file
                ) {
                    if ('file' == $file['type']) {
                        $this->logger->debug("Deleting file $prefixTo://{$file['path']}");
                        $destinationFilesystem->delete($file['path']);
                    } else {
                        $this->logger->debug("Deleting directory $prefixTo://{$file['path']}");
                        $destinationFilesystem->deleteDir($file['path']);
                    }
                };

                $forkManager->addWorker($executor);
            }

            try {
                $forkManager->execute();
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                $hasErrors = true;
            }

            $batchNumber++;
        };

        return !$hasErrors;
    }

}
