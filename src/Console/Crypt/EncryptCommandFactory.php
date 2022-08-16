<?php

namespace ConductorCore\Console\Crypt;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class EncryptCommandFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('config');
        $crypt = $container->get('ConductorCore\Crypt\Crypt');
        return new EncryptCommand($crypt, $config['crypt_key'] ?? null);
    }
}

