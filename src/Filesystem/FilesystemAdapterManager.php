<?php

namespace DevopsToolCore\Filesystem;

use DevopsToolCore\Exception;
use League\Flysystem\AdapterInterface;

class FilesystemAdapterManager
{
    /**
     * @var array
     */
    private $filesystemAdapters;

    /**
     * DatabaseExportAdapterFactory constructor.
     *
     * @param AdapterInterface[] $databaseExportAdapters
     */
    public function __construct(array $databaseExportAdapters)
    {
        $this->filesystemAdapters = $databaseExportAdapters;
    }

    /**
     * @param string|null $name
     *
     * @throws Exception\DomainException If requested filesystem adapter not found in those provided during construction
     * @return AdapterInterface
     */
    public function getAdapter(string $name): AdapterInterface
    {
        if (!array_key_exists($name, $this->filesystemAdapters)) {
            throw new Exception\DomainException("Filesystem adapter \"$name\" not found.");
        }

        return clone $this->filesystemAdapters[$name];
    }

    /**
     * @return array
     */
    public function getAdapterNames(): array
    {
        return array_keys($this->filesystemAdapters);
    }
}

