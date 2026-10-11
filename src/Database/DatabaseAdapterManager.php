<?php

namespace ConductorCore\Database;

use Closure;
use ConductorCore\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class DatabaseAdapterManager implements LoggerAwareInterface
{
    /** @var array<string, DatabaseAdapterInterface|Closure(): DatabaseAdapterInterface> */
    private array $adapters;
    private ?LoggerInterface $logger = null;

    /**
     * An entry may be a built adapter or a closure that builds one. The factory passes closures, so a
     * configured adapter is built, and its runtime requirements checked, on the first getAdapter()
     * for its name rather than when the manager is (CTAP-1759).
     *
     * @param array<string, DatabaseAdapterInterface|Closure(): DatabaseAdapterInterface> $databaseAdapters
     */
    public function __construct(array $databaseAdapters)
    {
        $this->adapters = $databaseAdapters;
    }

    public function getAdapter(string $name): DatabaseAdapterInterface
    {
        if (!array_key_exists($name, $this->adapters)) {
            throw new Exception\DomainException("Database adapter \"$name\" not found.");
        }

        if ($this->adapters[$name] instanceof Closure) {
            $adapter = ($this->adapters[$name])();
            if ($this->logger !== null && $adapter instanceof LoggerAwareInterface) {
                $adapter->setLogger($this->logger);
            }
            $this->adapters[$name] = $adapter;
        }

        return clone $this->adapters[$name];
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
