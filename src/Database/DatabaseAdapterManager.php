<?php

namespace ConductorCore\Database;

use ConductorCore\Exception;

class DatabaseAdapterManager
{
    /**
     * @var array
     */
    private $databaseAdapters;

    /**
     * DatabaseExportAdapterFactory constructor.
     *
     * @param DatabaseAdapterInterface[] $databaseExportAdapters
     */
    public function __construct(array $databaseExportAdapters)
    {
        $this->databaseAdapters = $databaseExportAdapters;
    }

    /**
     * @param string|null $name
     *
     * @throws Exception\DomainException If requested database adapter not found in those provided during construction
     * @return DatabaseAdapterInterface
     */
    public function getAdapter(string $name): DatabaseAdapterInterface
    {
        if (!array_key_exists($name, $this->databaseAdapters)) {
            throw new Exception\DomainException("Database adapter \"$name\" not found.");
        }

        return clone $this->databaseAdapters[$name];
    }

    /**
     * @return array
     */
    public function getAdapterNames(): array
    {
        return array_keys($this->databaseAdapters);
    }
}

