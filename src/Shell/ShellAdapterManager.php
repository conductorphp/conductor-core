<?php

namespace ConductorCore\Shell;

use ConductorCore\Exception;
use ConductorCore\Shell\Adapter\ShellAdapterInterface;

class ShellAdapterManager
{
    /**
     * @var array
     */
    private $shellAdapters;

    /**
     * ShellAdapterManager constructor.
     *
     * @param ShellAdapterInterface[] $shellAdapters
     */
    public function __construct(array $shellAdapters)
    {
        $this->shellAdapters = $shellAdapters;
    }

    /**
     * @param string|null $name
     *
     * @return ShellAdapterInterface
     * @throws Exception\DomainException If requested shell adapter not found in those provided during construction
     */
    public function getAdapter(string $name): ShellAdapterInterface
    {
        if (!array_key_exists($name, $this->shellAdapters)) {
            throw new Exception\DomainException("Shell adapter \"$name\" not found.");
        }

        return clone $this->shellAdapters[$name];
    }

    /**
     * @return array
     */
    public function getAdapterNames(): array
    {
        return array_keys($this->shellAdapters);
    }
}

