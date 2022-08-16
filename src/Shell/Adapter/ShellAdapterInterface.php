<?php
/**
 * @author Kirk Madera <kirk.madera@rmgmedia.com>
 */

namespace ConductorCore\Shell\Adapter;

use ConductorCore\Exception;

/**
 * Class LocalShellAdapter
 *
 * @package ConductorCore
 */
interface ShellAdapterInterface
{
    const PRIORITY_LOW = -1;
    const PRIORITY_NORMAL = 0;
    const PRIORITY_HIGH = 1;

    /**
     * @param string $command
     *
     * @return bool
     */
    public function isCallable($command): bool;

    /**
     * @param            $command
     * @param null       $currentWorkingDirectory Current working directory to run command from
     * @param array|null $environmentVariables    Environment variables to run command with
     * @param int        $priority                Relative priority to run the command with. Possible values are -1
     *                                            (low), 0 (normal), or 1 (high)
     * @param array|null $options                 Additional options
     *
     * @throws Exception\RuntimeException if command exits with non-zero status
     * @return string Standard output from the command
     */
    public function runShellCommand(
        string $command,
        string $currentWorkingDirectory = null,
        array $environmentVariables = null,
        int $priority = self::PRIORITY_NORMAL,
        array $options = null
    ): string;
}
