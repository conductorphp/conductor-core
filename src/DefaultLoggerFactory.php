<?php

namespace ConductorCore;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;

class DefaultLoggerFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Logger
    {
        return new Logger(
            'default', [
                (new ConsoleHandler())->setFormatter(new ConsoleFormatter()),
            ]
        );
    }
}
