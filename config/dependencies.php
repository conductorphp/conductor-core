<?php

namespace DevopsToolCore;

return [
    'abstract_factories' => [
        \Zend\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory::class
    ],
    'factories'          => [
        \Psr\Log\LoggerInterface::class              => DefaultLoggerFactory::class,
        Database\DatabaseAdapterManager::class       => Database\DatabaseAdapterManagerFactory::class,
        Database\DatabaseExportAdapterManager::class => Database\DatabaseExportAdapterManagerFactory::class,
        Database\DatabaseImportAdapterManager::class => Database\DatabaseImportAdapterManagerFactory::class,
        Filesystem\FilesystemAdapterManager::class   => Filesystem\FilesystemAdapterManagerFactory::class,
        Filesystem\MountManager\MountManager::class  => Filesystem\MountManager\MountManagerFactory::class,
        \League\Flysystem\Adapter\Local::class       => Filesystem\LocalAdapterFactory::class,
    ],
];
