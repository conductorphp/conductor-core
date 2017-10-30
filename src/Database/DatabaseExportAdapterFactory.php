<?php

namespace DevopsToolCore\Database;

use DevopsToolCore\Exception;

class DatabaseExportAdapterFactory
{
    /**
     * @var array
     */
    private $databaseExportAdapters;

    /**
     * DatabaseExportAdapterFactory constructor.
     *
     * @param DatabaseExportAdapterInterface[] $databaseExportAdapters
     */
    public function __construct(array $databaseExportAdapters)
    {
        $this->databaseExportAdapters = $databaseExportAdapters;
    }

    /**
     * @param string|null $format
     *
     * @throws Exception\DomainException If requested export adapter not found in those provided during construction
     * @return DatabaseExportAdapterInterface
     */
    public function create($format)
    {
        if (!array_key_exists($format, $this->databaseExportAdapters)) {
            throw new Exception\DomainException("Database export adapter not found for format \"$format\".");
        }

        return clone $this->databaseExportAdapters[$format];
    }

    /**
     * @return array
     */
    public function getSupportedFormats()
    {
        return array_keys($this->databaseExportAdapters);
    }
}

