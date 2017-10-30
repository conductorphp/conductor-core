<?php

namespace DevopsToolCore\Database;

use DevopsToolCore\Exception;

class DatabaseImportAdapterFactory
{
    /**
     * @var array
     */
    private $databaseImportAdapters;

    /**
     * DatabaseImportAdapterFactory constructor.
     *
     * @param DatabaseImportAdapterInterface[] $databaseImportAdapters
     */
    public function __construct(array $databaseImportAdapters)
    {
        $this->databaseImportAdapters = $databaseImportAdapters;
    }

    /**
     * @param string|null $format
     *
     * @throws Exception\DomainException If requested import adapter not found in those provided during construction
     * @return DatabaseImportAdapterInterface
     */
    public function create($format)
    {
        if (!array_key_exists($format, $this->databaseImportAdapters)) {
            throw new Exception\DomainException("Database import adapter not found for format \"$format\".");
        }

        return clone $this->databaseImportAdapters[$format];
    }

    /**
     * @return array
     */
    public function getSupportedFormats()
    {
        return array_keys($this->databaseImportAdapters);
    }
}

