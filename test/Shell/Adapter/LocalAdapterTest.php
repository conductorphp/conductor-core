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
        $logger = new class extends AbstractLogger {
            public array $messages = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $this->adapter->setLogger($logger);
        $output = $this->adapter->runShellCommand('echo out; echo err 1>&2');

        $this->assertSame("out\n", $output);
        $this->assertContains("err\n", $logger->messages);
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
