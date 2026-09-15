<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema\Node;

use ConductorCore\Config\Schema\ParseResult;

/** Any value at all — for a config slot that genuinely accepts anything, e.g. a template var. */
final class AnyNode extends AbstractNode
{
    protected function parseValue(mixed $value, string $path): ParseResult
    {
        return ParseResult::valid($value);
    }
}
