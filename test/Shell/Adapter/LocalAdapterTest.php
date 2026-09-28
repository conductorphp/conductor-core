<?php

namespace ConductorCoreTest;

use ConductorCore\Exception;
use ConductorCore\Shell\Adapter\LocalShellAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

class LocalAdapterTest extends TestCase
{
    /**
     * @var LocalShellAdapter
     */
    private $adapter;

    public function setUp(): void
    {
        $this->adapter = new LocalShellAdapter();
    }

    public function testIsCallableValidCommand()
    {
        $this->assertTrue($this->adapter->isCallable('ls'));
    }

    public function testIsCallableInvalidCommand()
    {
        $this->assertFalse($this->adapter->isCallable('badcommand'));
    }

    public function testRunShellCommand()
    {
        $this->assertIsString($this->adapter->runShellCommand('ls'));
    }

    /**
     * Symfony exports SHELL_VERBOSITY for conductor's own -v/-vv, and a child would inherit it and
     * run just as loud. Below debug the child runs at its own default instead.
     */
    public function testChildProcessesRunAtTheirDefaultVerbosityBelowDebug(): void
    {
        $this->withShellVerbosity('2', function (): void {
            $this->assertSame(
                "unset\n",
                $this->adapter->runShellCommand('echo "${SHELL_VERBOSITY:-unset}"')
            );
        });
    }

    public function testDebugVerbosityReachesChildProcesses(): void
    {
        $this->withShellVerbosity('3', function (): void {
            $this->assertSame("3\n", $this->adapter->runShellCommand('echo "${SHELL_VERBOSITY:-unset}"'));
        });
    }

    /** A caller who builds the environment has decided what the child gets. */
    public function testAnExplicitEnvironmentIsPassedThroughUnchanged(): void
    {
        $this->withShellVerbosity('3', function (): void {
            $output = $this->adapter->runShellCommand(
                'echo "${SHELL_VERBOSITY:-unset}"',
                null,
                ['PATH' => getenv('PATH'), 'SHELL_VERBOSITY' => '2']
            );
            $this->assertSame("2\n", $output);
        });
    }

