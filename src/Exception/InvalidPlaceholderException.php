<?php

declare(strict_types=1);

namespace ConductorCore\Exception;

use function implode;
use function sprintf;

/**
 * A `${NAME|filter}` placeholder named a filter that does not exist, or its value could not pass
 * through the filter — a `b64decode` on something that is not base64.
 *
 * Distinct from {@see UndefinedVariableException}: the variable IS defined, the placeholder around it
 * is wrong. Raised at config load, before any plan step runs, naming the variable and config path, so
 * the deploy stops here rather than writing a truncated key an hour in.
 */
class InvalidPlaceholderException extends InvalidConfigException
{
    /** @param list<string> $knownFilters */
    public static function unknownFilter(string $filter, string $name, string $path, array $knownFilters): self
    {
        return new self(sprintf(
            'Unknown filter "%s" in placeholder "${%s|%s}" at "%s". Known filters: %s.',
            $filter,
            $name,
            $filter,
            self::path($path),
            implode(', ', $knownFilters),
        ));
    }

    public static function filterFailed(string $filter, string $name, string $path, string $reason): self
    {
        return new self(sprintf(
            'Filter "%s" failed for variable "%s" at "%s": %s',
            $filter,
            $name,
            self::path($path),
            $reason,
        ));
    }

    private static function path(string $path): string
    {
        return $path === '' ? '(root)' : $path;
    }
}
