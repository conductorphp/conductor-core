<?php

namespace ConductorCore\Database;

use Closure;
use ConductorCore\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class DatabaseImportExportAdapterManager
{
    /** @var array<string, DatabaseImportExportAdapterInterface|Closure(): DatabaseImportExportAdapterInterface> */
    private array $adapters;
    private ?LoggerInterface $logger = null;

    /**
     * An entry may be a built adapter or a closure that builds one; see {@see DatabaseAdapterManager}.
     *
     * @param array<string, DatabaseImportExportAdapterInterface|Closure(): DatabaseImportExportAdapterInterface> $adapters
     */
    public function __construct(array $adapters, ?LoggerInterface $logger = null)
    {
        $this->adapters = $adapters;
        $this->logger   = $logger;
    }

    public function getAdapter(string $name): DatabaseImportExportAdapterInterface
    {
        if (!array_key_exists($name, $this->adapters)) {
            throw new Exception\DomainException("Database import/export adapter not found by name \"$name\".");
        }

        if ($this->adapters[$name] instanceof Closure) {
            $adapter = ($this->adapters[$name])();
            if ($this->logger !== null && $adapter instanceof LoggerAwareInterface) {
                $adapter->setLogger($this->logger);
            }
            $this->adapters[$name] = $adapter;
        }

        return $this->adapters[$name];
    }

    public function getAdapterNames(): array
    {
        return array_keys($this->adapters);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof LoggerAwareInterface) {
                $adapter->setLogger($logger);
            }
        }
    }
}
