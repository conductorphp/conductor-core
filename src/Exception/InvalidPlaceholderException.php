<?php

declare(strict_types=1);

namespace ConductorCore\Exception;

use function implode;
use function sprintf;

/**
 * A `${NAME…}` placeholder is malformed: it named a filter that does not exist, its value could not
 * pass through the filter — a `b64decode` on something that is not base64 — or it used a modifier
 * the interpolator does not support, such as the shell's `${NAME:=x}` (CTAP-1984).
 *
 * Distinct from {@see UndefinedVariableException}: whether or not the variable is defined, the
 * placeholder around it is wrong. Raised at config load, before any plan step runs, naming the
 * variable and config path, so the deploy stops here rather than writing a truncated key an hour in.
 */
class InvalidPlaceholderException extends InvalidConfigException
{
    /** The forms {@see \ConductorCore\Config\EnvVarInterpolator} accepts, for the error text. */
    public const SUPPORTED_FORMS = '${NAME}, ${NAME|filter}, ${NAME:-default}, ${NAME|filter:-default}';

    public static function unsupportedSyntax(string $placeholder, string $name, string $path, string $reason): self
    {
        return new self(sprintf(
            'Unsupported placeholder "%s" for variable "%s" at "%s": %s. Supported forms: %s;'
            . ' write "$%s" where the literal text is intended.',
            $placeholder,
            $name,
            self::path($path),
            $reason,
            self::SUPPORTED_FORMS,
            $placeholder,
        ));
    }

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
