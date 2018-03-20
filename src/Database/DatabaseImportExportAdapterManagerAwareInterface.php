<?php

namespace ConductorCore\Database;

interface DatabaseImportExportAdapterManagerAwareInterface
{
    public function setDatabaseImportExportAdapterManager(
        DatabaseImportExportAdapterManager $databaseImportExportAdapterManager
    );
}

