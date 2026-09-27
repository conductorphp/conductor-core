<?php

declare(strict_types=1);

namespace ConductorCore\Console\Crypt;

use ConductorCore\Crypt\CryptInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class EncryptCommandFactory implements FactoryInterface
{
    /** @param string $requestedName */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): EncryptCommand
    {
        return new EncryptCommand($container->get(CryptInterface::class));
    }
}
