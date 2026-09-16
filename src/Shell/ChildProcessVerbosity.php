<?php

namespace ConductorCore\Shell;

/**
 * Decides how much of conductor's own verbosity a child process inherits.
 *
 * Symfony Console exports SHELL_VERBOSITY into the process environment for every -q/-v flag, and a
 * shell command started from that process inherits it. Left alone, `conductor app:deploy -vv` runs
 * every step's console command at -vv as well, and a deploy log becomes the union of every child's
 * verbose output: composer's dependency solver, one line per installed entity, full status tables.
 *
 * The rule here: quiet and debug pass through, everything in between runs the child at its own
 * default. At -q nothing should talk; at -vvv everything should. At -v and -vv the operator wants
 * conductor's view of the deploy (plan, steps, what each step printed), not each step's own
 * diagnostics — those are one `-vvv` away, or a hand-run of the step's command.
 *
 * | conductor        | SHELL_VERBOSITY | child            |
 * |------------------|-----------------|------------------|
 * | -q               | -1              | -q               |
 * | (none), -v, -vv  | 0, 1, 2         | default (unset)  |
 * | -vvv             | 3               | -vvv             |
 */
final class ChildProcessVerbosity
{
    public const ENV_VAR = 'SHELL_VERBOSITY';

    /** Symfony's OutputInterface::VERBOSITY_QUIET as it appears in SHELL_VERBOSITY. */
    private const QUIET = -1;

    /** Symfony's OutputInterface::VERBOSITY_DEBUG as it appears in SHELL_VERBOSITY. */
    private const DEBUG = 3;

    /**
     * Returns the environment a child process should start with.
     *
     * The input is conductor's own environment (typically `getenv()`), so an explicit per-step
     * override should be layered on top of the result, not passed through here — a plan author who
     * sets SHELL_VERBOSITY on a step has asked for exactly that level.
     *
     * @param array<string, string> $environment
     * @return array<string, string>
     */
    public static function forChild(array $environment): array
    {
        if (!array_key_exists(self::ENV_VAR, $environment)) {
            return $environment;
        }

        $level = (int) $environment[self::ENV_VAR];
        if ($level <= self::QUIET || $level >= self::DEBUG) {
            return $environment;
        }

        unset($environment[self::ENV_VAR]);

        return $environment;
    }
}
