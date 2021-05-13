<?php

namespace ConductorCore;

return [
    'commands' => [
        Console\Crypt\GenerateKeyCommand::class,
        Console\Crypt\DecryptCommand::class,
        Console\Crypt\EncryptCommand::class,
        Console\Database\DatabaseExportCommand::class,
        Console\Database\DatabaseImportCommand::class,
        Console\Database\DatabaseMetadataCommand::class,
        Console\Database\TableMetadataCommand::class,
        Console\Filesystem\FilesystemLsCommand::class,
        Console\Filesystem\FilesystemMvCommand::class,
        Console\Filesystem\FilesystemRmCommand::class,
        Console\Filesystem\FilesystemCopyCommand::class,
        Console\Filesystem\FilesystemSyncCommand::class,
        Console\Shell\ExecCommand::class,
    ],
];
