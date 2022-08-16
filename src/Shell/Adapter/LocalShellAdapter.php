<?php
/**
 * @author Kirk Madera <kirk.madera@rmgmedia.com>
 */

namespace ConductorCore\Shell\Adapter;

use Amp\Loop;
use ConductorCore\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class LocalShellAdapter
 *
 * @package ConductorCore
 */
class LocalShellAdapter implements ShellAdapterInterface, LoggerAwareInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * LocalShellAdapter constructor.
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function isCallable($command): bool
    {
        exec('which ' . escapeshellarg($command), $output, $return);
        return 0 === $return;
    }

    /**
     * @inheritdoc
     */
    public function runShellCommand(
        string $command,
        string $currentWorkingDirectory = null,
        array  $environmentVariables = null,
        int    $priority = ShellAdapterInterface::PRIORITY_NORMAL,
        array  $options = null
    ): string {

        $this->logger->debug("Running shell command: $command");
        $command = 'bash -c ' . escapeshellarg($command);
        if (ShellAdapterInterface::PRIORITY_LOW == $priority) {
            $command = 'ionice -c3 nice -n 19 ' . $command;
        } elseif (ShellAdapterInterface::PRIORITY_HIGH == $priority) {
            if (0 == posix_getuid()) {
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
        Loop::onReadable(
            $pipes[2],
            function ($watcherId, $socket) use ($logger) {
                $line = fgets($socket);
                if ($line) {
                    $logger->debug($line);
                } elseif (!is_resource($socket) || feof($socket)) {
                    Loop::cancel($watcherId);
                }
            }
        );

        $output = '';
        Loop::onReadable(
            $pipes[1],
            function ($watcherId, $socket) use (&$output) {
                $line = fgets($socket);
                if ($line) {
                    $output .= $line;
                } elseif (!is_resource($socket) || feof($socket)) {
                    Loop::cancel($watcherId);
                }
            }
        );

        Loop::run();

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

    /**
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
