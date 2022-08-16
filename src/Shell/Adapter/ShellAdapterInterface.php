<?php

namespace ConductorCore\Shell\Adapter;

use ConductorCore\Exception\RuntimeException;

interface ShellAdapterInterface
{
    public const PRIORITY_LOW = -1;
    public const PRIORITY_NORMAL = 0;
    public const PRIORITY_HIGH = 1;

    public function isCallable(string $command): bool;

    /**
     * @param string $command
     * @param string|null $currentWorkingDirectory Current working directory to run command from
     * @param array|null $environmentVariables Environment variables to run command with
     * @param int $priority Relative priority to run the command with. Possible values are -1
     *                                            (low), 0 (normal), or 1 (high)
     * @param array|null $options Additional options
     *
     * @return string Standard output from the command
     * @throws RuntimeException if command exits with non-zero status
     */
    public function runShellCommand(
        string  $command,
        ?string $currentWorkingDirectory = null,
        ?array  $environmentVariables = null,
        int     $priority = self::PRIORITY_NORMAL,
        ?array  $options = null
    ): string;
}
