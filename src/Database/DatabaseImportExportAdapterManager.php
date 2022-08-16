<?php

namespace ConductorCore\Database;

use ConductorCore\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DatabaseImportExportAdapterManager
{
    /**
     * @var array
     */
    private $adapters;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * DatabaseImportExportAdapterManager constructor.
     *
     * @param DatabaseImportExportAdapterInterface[] $adapters
     */
    public function __construct(array $adapters, LoggerInterface $logger = null)
    {
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->adapters = $adapters;
        $this->logger = $logger;
    }

    /**
     * @param string|null $name
     *
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

    /**
     * @return array
     */
    public function getAdapterNames(): array
    {
        return array_keys($this->adapters);
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof LoggerAwareInterface) {
                $adapter->setLogger($logger);
            }
        }
        $this->logger = $logger;
    }
}

