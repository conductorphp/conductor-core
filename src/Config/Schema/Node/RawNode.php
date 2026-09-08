<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema\Node;

use ConductorCore\Config\Schema\ParseResult;

use function get_debug_type;
use function is_array;

/**
 * An array whose interior is deliberately not described yet.
 *
 * The honest middle ground while migrating. `plans` is a deep structure consumed by PlanRunner, not
 * by the Config object; describing it fully belongs with whatever change makes PlanRunner read a
 * typed plan. Until then this asserts the one thing the Config does rely on — that it is an array —
 * and says so, instead of a schema that pretends to more precision than it has.
 */
final class RawNode extends AbstractNode
{
    protected function parseValue(mixed $value, string $path): ParseResult
    {
        if (! is_array($value)) {
            return ParseResult::error($path, 'must be an array, got ' . get_debug_type($value));
        }

        return ParseResult::valid($value);
    }
}
