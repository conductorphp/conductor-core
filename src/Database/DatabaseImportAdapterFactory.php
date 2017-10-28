<?php

namespace DevopsToolCore\Database;

use DevopsToolCore\Exception;

class DatabaseImportAdapterFactory
{
    /**
     * @var array
     */
    private $databaseImportAdapters;

    public function __construct(array $databaseImportAdapters)
    {
        $this->databaseImportAdapters = $databaseImportAdapters;
    }

    public function create($format = null)
    {
        // If no format provided, return the first import adapter
        if (is_null($format)) {
            return clone reset($this->databaseImportAdapters);
        }

        if (!array_key_exists($format, $this->databaseImportAdapters)) {
            throw new Exception\DomainException("Database import adapter not found for format \"$format\".");
        }

        return clone $this->databaseImportAdapters[$format];
    }
}

