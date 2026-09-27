<?php

declare(strict_types=1);

namespace ConductorCore\Console\Crypt;

use ConductorCore\Crypt\CryptInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class DecryptCommandFactory implements FactoryInterface
{
    /** @param string $requestedName */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DecryptCommand
    {
        return new DecryptCommand($container->get(CryptInterface::class));
    }
}
