<?php

namespace ConductorCore\Database;

use ConductorCore\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DatabaseImportExportAdapterManager
{
    /** @var DatabaseImportExportAdapterInterface[] */
    private array $adapters;

    /**
     * @param DatabaseImportExportAdapterInterface[] $adapters
     */
    public function __construct(array $adapters, LoggerInterface $logger = null)
    {
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->adapters = $adapters;
    }

    /**
     * @return DatabaseImportExportAdapterInterface
     * @throws Exception\DomainException If requested import adapter not found in those provided during construction
     */
    public function getAdapter(string $name): DatabaseImportExportAdapterInterface
    {
        if (!array_key_exists($name, $this->adapters)) {
            throw new Exception\DomainException("Database import/export adapter not found by name \"$name\".");
        }

        return $this->adapters[$name];
    }

    public function getAdapterNames(): array
    {
        return array_keys($this->adapters);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof LoggerAwareInterface) {
                $adapter->setLogger($logger);
            }
        }
    }
}

