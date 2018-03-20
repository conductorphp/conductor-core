<?php

namespace ConductorCore\Database;

interface DatabaseAdapterManagerAwareInterface
{
    public function setDatabaseAdapterManager(
        DatabaseAdapterManager $databaseAdapterManager
    ): void;
}

