<?php

namespace ConductorCore;

return [
    'abstract_factories' => [
        \Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory::class
    ],
    'aliases'            => [
        \League\Flysystem\MountManager::class => Filesystem\MountManager\MountManager::class,
        // Core's own default for the conductor host's shell. Modules register their adapters under
        // their class and are selected through ShellAdapterManager; none binds this interface.
        Shell\Adapter\ShellAdapterInterface::class => Shell\Adapter\LocalShellAdapter::class,
        // What the container hands out for encryption: the resolver over both envelopes (CTAP-1968).
        Crypt\CryptInterface::class => Crypt\CryptResolver::class,
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
        Crypt\CryptResolver::class                         => Crypt\CryptResolverFactory::class,
        Database\DatabaseAdapterManager::class             => Database\DatabaseAdapterManagerFactory::class,
        Database\DatabaseImportExportAdapterManager::class => Database\DatabaseImportExportAdapterManagerFactory::class,
        Filesystem\MountManager\MountManager::class        => Filesystem\MountManager\MountManagerFactory::class,
        \League\Flysystem\Local\LocalFilesystemAdapter::class             => Filesystem\LocalAdapterFactory::class,
        Shell\ShellAdapterManager::class                   => Shell\ShellAdapterManagerFactory::class,
        Repository\RepositoryAdapterManager::class         => Repository\RepositoryAdapterManagerFactory::class,
        Runtime\RequirementChecker::class                  => Runtime\RequirementCheckerFactory::class,
        // The adapter `vcs.default_adapter` names, resolved on first use (CTAP-1758).
        Repository\RepositoryAdapterInterface::class       => Repository\DefaultRepositoryAdapterFactory::class,
    ],
];
