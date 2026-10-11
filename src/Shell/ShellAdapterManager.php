<?php

namespace ConductorCore\Shell;

use Closure;
use ConductorCore\Exception;
use ConductorCore\Shell\Adapter\ShellAdapterInterface;

class ShellAdapterManager
{
    /** @var array<string, ShellAdapterInterface|Closure(): ShellAdapterInterface> */
    private array $shellAdapters;

    /**
     * An entry may be a built adapter or a closure that builds one; see
     * {@see \ConductorCore\Database\DatabaseAdapterManager}.
     *
     * @param array<string, ShellAdapterInterface|Closure(): ShellAdapterInterface> $shellAdapters
     */
    public function __construct(array $shellAdapters)
    {
        $this->shellAdapters = $shellAdapters;
    }

    public function getAdapter(string $name): ShellAdapterInterface
    {
        if (!array_key_exists($name, $this->shellAdapters)) {
            throw new Exception\DomainException("Shell adapter \"$name\" not found.");
        }

        if ($this->shellAdapters[$name] instanceof Closure) {
            $this->shellAdapters[$name] = ($this->shellAdapters[$name])();
        }

        return clone $this->shellAdapters[$name];
    }

    public function getAdapterNames(): array
    {
        return array_keys($this->shellAdapters);
    }
}
