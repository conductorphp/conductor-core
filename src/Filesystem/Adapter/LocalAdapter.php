<?php

namespace ConductorCore\Filesystem\Adapter;

use ConductorCore\Exception;
use League\Flysystem\Adapter\Local as FlysystemLocalAdapter;
use League\Flysystem\Config;

class LocalAdapter extends FlysystemLocalAdapter implements WriteStreamAccessibleAdapterInterface
{
    /**
     * @inheritdoc
     */
    public function getWriteStream(string $path, Config $config = null)
    {
        $location = $this->applyPathPrefix($path);
        $this->ensureDirectory(dirname($location));
        $resource = fopen($location, 'w+b');
        if (false === $resource) {
            throw new Exception\RuntimeException('An error occurred while opening write stream.');
        }

        return $resource;
    }
}
