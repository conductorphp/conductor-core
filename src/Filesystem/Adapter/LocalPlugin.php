<?php

namespace ConductorCore\Filesystem\Adapter;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Config;

class LocalPlugin extends Local
{
    /**
     * Constructor.
     *
     * @param string $root
     * @param int    $writeFlags
     * @param int    $linkHandling
     * @param array  $permissions
     *
     * @throws LogicException
     */
    public function __construct($root, $writeFlags = LOCK_EX, $linkHandling = self::DISALLOW_LINKS, array $permissions = [])
    {
        parent::__construct($root, $writeFlags, $linkHandling, $permissions);
    }

    /**
     * @inheritdoc
     */
    public function writeStream($path, $resource, Config $config)
    {
        $location = $this->applyPathPrefix($path);
        $this->ensureDirectory(dirname($location));
        $stream = fopen($location, 'w+b');

        if ($config->get('is_parallel', false)) {
            return $stream;
        }

        if ( ! $stream) {
            return false;
        }

        stream_copy_to_stream($resource, $stream);

        if ( ! fclose($stream)) {
            return false;
        }

        $type = 'file';

        $result = compact('type', 'path');

        if ($visibility = $config->get('visibility')) {
            $this->setVisibility($path, $visibility);
            $result['visibility'] = $visibility;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }
}
