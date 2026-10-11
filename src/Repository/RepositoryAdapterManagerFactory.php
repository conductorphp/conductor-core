<?php

declare(strict_types=1);

namespace ConductorCore\Repository;

use ConductorCore\Exception;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function sprintf;

class RepositoryAdapterManagerFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): RepositoryAdapterManager {
        $config   = $container->get('config');
        $adapters = $config['vcs']['adapters'] ?? [];

        $definitions = [];
        foreach ($adapters as $name => $adapter) {
            if (isset($adapter['alias'])) {
                if (!isset($adapters[$adapter['alias']])) {
                    throw new Exception\DomainException(sprintf(
                        'VCS adapter "%s" aliases adapter "%s" which does not exist in configuration.',
                        $name,
                        $adapter['alias']
                    ));
                }
                $adapter = $adapters[$adapter['alias']];
            }
            if (empty($adapter['class'])) {
                throw new Exception\DomainException(sprintf('VCS adapter "%s" has no class (vcs.adapters.%s.class).', $name, $name));
            }
            $definitions[$name] = $adapter;
        }

        return new RepositoryAdapterManager($container, $definitions);
    }
}
