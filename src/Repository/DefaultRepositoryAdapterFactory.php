<?php

declare(strict_types=1);

namespace ConductorCore\Repository;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class DefaultRepositoryAdapterFactory implements FactoryInterface
{
    public const DEFAULT_ADAPTER = 'git';

    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): DefaultRepositoryAdapter {
        $config = $container->get('config');

        return new DefaultRepositoryAdapter(
            $container->get(RepositoryAdapterManager::class),
            $config['vcs']['default_adapter'] ?? self::DEFAULT_ADAPTER,
        );
    }
}
