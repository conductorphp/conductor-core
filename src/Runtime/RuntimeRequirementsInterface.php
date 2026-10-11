<?php

declare(strict_types=1);

namespace ConductorCore\Runtime;

/**
 * Implemented by an adapter that needs an extension or a command from the host.
 *
 * The adapter managers check the declaration when configuration selects the adapter, so an adapter
 * that is configured but never selected costs nothing, and the refusal names what to install.
 */
interface RuntimeRequirementsInterface
{
    /** @return list<Requirement> */
    public function runtimeRequirements(): array;
}
