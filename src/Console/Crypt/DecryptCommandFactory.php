<?php

namespace ConductorCore\Console\Crypt;

use ConductorCore\Crypt\Crypt;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class DecryptCommandFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DecryptCommand
    {
        $config = $container->get('config');
        $crypt = $container->get(Crypt::class);
        return new DecryptCommand($crypt, $config['crypt_key'] ?? null);
    }
}

