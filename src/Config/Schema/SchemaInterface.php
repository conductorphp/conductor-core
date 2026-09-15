<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema;

/**
 * One node in a config schema: validates a value, applies defaults, and returns the transformed
 * result.
 *
 * Deliberately small. This exists so a package's `Config` object can declare the shape it expects
 * once, instead of every reader repeating `?? null` and `is_array()` guards — see
 * {@see \ConductorCore\Config\ParsesConfigTrait}. It is not a general-purpose validation library and
 * should not grow into one: conductor validates config trees, not user input.
 */
interface SchemaInterface
{
    /**
     * @param mixed  $value The raw config value, or {@see Undefined::VALUE} when the key is absent —
     *     which is how a node tells "explicitly null" apart from "not provided".
     * @param string $path  Dot-separated path to this node, for error messages.
     */
    public function parse(mixed $value, string $path = ''): ParseResult;
}
