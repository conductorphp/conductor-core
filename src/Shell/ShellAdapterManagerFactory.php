<?php

namespace ConductorCore\Shell;

use ConductorCore\Exception;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceManager;
use ConductorCore\Runtime\RequirementChecker;
use ConductorCore\Runtime\RuntimeRequirementsInterface;
use Psr\Container\ContainerInterface;

class ShellAdapterManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ShellAdapterManager
    {
        $shellAdapters = [];
        $config = $container->get('config');
        if (isset($config['shell']['adapters'])) {
            foreach ($config['shell']['adapters'] as $name => $adapter) {
                if (isset($adapter['alias'])) {
                    if (!isset($config['shell']['adapters'][$adapter['alias']])) {
                        throw new Exception\DomainException(sprintf(
                            'Shell adapter "%s" aliases adapter "%s" which does not exist in configuration.',
                            $name,
                            $adapter['alias']
                        ));
                    }
                    $adapter = $config['shell']['adapters'][$adapter['alias']];
                }

                $class = $adapter['class'];
                $arguments = !empty($adapter['arguments']) ? $adapter['arguments'] : [];
                // Built on the first getAdapter() for this name, not here, so a configured adapter
                // that is never selected costs nothing and cannot fail boot. Its runtime
                // requirements are checked at that moment, naming the config key (CTAP-1759).
                $shellAdapters[$name] = static function () use ($container, $class, $arguments, $name): object {
                    try {
                        if ($arguments) {
                            if ($container instanceof ServiceManager) {
                                $shellAdapter = $container->build($class, $arguments);
                            } else {
                                throw new Exception\LogicException(
                                    'Adapter arguments not allowed if not using ' . ServiceManager::class . ' container.'
                                );
                            }
                        } else {
                            $shellAdapter = $container->get($class);
                        }
                    } catch (ServiceNotCreatedException $e) {
                        throw new Exception\RuntimeException("Error in shell/adapters/$name configuration");
                    }
                    if ($shellAdapter instanceof RuntimeRequirementsInterface) {
                        $container->get(RequirementChecker::class)->assertUsable(
                            sprintf('shell adapter "%s" (shell.adapters.%s)', $name, $name),
                            $shellAdapter,
                        );
                    }

                    return $shellAdapter;
                };
            }
        }
        return new ShellAdapterManager($shellAdapters);
    }
}

