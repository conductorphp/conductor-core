<?php

namespace DevopsToolCore\Database;

use DevopsToolCore\Exception;
use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\Factory\FactoryInterface;

class DatabaseExportAdapterManagerFactory implements FactoryInterface
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
        $databaseExportAdapters = [];
        $config = $container->get('config');
        if (!isset($config['database']['export_adapters'])) {
            throw new Exception\RuntimeException('No configuration key found for "database/export_adapters".');
        }

        foreach ($config['database']['export_adapters'] as $name => $class) {
            $databaseExportAdapters[$name] = $container->get($class);
        }
        return new DatabaseExportAdapterManager($databaseExportAdapters);
    }
}

