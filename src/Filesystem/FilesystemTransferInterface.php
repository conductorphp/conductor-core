<?php

namespace DevopsToolCore\Filesystem;

use DevopsToolCore\Exception\RuntimeException;

interface FilesystemTransferInterface
{
    /**
     * @param string $sourcePath
     * @param string $destinationPath
     * @param array  $excludes
     * @param array  $includes
     * @param bool   $delete
     *
     * @throws RuntimeException if source path is not a readable directory or destination path is not a writable directory
     * @return void
     */
    public function sync(
        $sourcePath,
        $destinationPath,
        array $excludes = [],
        array $includes = [],
        $delete = false
    );

    /**
     * @param string $sourcePath
     * @param string $destinationPath
     *
     * @throws RuntimeException if source is not a readable file or destination path is not a writable file
     * @return void
     */
    public function copy(
        $sourcePath,
        $destinationPath
    );

    /**
     * @return FilesystemInterface
     */
    public function getSourceFilesystem();

    /**
     * @return FilesystemInterface
     */
    public function getDestinationFilesystem();
}
