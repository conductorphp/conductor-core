<?php

declare(strict_types=1);

namespace ConductorCore\Runtime;

use ConductorCore\Exception\RuntimeRequirementException;
use ConductorCore\Shell\Adapter\ShellAdapterInterface;

use function array_map;
use function implode;
use function sprintf;

final class RequirementChecker
{
    public function __construct(private readonly ShellAdapterInterface $shell)
    {
    }

    /**
     * @param iterable<Requirement> $requirements
     * @return list<Requirement> The ones the host does not satisfy
     */
    public function missing(iterable $requirements): array
    {
        $missing = [];
        foreach ($requirements as $requirement) {
            if (!$requirement->isSatisfied($this->shell)) {
                $missing[] = $requirement;
            }
        }

        return $missing;
    }

    /**
     * @param string $subject What is being refused, with the config key that selected it, e.g.
     *        `database import/export adapter "default" (database.importexport_adapters.default)`
     * @param iterable<Requirement> $requirements
     * @throws RuntimeRequirementException Listing every missing requirement, not only the first
     */
    public function assert(string $subject, iterable $requirements): void
    {
        $missing = $this->missing($requirements);
        if ($missing === []) {
            return;
        }

        throw new RuntimeRequirementException(sprintf(
            '%s needs %s. Install it where conductor runs the command, or select another adapter.',
            $subject,
            implode(', ', array_map(static fn(Requirement $r): string => $r->describe(), $missing)),
        ));
    }

    /** Checks the candidate when it declares requirements; a no-op for anything else. */
    public function assertUsable(string $subject, object $candidate): void
    {
        if ($candidate instanceof RuntimeRequirementsInterface) {
            $this->assert($subject, $candidate->runtimeRequirements());
        }
    }
}
