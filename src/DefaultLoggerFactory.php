<?php

namespace ConductorCore;

use Monolog\Logger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;

class DefaultLoggerFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
{

    /**
     * Create an object
     *
     * @param  \Interop\Container\ContainerInterface $container
     * @param  string                                $requestedName
     * @param  null|array                            $options
     *
     * @return object
     * @throws \Laminas\ServiceManager\Exception\ServiceNotFoundException if unable to resolve the service.
     * @throws \Laminas\ServiceManager\Exception\ServiceNotCreatedException if an exception is raised when
     *     creating a service.
     * @throws \Interop\Container\Exception\ContainerException if any other error occurs
     */
    public function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Logger(
            'default', [
            (new ConsoleHandler())->setFormatter(new ConsoleFormatter())
        ]
        );
    }
}
