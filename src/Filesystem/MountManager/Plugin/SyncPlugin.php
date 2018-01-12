<?php

namespace DevopsToolCore\Filesystem\MountManager\Plugin;

use DevopsToolCore\Exception;
use DevopsToolCore\Filesystem\MountManager\MountManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
    public function sync(MountManager $mountManager, $from, $to, array $config = [])
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
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * This method is the same as copy, except that it does a putStream rather than writeStream, allowing it to write
     * even if the file exists.
     *
     * @param       $from
     * @param       $to
     * @param array $config
     *
     * @return bool
     */
    private function putFile(MountManager $mountManager, $from, $to, array $config)
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
     * @param       $from
     * @param       $to
     * @param array $config
     *
     * @return bool
     */
    private function syncDirectory(MountManager $mountManager, $from, $to, array $config)
    {
        list($filesToPush, $filesToDelete) = $this->determineFilesToPushAndDelete($mountManager, $from, $to, $config);

        if ($filesToPush) {
            $this->logger->debug('Pushing ' . number_format(count($filesToPush)) . ' file(s)');
            $this->putFiles(
                $mountManager,
                $from,
                $to,
                $config,
                $filesToPush
            );
        } else {
            $this->logger->debug('No files to push');
        }

        if (!empty($config['delete'])) {
            if ($filesToDelete) {
                $this->logger->debug('Deleting ' . number_format(count($filesToDelete)) . ' file(s)');
                $this->deleteFiles($mountManager, $to, $filesToDelete);
            } else {
                $this->logger->debug('No files to delete');
            }
        }

        return true;
    }


    /**
     * @param $filesToPush
     */
    private function removeImplicitDirectoriesForPush($filesToPush)
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
     * @param $filesToDelete
     */
    private function removeImplicitFilesForDelete($filesToDelete)
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

    private function applyExcludesAndIncludes(array $files, array $excludes = [], array $includes = [])
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

    private function isMatch($file, $patterns)
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
     * @param              $from
     * @param              $to
     * @param array        $config
     *
     * @return array
     */
    private function determineFilesToPushAndDelete(MountManager $mountManager, $from, $to, array $config)
    {
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

        if ($filesToPush) {
            $filesToPush = $this->removeImplicitDirectoriesForPush($filesToPush);
        }

        if ($delete && $filesToDelete) {
            $filesToDelete = $this->removeImplicitFilesForDelete($filesToDelete);
        }
        return [$filesToPush, $filesToDelete];
    }

    /**
     * @param MountManager $mountManager
     * @param              $from
     * @param              $to
     * @param array        $config
     * @param              $filesToPush
     *
     * @return void
     */
    private function putFiles(MountManager $mountManager, $from, $to, array $config, $filesToPush)
    {
        list($prefixFrom, $pathFrom) = $mountManager->getPrefixAndPath($from);
        $pathFrom = trim($pathFrom, '/');
        list($prefixTo, $pathTo) = $mountManager->getPrefixAndPath($to);
        $pathTo = trim($pathTo, '/');
        $destinationFilesystem = $mountManager->getFilesystem($prefixTo);
        foreach ($filesToPush as $file) {
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

    /**
     * @param MountManager $mountManager
     * @param              $to
     * @param              $filesToDelete
     */
    private function deleteFiles(MountManager $mountManager, $to, $filesToDelete)
    {
        list($prefixTo,) = $mountManager->getPrefixAndPath($to);
        $destinationFilesystem = $mountManager->getFilesystem($prefixTo);
        foreach ($filesToDelete as $file) {
            if ('file' == $file['type']) {
                $this->logger->debug("Deleting file $prefixTo://{$file['path']}");
                $destinationFilesystem->delete($file['path']);
            } else {
                $this->logger->debug("Deleting directory $prefixTo://{$file['path']}");
                $destinationFilesystem->deleteDir($file['path']);
            }
        }
    }

}
