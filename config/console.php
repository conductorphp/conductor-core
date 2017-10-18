<?php

namespace DevopsToolCore;

return [
    'commands' => [
        Database\Command\DatabaseExportCommand::class,
        Database\Command\DatabaseImportCommand::class,
        Database\Command\DatabaseSizesCommand::class,
    ],
];
