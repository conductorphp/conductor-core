<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema\Node;

use ConductorCore\Config\Schema\ParseResult;
use ConductorCore\Config\Schema\SchemaInterface;
use ConductorCore\Config\Schema\Undefined;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function get_debug_type;
use function implode;
use function is_array;

/**
 * A fixed set of known keys, each with its own schema — the shape of a config section.
 *
 * Unknown keys are IGNORED by default rather than rejected. Conductor merges config from package
 * defaults, platform support packages, the project, and the environment, so a section legitimately
 * carries keys this schema does not describe; failing on them would make every schema a complete
 * inventory of everything anyone ever merges in. Call {@see rejectUnknownKeys()} where the shape
 * really is closed and a stray key means a typo.
 */
final class MapNode extends AbstractNode
{
    private bool $rejectUnknown = false;

    /** @param array<string, SchemaInterface> $shape */
    public function __construct(private readonly array $shape)
    {
    }

    public function rejectUnknownKeys(): self
    {
        $this->rejectUnknown = true;

        return $this;
    }

    protected function parseValue(mixed $value, string $path): ParseResult
    {
        if (! is_array($value)) {
            return ParseResult::error($path, 'must be a map, got ' . get_debug_type($value));
        }

        $parsed = [];
        $errors = [];

        foreach ($this->shape as $key => $schema) {
            $childPath = $path === '' ? (string) $key : "$path.$key";
            $result    = $schema->parse(
                array_key_exists($key, $value) ? $value[$key] : Undefined::VALUE,
                $childPath,
            );

            if (! $result->isValid()) {
                $errors = array_merge($errors, $result->errors);
                continue;
            }

            // Undefined means "absent with no default" — leave the key out entirely so the Config
            // can distinguish it, rather than materializing a null.
            if ($result->value !== Undefined::VALUE) {
                $parsed[$key] = $result->value;
            }
        }

        if ($this->rejectUnknown) {
            $unknown = array_diff(array_keys($value), array_keys($this->shape));
            if ($unknown !== []) {
                $errors[] = ($path === '' ? '' : "$path: ")
                    . 'unknown key(s): ' . implode(', ', $unknown);
            }
        } else {
            // Carry unknown keys through untouched, so merged-in config is not silently dropped.
            foreach ($value as $key => $raw) {
                if (! array_key_exists($key, $this->shape)) {
                    $parsed[$key] = $raw;
                }
            }
        }

        return $errors === [] ? ParseResult::valid($parsed) : ParseResult::errors($errors);
    }
}
