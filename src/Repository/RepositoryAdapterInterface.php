<?php

namespace ConductorCore\Repository;

interface RepositoryAdapterInterface
{
    public function setRepoUrl(string $repoUrl): void;

    public function setPath(string $path): void;

    public function checkout(string $repoReference, bool $shallow = false): void;

    public function isClean(): bool;

    public function pull(): void;

    public function stash(string $message): void;
}
