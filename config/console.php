<?php

namespace DevopsToolCore;

return [
    'commands' => [
        Crypt\Command\GenerateKeyCommand::class,
        Crypt\Command\DecryptCommand::class,
        Crypt\Command\EncryptCommand::class,
        Database\Command\DatabaseExportCommand::class,
        Database\Command\DatabaseImportCommand::class,
        Database\Command\DatabaseMetadataCommand::class,
        Database\Command\TableMetadataCommand::class,
        Filesystem\Command\FilesystemLsCommand::class,
        Filesystem\Command\FilesystemCopyCommand::class,
        Filesystem\Command\FilesystemSyncCommand::class,
    ],
];
