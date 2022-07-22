<?php

namespace ConductorCore\Filesystem\MountManager;

use ConductorCore\Exception;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceManager;
use League\Flysystem\Filesystem;
use League\Flysystem\PathNormalizer;
use League\Flysystem\WhitespacePathNormalizer;
use Psr\Container\ContainerInterface;

class MountManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $filesystems = [];
        $config = $container->has('config') ? $container->get('config') : null;
        if (isset($config['filesystem']['adapters'])) {
            foreach ($config['filesystem']['adapters'] as $name => $adapter) {
                if (isset($adapter['alias'])) {
                    if (!isset($config['filesystem']['adapters'][$adapter['alias']])) {
                        throw new Exception\DomainException(sprintf(
                            'Filesystem adapter "%s" aliases adapter "%s" which does not exist in configuration.',
                            $name,
                            $adapter['alias']
                        ));
                    }
                    $adapter = $config['filesystem']['adapters'][$adapter['alias']];
                }

                $class = $adapter['class'];
                $arguments = !empty($adapter['arguments']) ? $adapter['arguments'] : [];
                $filesystemConfig = [];
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
                    throw new Exception\RuntimeException("Error in filesystem/adapters/$name configuration", 0, $e);
                }

                $filesystems[$name] = new Filesystem($filesystemAdapter, $filesystemConfig);
            }
        }

        return new MountManager(
            $filesystems,
            $container->get(WhitespacePathNormalizer::class),
        );
    }
}

