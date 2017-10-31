<?php

namespace DevopsToolCore\Filesystem;

use DevopsToolCore\Exception;
use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\ServiceManager\ServiceManager;

class FilesystemAdapterProviderFactory implements FactoryInterface
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
        $filesystemAdapters = [];
        $config = $container->get('config');
        if (!isset($config['filesystem']['adapters'])) {
            throw new Exception\RuntimeException('No configuration key found for "filesystem/adapters".');
        }

        foreach ($config['filesystem']['adapters'] as $name => $adapter) {
            $class = $adapter['class'];
            $arguments = !empty($adapter['arguments']) ? $adapter['arguments'] : [];
            if ($arguments) {
                if ($container instanceof ServiceManager) {
                    $filesystemAdapter = $container->build($class, $arguments);
                } else {
                    throw new Exception\LogicException(
                        'Adapter arguments not allowed if not using ' . ServiceManager::class . ' container.'
                    );
                }
            } else {
                $filesystemAdapter = $container->get($class);
            }

            $filesystemAdapters[$name] = $filesystemAdapter;
        }
        return new FilesystemAdapterProvider($filesystemAdapters);
    }
}

