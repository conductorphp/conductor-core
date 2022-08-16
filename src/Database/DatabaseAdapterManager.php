<?php

namespace ConductorCore\Database;

use ConductorCore\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class DatabaseAdapterManager implements LoggerAwareInterface
{
    /**
     * @var array
     */
    private $adapters;

    /**
     * DatabaseExportAdapterFactory constructor.
     *
     * @param DatabaseAdapterInterface[] $databaseAdapters
     */
    public function __construct(array $databaseAdapters)
    {
        $this->adapters = $databaseAdapters;
    }

    /**
     * @param string|null $name
     *
     * @throws Exception\DomainException If requested database adapter not found in those provided during construction
     * @return DatabaseAdapterInterface
     */
    public function getAdapter(string $name): DatabaseAdapterInterface
    {
        if (!array_key_exists($name, $this->adapters)) {
            throw new Exception\DomainException("Database adapter \"$name\" not found.");
        }

        return clone $this->adapters[$name];
    }

    /**
     * @return array
     */
    public function getAdapterNames(): array
    {
        return array_keys($this->adapters);
    }

    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof LoggerAwareInterface) {
                $adapter->setLogger($logger);
            }
        }
        $this->logger = $logger;
    }
}

