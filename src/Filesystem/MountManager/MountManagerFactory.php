<?php

namespace ConductorCore\Filesystem\MountManager;

use ConductorCore\Exception;
use ConductorCore\Filesystem\Filesystem;
use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
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
                $filesystemConfig = null;
                try {
                    if ($arguments) {
                        if ($container instanceof ServiceManager) {
                            if (!empty($arguments['config'])) {
                                $filesystemConfig = $arguments['config'];
                                unset($arguments['config']);
                            }
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

                $filesystems[$prefix] = new Filesystem($filesystemAdapter, $filesystemConfig);
            }
        }

        return new MountManager($filesystems);
    }
}

