<?php

namespace DevopsToolCore\Database;

use DevopsToolCore\Exception;

class DatabaseImportExportAdapterManager
{
    /**
     * @var array
     */
    private $adapters;

    /**
     * DatabaseImportExportAdapterManager constructor.
     *
     * @param DatabaseImportExportAdapterInterface[] $adapters
     */
    public function __construct(array $adapters)
    {
        $this->adapters = $adapters;
    }

    /**
     * @param string|null $name
     *
     * @throws Exception\DomainException If requested import adapter not found in those provided during construction
     * @return DatabaseImportExportAdapterInterface
     */
    public function getAdapter(string $name): DatabaseImportExportAdapterInterface
    {
        if (!array_key_exists($name, $this->adapters)) {
            throw new Exception\DomainException("Database import/export adapter not found by name \"$name\".");
        }

        return clone $this->adapters[$name];
    }

    /**
     * @return array
     */
    public function getAdapterNames()
    {
        return array_keys($this->adapters);
    }
}

