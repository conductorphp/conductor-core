<?php

namespace ConductorCore;

return [
    'abstract_factories' => [
        \Zend\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory::class
    ],
    'factories'          => [
        \Psr\Log\LoggerInterface::class                    => DefaultLoggerFactory::class,
        Crypt\Command\DecryptCommand::class                => Crypt\Command\DecryptCommandFactory::class,
        Crypt\Command\EncryptCommand::class                => Crypt\Command\EncryptCommandFactory::class,
        Database\DatabaseAdapterManager::class             => Database\DatabaseAdapterManagerFactory::class,
        Database\DatabaseImportExportAdapterManager::class => Database\DatabaseImportExportAdapterManagerFactory::class,
        Filesystem\MountManager\MountManager::class        => Filesystem\MountManager\MountManagerFactory::class,
        \League\Flysystem\Adapter\Local::class             => Filesystem\LocalAdapterFactory::class,
    ],
];
