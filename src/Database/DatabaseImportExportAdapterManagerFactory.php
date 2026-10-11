<?php

namespace ConductorCore\Database;

use ConductorCore\Exception;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceManager;
use ConductorCore\Runtime\RequirementChecker;
use ConductorCore\Runtime\RuntimeRequirementsInterface;
use Psr\Container\ContainerInterface;

class DatabaseImportExportAdapterManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DatabaseImportExportAdapterManager
    {
        $databaseAdapters = [];
        $config = $container->get('config');
        if (isset($config['database']['importexport_adapters'])) {
            foreach ($config['database']['importexport_adapters'] as $name => $adapter) {
                if (isset($adapter['alias'])) {
                    if (!isset($config['database']['importexport_adapters'][$adapter['alias']])) {
                        throw new Exception\DomainException(sprintf(
                            'Database import/export adapter "%s" aliases adapter "%s" which does not exist in configuration.',
                            $name,
                            $adapter['alias']
                        ));
                    }
                    $adapter = $config['database']['importexport_adapters'][$adapter['alias']];
                }

                $class = $adapter['class'];
                $arguments = !empty($adapter['arguments']) ? $adapter['arguments'] : [];
                // Built on the first getAdapter() for this name, not here, so a configured adapter
                // that is never selected costs nothing and cannot fail boot. Its runtime
                // requirements are checked at that moment, naming the config key (CTAP-1759).
                $databaseAdapters[$name] = static function () use ($container, $class, $arguments, $name): object {
                    try {
                        if ($arguments) {
                            if ($container instanceof ServiceManager) {
                                $databaseAdapter = $container->build($class, $arguments);
                            } else {
                                throw new Exception\LogicException(
                                    'Adapter arguments not allowed if not using ' . ServiceManager::class . ' container.'
                                );
                            }
                        } else {
                            $databaseAdapter = $container->get($class);
                        }
                    } catch (ServiceNotCreatedException $e) {
                        throw new Exception\RuntimeException("Error in database/importexport_adapters/$name configuration");
                    }
                    if ($databaseAdapter instanceof RuntimeRequirementsInterface) {
                        $container->get(RequirementChecker::class)->assertUsable(
                            sprintf('database import/export adapter "%s" (database.importexport_adapters.%s)', $name, $name),
                            $databaseAdapter,
                        );
                    }

                    return $databaseAdapter;
                };
            }
        }
        return new DatabaseImportExportAdapterManager($databaseAdapters);
    }
}

