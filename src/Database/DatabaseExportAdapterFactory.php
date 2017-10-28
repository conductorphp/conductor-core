<?php

namespace DevopsToolCore\Database;

use DevopsToolCore\Exception;

class DatabaseExportAdapterFactory
{
    /**
     * @var array
     */
    private $databaseExportAdapters;

    public function __construct(array $databaseExportAdapters)
    {
        $this->databaseExportAdapters = $databaseExportAdapters;
    }

    public function create($format = null)
    {
        // If no format provided, return the first export adapter
        if (is_null($format)) {
            return clone reset($this->databaseExportAdapters);
        }

        if (!array_key_exists($format, $this->databaseExportAdapters)) {
            throw new Exception\DomainException("Database export adapter not found for format \"$format\".");
        }

        return clone $this->databaseExportAdapters[$format];
    }
}

