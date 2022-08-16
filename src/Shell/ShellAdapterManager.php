<?php

namespace ConductorCore\Shell;

use ConductorCore\Exception;
use ConductorCore\Shell\Adapter\ShellAdapterInterface;

class ShellAdapterManager
{
    /** @var ShellAdapterInterface[] */
    private array $shellAdapters;

    public function __construct(array $shellAdapters)
    {
        $this->shellAdapters = $shellAdapters;
    }

    public function getAdapter(string $name): ShellAdapterInterface
    {
        if (!array_key_exists($name, $this->shellAdapters)) {
            throw new Exception\DomainException("Shell adapter \"$name\" not found.");
        }

        return clone $this->shellAdapters[$name];
    }

    public function getAdapterNames(): array
    {
        return array_keys($this->shellAdapters);
    }
}

