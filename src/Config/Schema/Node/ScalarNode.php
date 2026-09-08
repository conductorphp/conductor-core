<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema\Node;

use ConductorCore\Config\Schema\ParseResult;

use function filter_var;
use function get_debug_type;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function trim;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * A string, int, float or bool.
 *
 * Coercion is deliberate but narrow. Config comes from PHP files, YAML and environment variables, so
 * `port: "22"` and `port: 22` both occur in the wild and both mean 22 — refusing the string would be
 * pedantry. What it will not do is coerce something meaningless (an array to a string, `"abc"` to an
 * int); those are config mistakes and must surface as errors.
 */
final class ScalarNode extends AbstractNode
{
    public const TYPE_STRING = 'string';
    public const TYPE_INT    = 'int';
    public const TYPE_FLOAT  = 'float';
    public const TYPE_BOOL   = 'bool';

    private bool $trim = false;
    private bool $allowEmpty = true;

    public function __construct(private readonly string $type)
    {
    }

    /** Trim surrounding whitespace. Strings only. */
    public function trim(): self
    {
        $this->trim = true;

        return $this;
    }

    /** Reject `''`. Use where an empty string would silently read as "not configured". */
    public function notEmpty(): self
    {
        $this->allowEmpty = false;

        return $this;
    }

    protected function parseValue(mixed $value, string $path): ParseResult
    {
        return match ($this->type) {
            self::TYPE_STRING => $this->parseString($value, $path),
            self::TYPE_INT    => $this->parseInt($value, $path),
            self::TYPE_FLOAT  => $this->parseFloat($value, $path),
            self::TYPE_BOOL   => $this->parseBool($value, $path),
        };
    }

    private function parseString(mixed $value, string $path): ParseResult
    {
        if (is_string($value)) {
            $string = $this->trim ? trim($value) : $value;
        } elseif (is_int($value) || is_float($value)) {
            $string = (string) $value;
        } else {
            return ParseResult::error($path, 'must be a string, got ' . get_debug_type($value));
        }

        if (! $this->allowEmpty && $string === '') {
            return ParseResult::error($path, 'must not be empty');
        }

        return ParseResult::valid($string);
    }

    private function parseInt(mixed $value, string $path): ParseResult
    {
        if (is_int($value)) {
            return ParseResult::valid($value);
        }

        // is_numeric first: (int) "abc" is 0, which would turn a typo into a plausible value.
        if (is_string($value) && is_numeric($value) && (float) $value === (float) (int) $value) {
            return ParseResult::valid((int) $value);
        }

        return ParseResult::error($path, 'must be an integer, got ' . get_debug_type($value));
    }

    private function parseFloat(mixed $value, string $path): ParseResult
    {
        if (is_float($value) || is_int($value)) {
            return ParseResult::valid((float) $value);
        }

        if (is_string($value) && is_numeric($value)) {
            return ParseResult::valid((float) $value);
        }

        return ParseResult::error($path, 'must be a number, got ' . get_debug_type($value));
    }

    private function parseBool(mixed $value, string $path): ParseResult
    {
        if (is_bool($value)) {
            return ParseResult::valid($value);
        }

        // Accepts the usual config spellings: "true"/"false", "yes"/"no", "on"/"off", 1/0.
        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($filtered !== null) {
            return ParseResult::valid($filtered);
        }

        return ParseResult::error($path, 'must be a boolean, got ' . get_debug_type($value));
    }
}
