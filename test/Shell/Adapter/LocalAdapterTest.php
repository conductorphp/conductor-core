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
     * Each call registers its own event loop callbacks; consecutive calls must not
     * leak state from the previous run into the next.
     */
    public function testConsecutiveRunsAreIndependent()
    {
        $this->assertSame("first\n", $this->adapter->runShellCommand('echo first'));
        $this->assertSame("second\n", $this->adapter->runShellCommand('echo second'));
    }
}