    private function recordingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            public array $messages = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };
    }

    private function withShellVerbosity(string $level, callable $test): void
    {
        $previous = getenv('SHELL_VERBOSITY');
        putenv('SHELL_VERBOSITY=' . $level);
        try {
            $test();
        } finally {
            putenv(false === $previous ? 'SHELL_VERBOSITY' : 'SHELL_VERBOSITY=' . $previous);
        }
    }

    public function testRunShellCommandReturnsStdout()
    {
        $this->assertSame("hello\n", $this->adapter->runShellCommand('echo hello'));
    }

    /**
     * The adapter drains stdout through the event loop one line at a time, so output
     * large enough to exceed the pipe buffer is the case most likely to regress.
     */
    public function testRunShellCommandCapturesOutputLargerThanThePipeBuffer()
    {
        $lines = 20000;
        $output = $this->adapter->runShellCommand(sprintf('seq 1 %d', $lines));

        $this->assertSame($lines, substr_count($output, "\n"));
        $this->assertStringStartsWith("1\n", $output);
        $this->assertStringEndsWith("$lines\n", $output);
    }

    public function testRunShellCommandSendsStderrToTheLogger()
    {
        $logger = $this->recordingLogger();
        $this->adapter->setLogger($logger);
        $output = $this->adapter->runShellCommand('echo out; echo err 1>&2');

        $this->assertSame("out\n", $output);
        $this->assertContains("err\n", $logger->messages);
    }

    /**
     * A Symfony Console progress bar redraws with `\r` on stderr, so the child leaves a partial
     * line there and carries on writing stdout. Reading either pipe a line at a time blocked on
     * the partial stderr line while stdout filled the pipe buffer, and the child blocked in turn
     * (CTAP-1946). The command runs under `timeout` so a regression fails instead of hanging.
     */
    public function testAPartialStderrLineDoesNotStallALargeStdout(): void
    {
        $logger = $this->recordingLogger();
        $this->adapter->setLogger($logger);

        $output = $this->adapter->runShellCommand(
            "timeout 10 bash -c \"printf 'partial' >&2; head -c 200000 /dev/zero | tr '\\\\0' a; echo >&2\""
        );

        $this->assertSame(str_repeat('a', 200000), $output);
        $this->assertContains("partial\n", $logger->messages);
    }

    public function testAPartialStderrLineAtExitIsStillLogged(): void
    {
        $logger = $this->recordingLogger();
        $this->adapter->setLogger($logger);

        $this->adapter->runShellCommand("printf 'first\\nlast' >&2");

        $this->assertContains("first\n", $logger->messages);
        $this->assertContains('last', $logger->messages);
    }

    public function testInterleavedOutputOnBothStreamsIsKeptApart(): void
    {
        $logger = $this->recordingLogger();
        $this->adapter->setLogger($logger);

        $output = $this->adapter->runShellCommand(
            'timeout 10 bash -c \'for i in $(seq 1 5000); do echo "out $i"; echo "err $i" >&2; done\''
        );

        $expected = implode('', array_map(static fn (int $i): string => "out $i\n", range(1, 5000)));
        $this->assertSame($expected, $output);
        $stderrLines = array_values(array_filter(
            $logger->messages,
            static fn (string $message): bool => str_starts_with($message, 'err ')
        ));
        $this->assertSame(array_map(static fn (int $i): string => "err $i\n", range(1, 5000)), $stderrLines);
    }

    public function testANonZeroExitAfterLargeOutputThrowsWithTheOutput(): void
    {
        try {
            $this->adapter->runShellCommand(
                "timeout 10 bash -c \"printf 'partial' >&2; head -c 100000 /dev/zero | tr '\\\\0' a; exit 3\""
            );
            $this->fail('Expected a ShellCommandFailedException.');
        } catch (Exception\ShellCommandFailedException $exception) {
            $this->assertStringContainsString(str_repeat('a', 100000), $exception->getMessage());
            $this->assertSame(3, $exception->getExitStatus());
            $this->assertSame(str_repeat('a', 100000), $exception->getStdout());
            $this->assertSame('partial', $exception->getStderr());
        }
    }

    /**
     * stderr is streamed at DEBUG as it arrives, which is gone at default verbosity. A failure
     * carries both streams and the exit status on the exception, so the caller can report them
     * without picking the message apart (CTAP-2006). The message itself keeps its old shape.
     */
    public function testANonZeroExitCarriesBothStreamsAndTheStatus(): void
    {
        try {
            $this->adapter->runShellCommand('echo out; echo err >&2; exit 3');
            $this->fail('Expected a ShellCommandFailedException.');
        } catch (Exception\ShellCommandFailedException $exception) {
            $this->assertSame(3, $exception->getExitStatus());
            $this->assertSame("out\n", $exception->getStdout());
            $this->assertSame("err\n", $exception->getStderr());
            $this->assertStringStartsWith('An error occurred while running shell command: "', $exception->getMessage());
            $this->assertStringEndsWith("\nOutput: out\n", $exception->getMessage());
        }
    }

    public function testAFailureStillStreamsStderrToTheLoggerAsItArrives(): void
    {
        $logger = $this->recordingLogger();
        $this->adapter->setLogger($logger);

        try {
            $this->adapter->runShellCommand("echo first >&2; printf 'partial' >&2; exit 1");
            $this->fail('Expected a ShellCommandFailedException.');
        } catch (Exception\ShellCommandFailedException $exception) {
            $this->assertSame("first\npartial", $exception->getStderr());
        }

        $this->assertContains("first\n", $logger->messages);
        $this->assertContains('partial', $logger->messages);
    }

    public function testRunShellCommandThrowsExceptionOnError()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->adapter->runShellCommand('badcommand');
    }

    public function testRunShellCommandThrowsExceptionOnNonZeroExitCode()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->adapter->runShellCommand('exit 3');
    }

    /**
     * The three probes below are the point of running steps under
     * `bash -Eeuo pipefail -c` (CTAP-1712). Under a bare `bash -c` each of them
     * exits 0, so a failing statement in the middle of a multi-line plan step is
     * silently ignored and only the last statement decides whether a deploy
     * continues.
     */
    public function testRunShellCommandFailsOnAFailingIntermediateStatement()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->adapter->runShellCommand("false\necho reached");
    }

    public function testRunShellCommandFailsOnAnUnsetVariableExpansion()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->adapter->runShellCommand('echo "${CONDUCTOR_TEST_UNSET_VARIABLE}"');
    }

    public function testRunShellCommandFailsOnAFailureInsideAPipeline()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->adapter->runShellCommand('sh -c "exit 3" | cat');
    }

    /**
     * A guard that does not fire is the last statement's exit status, and an
     * unfired `[[ … ]] && cmd` is a failing step under both shells. Pinned here
     * because it looks like something strict mode introduced and is not — see
     * CTAP-1711.
     */
    public function testRunShellCommandStillFailsOnAnUnfiredTrailingGuard()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->adapter->runShellCommand('[[ -f /nonexistent ]] && echo x');
    }

    /**
     * `pipefail` makes a SIGPIPE'd producer a step failure, so the long-standing
     * `… | head -n1` idiom now aborts with exit 141. Deliberate, and the reason
     * such a pipeline has to be written to tolerate the early reader — see
     * CTAP-1710.
     */
    public function testRunShellCommandFailsWhenAPipelineReaderExitsEarly()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->adapter->runShellCommand('seq 1 200000 | head -n1');
    }

    /**
     * The behavior tests above cover errexit, nounset and pipefail. errtrace has
     * no failure of its own to show -- it only decides whether an ERR trap a step
     * sets is inherited by functions and subshells -- so assert the flag set
     * directly rather than contriving a case for it.
     */
    public function testRunShellCommandRunsUnderStrictShellOptions()
    {
        $options = explode(':', trim($this->adapter->runShellCommand('echo "$SHELLOPTS"')));

        $this->assertContains('errexit', $options);
        $this->assertContains('errtrace', $options);
        $this->assertContains('nounset', $options);
        $this->assertContains('pipefail', $options);
    }

    /**
     * Strict mode must not turn an ordinary successful multi-line step into a
     * failure: the statements still run in order and stdout is still returned
     * whole.
     */
    public function testRunShellCommandStillRunsAPassingMultiLineStep()
    {
        $output = $this->adapter->runShellCommand("echo one\necho two\necho three");

        $this->assertSame("one\ntwo\nthree\n", $output);
    }

    /**
     * `-u` must not fire on the guarded-default idiom every plan step already
     * uses for optional variables.
     */
    public function testRunShellCommandAllowsGuardedDefaultsForUnsetVariables()
    {
        $this->assertSame(
            "fallback\n",
            $this->adapter->runShellCommand('echo "${CONDUCTOR_TEST_UNSET_VARIABLE:-fallback}"')
        );
    }

    public function testRunShellCommandPassesEnvironmentVariablesThroughStrictMode()
    {
        $output = $this->adapter->runShellCommand(
            'echo "$CONDUCTOR_TEST_SET_VARIABLE"',
            null,
            ['CONDUCTOR_TEST_SET_VARIABLE' => 'value']
        );

        $this->assertSame("value\n", $output);
    }

    /**
     * Each call registers its own event loop callbacks; consecutive calls must not
     * leak state from the previous run into the next.
     */
    public function testConsecutiveRunsAreIndependent()
    {
        $this->assertSame("first\n", $this->adapter->runShellCommand('echo first'));
        $this->assertSame("second\n", $this->adapter->runShellCommand('echo second'));
    }
}
