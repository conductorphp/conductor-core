<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema\Node;

use ConductorCore\Config\Schema\ParseResult;
use ConductorCore\Config\Schema\SchemaInterface;

use function array_merge;
use function get_debug_type;
use function is_array;

/**
 * A collection whose keys are not known up front — every entry validated against one schema.
 *
 * This covers most of conductor's config: `plans`, `databases`, `servers`, `template_vars` are all
 * "a map of names to things of the same shape", where the names belong to the project. An optional
 * key schema validates the names themselves.
 */
final class CollectionNode extends AbstractNode
{
    public function __construct(
        private readonly SchemaInterface $values,
        private readonly ?SchemaInterface $keys = null,
    ) {
    }

    protected function parseValue(mixed $value, string $path): ParseResult
    {
        if (! is_array($value)) {
            return ParseResult::error($path, 'must be a list or map, got ' . get_debug_type($value));
        }

        $parsed = [];
        $errors = [];

        foreach ($value as $key => $entry) {
            $childPath = $path === '' ? (string) $key : "$path.$key";
            $parsedKey = $key;

            if ($this->keys !== null) {
                $keyResult = $this->keys->parse($key, "$childPath (key)");
                if (! $keyResult->isValid()) {
                    $errors = array_merge($errors, $keyResult->errors);
                    continue;
                }

                $parsedKey = $keyResult->value;
            }

            $result = $this->values->parse($entry, $childPath);
            if (! $result->isValid()) {
                $errors = array_merge($errors, $result->errors);
                continue;
            }

            $parsed[$parsedKey] = $result->value;
        }

        return $errors === [] ? ParseResult::valid($parsed) : ParseResult::errors($errors);
    }
}
