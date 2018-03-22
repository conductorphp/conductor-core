<?php

namespace ConductorCore\Repository;

interface RepositoryAdapterInterface
{
    /**
     * @param string $repoUrl
     */
    public function setRepoUrl(string $repoUrl): void;

    /**
     * @param string $path
     */
    public function setPath(string $path): void;

    /**
     * @param string $repoReference
     */
    public function checkout(string $repoReference): void;

    /**
     * @return bool
     */
    public function isClean(): bool;

    /**
     *
     */
    public function pull(): void;

    /**
     * @param string $message
     */
    public function stash(string $message): void;
}
