<?php

namespace ConductorCore;

return [
    'abstract_factories' => [
        \Zend\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory::class
    ],
    'aliases'            => [
        Shell\Adapter\ShellAdapterInterface::class => Shell\Adapter\LocalShellAdapter::class,
    ],
    'factories'          => [
        \Psr\Log\LoggerInterface::class                    => DefaultLoggerFactory::class,
        Console\Crypt\DecryptCommand::class                => Console\Crypt\DecryptCommandFactory::class,
        Console\Crypt\EncryptCommand::class                => Console\Crypt\EncryptCommandFactory::class,
        Database\DatabaseAdapterManager::class             => Database\DatabaseAdapterManagerFactory::class,
        Database\DatabaseImportExportAdapterManager::class => Database\DatabaseImportExportAdapterManagerFactory::class,
        Filesystem\MountManager\MountManager::class        => Filesystem\MountManager\MountManagerFactory::class,
        \League\Flysystem\Adapter\Local::class             => Filesystem\LocalAdapterFactory::class,
        Shell\ShellAdapterManager::class                   => Shell\ShellAdapterManagerFactory::class,
    ],
];
