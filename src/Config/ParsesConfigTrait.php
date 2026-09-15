<?php

declare(strict_types=1);

namespace ConductorCore\Config;

use ConductorCore\Config\Schema\SchemaInterface;
use ConductorCore\Exception\InvalidConfigException;

use function count;
use function implode;
use function sprintf;

/**
 * The parse-once step every Config object repeats: validate a raw config sub-tree against a schema
 * and throw a consistent {@see InvalidConfigException} naming the config path, or hand back the
 * schema's transformed value for the Config to assign to its own properties.
 *
 * A trait rather than a base class precisely because a Config is `readonly`: readonly properties can
 * only be initialized inside the declaring class's own constructor, so each Config must assign its
 * own. The trait owns the validation so that part can no longer drift Config to Config.
 *
 * A conforming Config:
 *
 *     final readonly class ThingConfig
 *     {
 *         use ParsesConfigTrait;
 *
 *         public const CONFIG_KEY = 'thing';
 *
 *         public array $connections;
 *
 *         public function __construct(?array $config)
 *         {
 *             $parsed            = $this->parseConfig($config, $this->schema(), self::CONFIG_KEY);
 *             $this->connections = $parsed['connections'] ?? [];
 *         }
 *
 *         private function schema(): SchemaInterface { ... }
 *     }
 */
trait ParsesConfigTrait
{
    /**
     * @param array<string, mixed>|null $config     Raw config sub-tree, or null when absent.
     * @param non-empty-string          $configPath Key path, e.g. `application_orchestration`, named
     *     in the error so a reader knows which file to open.
     * @return array<string, mixed> The schema's transformed value; `[]` when $config is null.
     * @throws InvalidConfigException When the config does not satisfy the schema.
     */
    private function parseConfig(?array $config, SchemaInterface $schema, string $configPath): array
    {
        if ($config === null) {
            return [];
        }

        $result = $schema->parse($config);
        if ($result->isValid()) {
            /** @var array<string, mixed> $value */
            $value = $result->value;

            return $value;
        }

        throw new InvalidConfigException(sprintf(
            "Invalid configuration for \"%s\" (%d problem%s):\n\n  - %s\n",
            $configPath,
            count($result->errors),
            count($result->errors) === 1 ? '' : 's',
            implode("\n  - ", $result->errors),
        ));
    }
}
