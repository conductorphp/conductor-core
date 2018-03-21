<?php

namespace ConductorCore\Repository;

interface RepositoryAdapterAwareInterface
{
    public function setRepositoryAdapter(RepositoryAdapterInterface $repositoryAdapter): void;
}
