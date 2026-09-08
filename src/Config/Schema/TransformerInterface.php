<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema;

/**
 * Turns a validated raw value into whatever the Config object wants to hold — most usefully a typed
 * sub-DTO, so a nested config tree becomes nested objects rather than nested arrays.
 *
 * A one-off transform can be passed as a plain callable instead; implement this when the same
 * transform is used by more than one schema.
 */
interface TransformerInterface
{
    public function transform(mixed $value): mixed;
}
