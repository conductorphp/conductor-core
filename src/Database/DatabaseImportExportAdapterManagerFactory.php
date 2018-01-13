<?php

namespace DevopsToolCore\Database;

use DevopsToolCore\Exception;
use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\ServiceManager\ServiceManager;

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
        if (!isset($config['database']['importexport_adapters'])) {
            throw new Exception\RuntimeException('No configuration key found for "database/importexport_adapters".');
        }

        foreach ($config['database']['importexport_adapters'] as $name => $adapter) {
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
        return new DatabaseImportExportAdapterManager($databaseAdapters);
    }
}

