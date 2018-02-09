<?php

namespace DevopsToolCore\Filesystem\MountManager;

use DevopsToolCore\Exception;
use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use League\Flysystem\Filesystem;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\ServiceManager\ServiceManager;

class MountManagerFactory implements FactoryInterface
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
        $filesystems = [];
        $config = $container->get('config');
        if (isset($config['filesystem']['adapters'])) {
            foreach ($config['filesystem']['adapters'] as $prefix => $adapter) {
                $class = $adapter['class'];
                $arguments = !empty($adapter['arguments']) ? $adapter['arguments'] : [];
                try {
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
                } catch (ServiceNotCreatedException $e) {
                    throw new Exception\RuntimeException("Error in filesystem/adapters/$prefix configuration", 0, $e);
                }

                $filesystems[$prefix] = new Filesystem($filesystemAdapter);
            }
        }

        return new MountManager($filesystems);
    }
}

