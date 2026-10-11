<?php

declare(strict_types=1);

namespace ConductorCore\Repository;

use ConductorCore\Exception;
use ConductorCore\Runtime\RequirementChecker;
use ConductorCore\Runtime\RuntimeRequirementsInterface;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;

use function array_key_exists;
use function array_keys;
use function get_debug_type;
use function implode;
use function sprintf;

/**
 * The VCS adapters configured under `vcs.adapters`, selected by name, built on first request.
 *
 * Same shape as {@see \ConductorCore\Shell\ShellAdapterManager}: a VCS module registers a factory
 * under its adapter class and contributes `vcs.adapters.<name>.class`; nothing binds
 * {@see RepositoryAdapterInterface} to a module's class. Before this, git-vcs-support bound the
 * interface directly, so a second VCS module would have competed for it the way the platform
 * modules did (CTAP-1758).
 *
 * Adapters are built on the first `getAdapter()` for their name rather than when the manager is,
 * so a configured adapter whose runtime is missing costs nothing until it is selected.
 */
final class RepositoryAdapterManager
{
    /** @var array<string, RepositoryAdapterInterface> */
    private array $prototypes = [];

    /**
     * @param array<string, array{class: class-string, arguments?: array<string, mixed>}> $definitions
     *        Adapter name => definition, aliases already resolved
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $definitions,
    ) {
    }

    /** @return list<string> */
    public function getAdapterNames(): array
    {
        return array_keys($this->definitions);
    }

    public function hasAdapter(string $name): bool
    {
        return array_key_exists($name, $this->definitions);
    }

    /**
     * Callers set a repo URL and path on the adapter they are handed, so each call yields its own
     * instance: a clone of the built prototype, as the shell and database managers do.
     *
     * @throws Exception\DomainException When no adapter is configured under that name
     */
    public function getAdapter(string $name): RepositoryAdapterInterface
    {
        if (!$this->hasAdapter($name)) {
            throw new Exception\DomainException(sprintf(
                'VCS adapter "%s" is not configured (vcs.adapters.%s). Configured adapters: %s. A VCS'
                . ' module such as conductor/git-vcs-support registers one when installed.',
                $name,
                $name,
                $this->definitions === [] ? 'none' : implode(', ', $this->getAdapterNames()),
            ));
        }

        return clone ($this->prototypes[$name] ??= $this->build($name));
    }

    private function build(string $name): RepositoryAdapterInterface
    {
        $definition = $this->definitions[$name];
        $class      = $definition['class'];
        $arguments  = $definition['arguments'] ?? [];

        if ($arguments !== []) {
            if (!$this->container instanceof ServiceManager) {
                throw new Exception\LogicException(
                    'Adapter arguments not allowed if not using ' . ServiceManager::class . ' container.'
                );
            }
            $adapter = $this->container->build($class, $arguments);
        } else {
            $adapter = $this->container->get($class);
        }

        if (!$adapter instanceof RepositoryAdapterInterface) {
            throw new Exception\DomainException(sprintf(
                'vcs.adapters.%s names %s, but the container returned %s, which is not a %s.',
                $name,
                $class,
                get_debug_type($adapter),
                RepositoryAdapterInterface::class,
            ));
        }

        if ($adapter instanceof RuntimeRequirementsInterface) {
            $this->container->get(RequirementChecker::class)
                ->assert(sprintf('VCS adapter "%s" (vcs.adapters.%s)', $name, $name), $adapter->runtimeRequirements());
        }

        return $adapter;
    }
}
