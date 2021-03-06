<?php

namespace ConductorCore\Database;

use ConductorCore\Exception;
use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceManager;

class DatabaseImportExportAdapterManagerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string             $requestedName
     * @param  null|array         $options
     *
     * @return object
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     *     creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $databaseAdapters = [];
        $config = $container->get('config');
        if (isset($config['database']['importexport_adapters'])) {
            foreach ($config['database']['importexport_adapters'] as $name => $adapter) {
                if (isset($adapter['alias'])) {
                    if (!isset($config['database']['importexport_adapters'][$adapter['alias']])) {
                        throw new Exception\DomainException(sprintf(
                            'Database import/export adapter "%s" aliases adapter "%s" which does not exist in configuration.',
                            $name,
                            $adapter['alias']
                        ));
                    }
                    $adapter = $config['database']['importexport_adapters'][$adapter['alias']];
                }

                $class = $adapter['class'];
                $arguments = !empty($adapter['arguments']) ? $adapter['arguments'] : [];
                try {
                    if ($arguments) {
                        if ($container instanceof ServiceManager) {
                            $databaseAdapter = $container->build($class, $arguments);
                        } else {
                            throw new Exception\LogicException(
                                'Adapter arguments not allowed if not using ' . ServiceManager::class . ' container.'
                            );
                        }
                    } else {
                        $databaseAdapter = $container->get($class);
                    }
                } catch (ServiceNotCreatedException $e) {
                    throw new Exception\RuntimeException("Error in database/importexport_adapters/$name configuration");
                }

                $databaseAdapters[$name] = $databaseAdapter;
            }
        }
        return new DatabaseImportExportAdapterManager($databaseAdapters);
    }
}

