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
    // Console I/O services. These must be registered explicitly: left to the
    // ReflectionBasedAbstractFactory, servicemanager v4 resolves ArgvInput's
    // optional $definition parameter from the container and hands it an empty
    // InputDefinition, so binding the real argv fails with "No arguments
    // expected". v3 left that parameter null. Constructing them directly is
    // also what the console expects — ArgvInput reads $_SERVER['argv'].
    'invokables'         => [
        \Symfony\Component\Console\Input\ArgvInput::class => \Symfony\Component\Console\Input\ArgvInput::class,
        \Symfony\Component\Console\Output\ConsoleOutput::class => \Symfony\Component\Console\Output\ConsoleOutput::class,
        \Symfony\Component\Console\Helper\QuestionHelper::class => \Symfony\Component\Console\Helper\QuestionHelper::class,
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
