<?php

namespace DevopsToolCore;

return [
    'abstract_factories' => [
        \Zend\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory::class
    ],
    'factories'          => [
        \Psr\Log\LoggerInterface::class              => DefaultLoggerFactory::class,
        Database\DatabaseAdapterManager::class       => Database\DatabaseAdapterManagerFactory::class,
        Database\DatabaseExportAdapterFactory::class => Database\DatabaseExportAdapterFactoryFactory::class,
        Database\DatabaseImportAdapterFactory::class => Database\DatabaseImportAdapterFactoryFactory::class,
        Filesystem\FilesystemAdapterManager::class   => Filesystem\FilesystemAdapterManagerFactory::class,
        \League\Flysystem\Adapter\Local::class       => Filesystem\LocalAdapterFactory::class,
    ],
];
