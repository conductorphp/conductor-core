<?php

namespace ConductorCore\Shell\Adapter;

use ConductorCore\Exception;
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

        $this->logger->debug("Running shell command: $command");
        $command = 'bash -c ' . escapeshellarg($command);
        if (ShellAdapterInterface::PRIORITY_LOW === $priority) {
            $command = 'ionice -c3 nice -n 19 ' . $command;
        } elseif (ShellAdapterInterface::PRIORITY_HIGH === $priority) {
            if (0 === posix_getuid()) {
                $command = 'ionice -c 1 -n 0 ' . $command;
            } else {
                $command = 'ionice -c 2 -n 0 ' . $command;
            }
        }

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
            throw new Exception\RuntimeException(sprintf('Failed to open process for command "%s".', $command));
        }

        // Allow for input?
        //fwrite($pipes[0], ' ');

        $logger = $this->logger;
        EventLoop::onReadable(
            $pipes[2],
            static function (string $callbackId, $socket) use ($logger) {
                $line = fgets($socket);
                if ($line) {
                    $logger->debug($line);
                } elseif (!is_resource($socket) || feof($socket)) {
                    EventLoop::cancel($callbackId);
                }
            }
        );

        $output = '';
        EventLoop::onReadable(
            $pipes[1],
            static function (string $callbackId, $socket) use (&$output) {
                $line = fgets($socket);
                if ($line) {
                    $output .= $line;
                } elseif (!is_resource($socket) || feof($socket)) {
                    EventLoop::cancel($callbackId);
                }
            }
        );

        EventLoop::run();

        $output .= stream_get_contents($pipes[1]);
        $remainingStderr = stream_get_contents($pipes[2]);
        if ($remainingStderr) {
            $this->logger->debug($remainingStderr);
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_close($process);
        if ($status > 0 && $status <= 255) {
            throw new Exception\RuntimeException(
                "An error occurred while running shell command: \"$command\"\nOutput: $output"
            );
        }

        return $output;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
