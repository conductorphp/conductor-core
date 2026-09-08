<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema\Node;

use ConductorCore\Config\Schema\ParseResult;

use function get_debug_type;
use function is_int;
use function is_string;
use function octdec;
use function preg_match;

/**
 * A filesystem permission mode, normalized to the int that `chmod()` and `mkdir()` want.
 *
 * Config carries these two ways and they mean the same thing, but they need OPPOSITE handling:
 *
 * | config value | is already | conversion |
 * |---|---|---|
 * | `0750` (PHP octal int literal) | 488 decimal, i.e. the mode | none — use as-is |
 * | `'0750'` (string, from YAML or an env var) | four characters | `octdec()` |
 *
 * Getting it backwards is silent and wrong in both directions: `(int) '0750'` is 750 decimal, which
 * is mode 1366; `octdec('488')` is 4, because `octdec()` ignores the digits 8 and 9. So the raw type
 * has to be inspected before any coercion, which is why this cannot be a string or int node with a
 * transformer attached.
 */
final class FileModeNode extends AbstractNode
{
    protected function parseValue(mixed $value, string $path): ParseResult
    {
        if (is_int($value)) {
            return ParseResult::valid($value);
        }

        if (is_string($value)) {
            if (preg_match('/^0?[0-7]{3,4}$/', $value) !== 1) {
                return ParseResult::error(
                    $path,
                    "must be an octal permission mode such as '0750', got '$value'"
                );
            }

            return ParseResult::valid((int) octdec($value));
        }

        return ParseResult::error(
            $path,
            'must be an octal permission mode, got ' . get_debug_type($value)
        );
    }
}
