<?php

declare(strict_types=1);

namespace ConductorCore\Repository;

/**
 * The {@see RepositoryAdapterInterface} the container hands out: the adapter `vcs.default_adapter`
 * names, resolved from {@see RepositoryAdapterManager} on the first call.
 *
 * Consumers that take the interface in their constructor are built at boot (every console command
 * is), so resolving eagerly would make a host without the default adapter's module fail `list`.
 */
final class DefaultRepositoryAdapter implements RepositoryAdapterInterface
{
    private ?RepositoryAdapterInterface $adapter = null;

    public function __construct(
        private readonly RepositoryAdapterManager $manager,
        private readonly string $adapterName,
    ) {
    }

    public function setRepoUrl(string $repoUrl): void
    {
        $this->adapter()->setRepoUrl($repoUrl);
    }

    public function setPath(string $path): void
    {
        $this->adapter()->setPath($path);
    }

    public function checkout(string $repoReference, bool $shallow = false): void
    {
        $this->adapter()->checkout($repoReference, $shallow);
    }

    public function isClean(): bool
    {
        return $this->adapter()->isClean();
    }

    public function pull(): void
    {
        $this->adapter()->pull();
    }

    public function stash(string $message): void
    {
        $this->adapter()->stash($message);
    }

    private function adapter(): RepositoryAdapterInterface
    {
        return $this->adapter ??= $this->manager->getAdapter($this->adapterName);
    }
}
