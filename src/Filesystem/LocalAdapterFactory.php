<?php

namespace ConductorCore\Filesystem;

use Laminas\ServiceManager\Factory\FactoryInterface;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;

class LocalAdapterFactory implements FactoryInterface
{
    /**
     * @throws ReflectionException
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): LocalFilesystemAdapter
    {
        // @todo Review this. This looks fishy...
        return (new ReflectionClass(LocalFilesystemAdapter::class))->newInstanceArgs($options);
    }
}
