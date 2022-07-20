<?php

namespace ConductorCore;

return [
    'abstract_factories' => [
        \Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory::class
    ],
    'aliases'            => [
        \League\Flysystem\MountManager::class => Filesystem\MountManager\MountManager::class,
        Shell\Adapter\ShellAdapterInterface::class => Shell\Adapter\LocalShellAdapter::class,
    ],
    'factories'          => [
        \Psr\Log\LoggerInterface::class                    => DefaultLoggerFactory::class,
        Console\Crypt\DecryptCommand::class                => Console\Crypt\DecryptCommandFactory::class,
        Console\Crypt\EncryptCommand::class                => Console\Crypt\EncryptCommandFactory::class,
        Database\DatabaseAdapterManager::class             => Database\DatabaseAdapterManagerFactory::class,
        Database\DatabaseImportExportAdapterManager::class => Database\DatabaseImportExportAdapterManagerFactory::class,
        Filesystem\MountManager\MountManager::class        => Filesystem\MountManager\MountManagerFactory::class,
        \League\Flysystem\Local\LocalFilesystemAdapter::class             => Filesystem\LocalAdapterFactory::class,
        Shell\ShellAdapterManager::class                   => Shell\ShellAdapterManagerFactory::class,
    ],
];
