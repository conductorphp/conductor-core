<?php

namespace ConductorCore\Filesystem;

use League\Flysystem\Util;

/**
 * Class Filesystem
 *
 * Overrode to deal with directories consistently across adapters
 *
 * @see     https://github.com/thephpleague/flysystem/issues/899
 * @package ConductorCore\Filesystem
 */
class Filesystem extends \League\Flysystem\Filesystem
{
    /**
     * @inheritdoc
     */
    public function has($path)
    {
        $path = Util::normalizePath($path);

        // Root path always exists. Skip the filesystem check
        if ('' == $path || '.' == $path) {
            return true;
        }

        if ($this->getAdapter()->has($path)) {
            return true;
        }

        // Deal with inconsistent adapter behavior.
        // Some adapters always return false for directories.
        // Check if this is an existing directory by checking if it exists in its parent's contents
        $parts = explode('/', $path);
        array_pop($parts);
        $parentPath = implode('/', $parts);

        foreach ($this->listContents($parentPath) as $parentContent) {
            if ($path == $parentContent['path']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getMetadata($path): array
    {
        $path = Util::normalizePath($path);
        $this->assertPresent($path);

        // Deal with inconsistent adapter behavior.
        // Some adapters will throw exceptions for directories; Some return false; Some return an array
        try {
            $metaData = $this->getAdapter()->getMetadata($path);
        } catch (\Exception $e) {
            // Do nothing
        }

        // If file exists, but returns no metadata, assume it's a directory
        if (empty($metaData)) {
            $metaData = [
                'type' => 'dir',
                'path' => $path,
            ];
        }
        return $metaData;
    }
}
