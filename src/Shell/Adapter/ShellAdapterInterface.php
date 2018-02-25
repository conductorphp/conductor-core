<?php
/**
 * @author Kirk Madera <kmadera@robofirm.com>
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
    const PRIORITY_LOW = 0;
    const PRIORITY_NORMAL = 1;
    const PRIORITY_HIGH = 2;

    /**
     * @param string $command
     *
     * @return bool
     */
    public function isCallable($command): bool;

    /**
     * @param            $command
     * @param null       $currentWorkingDirectory Current working directory to run command from
     * @param array|null $environmentVariables   Environment variables to run command with
     * @param array|null $options                 Additional options
     *
     * @throws Exception\RuntimeException if command exits with non-zero status
     * @return string Standard output from the command
     */
    public function runShellCommand(
        string $command,
        int $priority = self::PRIORITY_NORMAL,
        string $currentWorkingDirectory = null,
        array $environmentVariables = null,
        array $options = null
    ): string;
}
