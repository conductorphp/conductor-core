<?php

namespace ConductorCore\Repository;

interface RepositoryAdapterAwareInterface
{
    /**
     * @param RepositoryAdapterInterface $repositoryAdapter
     */
    public function setRepositoryAdapter(RepositoryAdapterInterface $repositoryAdapter): void;
}
