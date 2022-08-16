<?php

namespace ConductorCore\Database;

use ConductorCore\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class DatabaseAdapterManager implements LoggerAwareInterface
{
    /** @var DatabaseAdapterInterface[] */
    private array $adapters;

    /**
     * @param DatabaseAdapterInterface[] $databaseAdapters
     */
    public function __construct(array $databaseAdapters)
    {
        $this->adapters = $databaseAdapters;
    }

    /**
     * @throws Exception\DomainException If requested database adapter not found in those provided during construction
     */
    public function getAdapter(string $name): DatabaseAdapterInterface
    {
        if (!array_key_exists($name, $this->adapters)) {
            throw new Exception\DomainException("Database adapter \"$name\" not found.");
        }

        return clone $this->adapters[$name];
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

