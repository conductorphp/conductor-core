<?php

namespace ConductorCore\Filesystem\Adapter;

use ConductorCore\Exception;
use League\Flysystem\Config;

interface WriteStreamAccessibleAdapterInterface
{
    /**
     * @param string      $path
     * @param Config|null $config
     *
     * @return resource Stream resource
     * @throws Exception\RuntimeException if error occurred while opening write stream
     */
    public function getWriteStream(string $path, Config $config = null);
}
