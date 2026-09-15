<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema\Node;

use ConductorCore\Config\Schema\ParseResult;

use function array_map;
use function get_debug_type;
use function implode;
use function in_array;
use function is_scalar;
use function var_export;

/**
 * One of a fixed set of allowed values, listing them when it does not match.
 *
 * The error naming the valid options is the point. `ApplicationConfig::validate()` used to throw
 * `Invalid file layout "blugreen".` and leave you to find the two acceptable spellings in the source.
 */
final class EnumNode extends AbstractNode
{
    /** @param list<mixed> $allowed */
    public function __construct(private readonly array $allowed)
    {
    }

    protected function parseValue(mixed $value, string $path): ParseResult
    {
        if (in_array($value, $this->allowed, true)) {
            return ParseResult::valid($value);
        }

        $given = is_scalar($value) ? var_export($value, true) : get_debug_type($value);

        return ParseResult::error(
            $path,
            "must be one of: " . implode(', ', array_map(
                static fn(mixed $option): string => var_export($option, true),
                $this->allowed
            )) . ", got $given"
        );
    }
}
