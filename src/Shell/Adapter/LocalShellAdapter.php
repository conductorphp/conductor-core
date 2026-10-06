<?php

namespace ConductorCore\Shell\Adapter;

use ConductorCore\Exception;
use ConductorCore\Shell\ChildProcessVerbosity;
use ConductorCore\Shell\SecretRedactor;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;

class LocalShellAdapter implements ShellAdapterInterface, LoggerAwareInterface
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    public function isCallable($command): bool
    {
        exec('which ' . escapeshellarg($command), $output, $return);
        return 0 === $return;
    }

    public function runShellCommand(
        string  $command,
        ?string $currentWorkingDirectory = null,
        ?array  $environmentVariables = null,
        int     $priority = self::PRIORITY_NORMAL,
        ?array  $options = null
    ): string {

        // Everything this adapter logs or reports is masked: a failed command's message is logged at
        // ERROR, and a password on the command line went with it (CTAP-2218). The command that runs
        // is untouched.
        $this->logger->debug('Running shell command: ' . SecretRedactor::redact($command));
        // A null environment inherits conductor's own, SHELL_VERBOSITY included, which would run the
        // child at conductor's -v/-vv. An explicit environment is the caller's decision and is passed
        // through as given (PlanRunner applies the same policy to its inherited base before layering
        // a step's own variables on top).
        if (null === $environmentVariables) {
            $environmentVariables = ChildProcessVerbosity::forChild(getenv());
        }
        $reportedCommand = $this->wrap(SecretRedactor::redact($command), $priority);
        $command = $this->wrap($command, $priority);

        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];
        $process = proc_open(
            $command,
            $descriptorSpec,
            $pipes,
            $currentWorkingDirectory,
            $environmentVariables,
            $options
        );
        if (!is_resource($process)) {
            throw new Exception\RuntimeException(
                sprintf('Failed to open process for command "%s".', $reportedCommand)
            );
        }

        // Nothing is written to the child's stdin, so close it now rather than leave a reader such
        // as `docker exec -i` waiting on it.
        fclose($pipes[0]);

        // Both pipes are read without blocking, a chunk at a time. A blocking line read on stderr
        // waits for a newline the child may never write while stdout fills its pipe buffer, and
        // the child then blocks writing stdout: a progress bar redrawing with `\r` is enough
        // (CTAP-1946).
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        // stderr is streamed to the logger a line at a time as it arrives, for live progress, and
        // also retained whole: the streamed copy is DEBUG and gone at default verbosity, and a
        // failure has to be able to report what the command said (CTAP-2006).
        $logger = $this->logger;
        $stderr = '';
        $pendingStderrLine = '';
        EventLoop::onReadable(
            $pipes[2],
            static function (string $callbackId, $socket) use ($logger, &$stderr, &$pendingStderrLine) {
                $chunk = fread($socket, 8192);
                if (false !== $chunk && '' !== $chunk) {
                    $stderr .= $chunk;
                    $pendingStderrLine .= $chunk;
                    while (false !== ($end = strpos($pendingStderrLine, "\n"))) {
                        $logger->debug(SecretRedactor::redact(substr($pendingStderrLine, 0, $end + 1)));
                        $pendingStderrLine = substr($pendingStderrLine, $end + 1);
                    }
                } elseif (!is_resource($socket) || feof($socket)) {
                    EventLoop::cancel($callbackId);
                }
            }
        );

        $output = '';
        EventLoop::onReadable(
            $pipes[1],
            static function (string $callbackId, $socket) use (&$output) {
                $chunk = fread($socket, 8192);
                if (false !== $chunk && '' !== $chunk) {
                    $output .= $chunk;
                } elseif (!is_resource($socket) || feof($socket)) {
                    EventLoop::cancel($callbackId);
                }
            }
        );

        EventLoop::run();

        if ('' !== $pendingStderrLine) {
            $this->logger->debug(SecretRedactor::redact($pendingStderrLine));
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_close($process);
        if ($status > 0 && $status <= 255) {
            // The message is the historical one; the streams and status ride along as properties.
            // All of it is for reporting, so all of it is masked.
            throw new Exception\ShellCommandFailedException(
                $reportedCommand,
                $status,
                SecretRedactor::redact($output),
                SecretRedactor::redact($stderr)
            );
        }

        return $output;
    }

    /**
     * Strict mode, so a failing statement in the middle of a multi-line command is a failure rather
     * than being overwritten by the exit status of the last statement alone. -E keeps an ERR trap
     * alive inside functions and subshells.
     */
    private function wrap(string $command, int $priority): string
    {
        $command = 'bash -Eeuo pipefail -c ' . escapeshellarg($command);
        if (ShellAdapterInterface::PRIORITY_LOW === $priority) {
            return 'ionice -c3 nice -n 19 ' . $command;
        }
        if (ShellAdapterInterface::PRIORITY_HIGH === $priority) {
            if (0 === posix_getuid()) {
                return 'ionice -c 1 -n 0 ' . $command;
            }

            return 'ionice -c 2 -n 0 ' . $command;
        }

        return $command;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
