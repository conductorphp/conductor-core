<?php

namespace ConductorCore\Exception;

use Throwable;

/**
 * A shell command exited with a non-zero status.
 *
 * The message keeps its historical "An error occurred while running shell command …" shape for
 * callers that parse it. The command, the exit status and what the child wrote to each stream
 * are carried as properties as well, so a caller can report the failure in full instead of
 * picking the message apart or losing stderr altogether (CTAP-2006).
 */
class ShellCommandFailedException extends RuntimeException
{
    public function __construct(
        private readonly string $command,
        private readonly int $exitStatus,
        private readonly string $stdout = '',
        private readonly string $stderr = '',
        ?string $message = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?? sprintf("An error occurred while running shell command: \"%s\"\nOutput: %s", $command, $stdout),
            $exitStatus,
            $previous
        );
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getExitStatus(): int
    {
        return $this->exitStatus;
    }

    /** Everything the command wrote to stdout before it exited. */
    public function getStdout(): string
    {
        return $this->stdout;
    }

    /** Everything the command wrote to stderr before it exited. */
    public function getStderr(): string
    {
        return $this->stderr;
    }
}
