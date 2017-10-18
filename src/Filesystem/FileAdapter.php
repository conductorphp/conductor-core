<?php

namespace DevopsToolCore\Filesystem;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FileAdapter
{
    /**
     * rmdir() will not remove the dir if it is not empty
     *
     * @param $directory
     */
    public function removePath($path)
    {
        if (false !== strpos($path, '*')) {
            $paths = glob($path);
            foreach ($paths as $path) {
                $this->removePath($path);
            }
        } else {
            if (is_dir($path)) {
                $iterator = new RecursiveDirectoryIterator($path);
                $iterator = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
                /** @var SplFileInfo $file */
                foreach ($iterator as $file) {
                    if ('.' === $file->getBasename() || '..' === $file->getBasename()) {
                        continue;
                    }
                    if ($file->isLink() || $file->isFile()) {
                        unlink($file->getPathname());
                    } else {
                        rmdir($file->getPathname());
                    }
                }
                rmdir($path);
            } else {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
