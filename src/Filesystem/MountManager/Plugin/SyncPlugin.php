<?php

namespace ConductorCore\Filesystem\MountManager\Plugin;

use Amp\Loop;
use ConductorCore\Exception;
use ConductorCore\Filesystem\MountManager\MountManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function Amp\asyncCall;

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
     * @return void
     */
    public function sync(MountManager $mountManager, string $from, string $to, array $config = []): void
    {
        if (!$mountManager->has($from)) {
            throw new Exception\RuntimeException("Path \"$from\" does not exist.");
        }

        $metadata = $mountManager->getMetadata($from);
        $fileExists = $mountManager->has($to);
        if ($metadata && 'file' == $metadata['type']) {
            $sourceIsNewer = false;
            if ($fileExists) {
                $toMetadata = $mountManager->getMetadata($to);
                $sourceIsNewer = $toMetadata && $metadata['timestamp'] > $toMetadata['timestamp'];
            }

            if (!$fileExists || $sourceIsNewer) {
                $this->putFile($mountManager, $from, $to, $config);
            }
        } else {
            $this->syncDirectory($mountManager, $from, $to, $config);
        }

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
     */
    private function syncDirectory(MountManager $mountManager, string $from, string $to, array $config): void
    {
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
            $this->putFiles(
                $mountManager,
                $from,
                $to,
                $config,
                $filesToPush
            );
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
                $this->deleteFiles($mountManager, $to, $filesToDelete, $config);
            } else {
                $this->logger->info('No files to delete');
            }
        }
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

        return $files;
    }

    /**
     * @param string $file
     * @param array  $patterns
     *
     * @return bool
     */
    private function isMatch(string $file, array $patterns): bool
    {
        foreach ($patterns as &$pattern) {
            $pattern = str_replace('\*', '*', preg_quote($pattern, '%'));
            $pattern = preg_replace('%([^*])$%', '$1(/|$)', $pattern);
            $pattern = preg_replace('%^([^*/])%', '(^|/)$1', $pattern);
            $pattern = preg_replace('%^/%', '^', $pattern);
            $pattern = str_replace('*', '.*', $pattern);
            $pattern = "%$pattern%";
        }

        foreach ($patterns as $pattern) {
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
    private function determineFilesToPushAndDelete(MountManager $mountManager, string $from, string $to, array $config): array
    {
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
     */
    private function putFiles(MountManager $mountManager, string $from, string $to, array $config, array $filesToPush): void
    {
        list($prefixFrom, $pathFrom) = $mountManager->getPrefixAndPath($from);
        $pathFrom = trim($pathFrom, '/');
        list($prefixTo, $pathTo) = $mountManager->getPrefixAndPath($to);
        $pathTo = trim($pathTo, '/');
        $destinationFilesystem = $mountManager->getFilesystem($prefixTo);

        $batchSize = !empty($config['batch_size']) ? $config['batch_size'] : 100;
        $batchNumber = 1;
        $numBatches = ceil(count($filesToPush) / $batchSize);
        while ($batch = array_slice($filesToPush, $batchSize * ($batchNumber - 1), $batchSize)) {
            $this->logger->info(
                'Processing copy batch ' . number_format($batchNumber) . '/' . number_format($numBatches)
            );
            // @todo Figure out how to actually make this run asynchronously
            asyncCall(
                function () use (
                    $mountManager,
                    $destinationFilesystem,
                    $batch,
                    $prefixFrom,
                    $pathFrom,
                    $prefixTo,
                    $pathTo,
                    $config
                ) {
                    foreach ($batch as $file) {
                        if ('file' == $file['type']) {
                            $from = "$prefixFrom://$pathFrom/{$file['relative_path']}";
                            $to = "$prefixTo://$pathTo/{$file['relative_path']}";
                            $mountManager->putFile($from, $to, $config);
                        } else {
                            $to = "$pathTo/{$file['relative_path']}";
                            $this->logger->debug("Creating directory $prefixTo://$to");
                            $destinationFilesystem->createDir($to);
                        }
                    }
                }
            );

            Loop::run();
            $batchNumber++;
        };
    }

    /**
     * @param MountManager $mountManager
     * @param string       $to
     * @param array        $filesToDelete
     * @param array        $config
     */
    private function deleteFiles(MountManager $mountManager, string $to, array $filesToDelete, array $config): void
    {
        list($prefixTo,) = $mountManager->getPrefixAndPath($to);
        $destinationFilesystem = $mountManager->getFilesystem($prefixTo);

        $batchSize = !empty($config['batch_size']) ? $config['batch_size'] : 100;
        $batchNumber = 1;
        $numBatches = ceil(count($filesToDelete) / $batchSize);

        while ($batch = array_slice($filesToDelete, $batchSize * ($batchNumber - 1), $batchSize)) {
            $this->logger->info(
                'Processing delete batch ' . number_format($batchNumber) . '/' . number_format($numBatches)
            );
            asyncCall(
                function () use (
                    $mountManager,
                    $destinationFilesystem,
                    $batch,
                    $prefixTo
                ) {
                    foreach ($batch as $file) {
                        if ('file' == $file['type']) {
                            $this->logger->debug("Deleting file $prefixTo://{$file['path']}");
                            $destinationFilesystem->delete($file['path']);
                        } else {
                            $this->logger->debug("Deleting directory $prefixTo://{$file['path']}");
                            $destinationFilesystem->deleteDir($file['path']);
                        }
                    }
                }
            );

            Loop::run();
            $batchNumber++;
        };
    }

}
