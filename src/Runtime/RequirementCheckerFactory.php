<?php

declare(strict_types=1);

namespace ConductorCore\Runtime;

use ConductorCore\Shell\Adapter\ShellAdapterInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/** Checks against the conductor host's own shell; an adapter bound to a remote shell checks itself at use. */
class RequirementCheckerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): RequirementChecker
    {
        return new RequirementChecker($container->get(ShellAdapterInterface::class));
    }
}
