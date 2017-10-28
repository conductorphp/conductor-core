<?php

namespace DevopsToolCore;

return [
    'commands' => [
        Database\Command\DatabaseExportCommand::class,
        Database\Command\DatabaseImportCommand::class,
        Database\Command\DatabaseMetadataCommand::class,
        Database\Command\TableMetadataCommand::class,
    ],
];
