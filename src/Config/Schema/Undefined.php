<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema;

/**
 * Marker for "this key was not present at all".
 *
 * A config schema has to tell an absent key from one explicitly set to null: the first takes the
 * default, the second is a deliberate null that a nullable node should keep. `?? null` collapses
 * both, which is exactly the ambiguity this layer exists to remove.
 */
enum Undefined
{
    case VALUE;
}
