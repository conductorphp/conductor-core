<?php

namespace ConductorCoreTest;

use ConductorCore\Exception\RuntimeException;
use ConductorCore\ForkManager;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Workers run in forked children. A child must exit whatever its worker does: if it returned into the caller it
 * would run the rest of the deploy plan concurrently with the parent (CTAP-1989). The parent must notice every
 * failed child, not just whichever one it happened to reap last.
 */
#[RequiresPhpExtension('pcntl')]
class ForkManagerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/fork-manager-test-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testAThrowingWorkerExitsItsChildAndFailsTheParentOnce(): void
    {
        $forkManager = new ForkManager($this->fileLogger());
        $forkManager->addWorker(fn() => $this->touch('worker-0'));
        $forkManager->addWorker(function (): void {
            throw new \LogicException('boom');
        });
        $forkManager->addWorker(fn() => $this->touch('worker-2'));

        $failure = null;
        try {
            $forkManager->execute();
        } catch (RuntimeException $e) {
            $failure = $e;
        }
        // Whoever gets here writes its pid down. Only the parent may.
        file_put_contents($this->dir . '/after-execute', getmypid() . "\n", FILE_APPEND);
        usleep(200_000); // give an escaped child the chance to write too

        $this->assertSame([(string) getmypid()], $this->lines('after-execute'), 'code after execute() ran in a child');
        $this->assertNotNull($failure, 'the parent must throw when a worker failed');
        $this->assertMatchesRegularExpression('/^1 worker process\(es\) failed \(pid \d+\)\.$/', $failure->getMessage());
        $this->assertFileExists($this->dir . '/worker-0');
        $this->assertFileExists($this->dir . '/worker-2');

        $log = $this->log();
        $this->assertMatchesRegularExpression('/error: Worker \d+ failed: boom \(LogicException\)/', $log, 'the child logs why');
        $this->assertMatchesRegularExpression('/error: Worker \d+ failed: exited with status 1\./', $log, 'the parent logs which');
    }

    public function testEveryFailedWorkerIsCountedNotJustTheLastOneReaped(): void
    {
        $forkManager = new ForkManager($this->fileLogger());
        // The failure finishes first; the successes finish last so a "last status wins" parent would miss it.
        $forkManager->addWorker(function (): void {
            exit(3);
        });
        $forkManager->addWorker(function (): void {
            usleep(300_000);
        });
        $forkManager->addWorker(function (): void {
            usleep(300_000);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^1 worker process\(es\) failed \(pid \d+\)\.$/');
        try {
            $forkManager->execute();
        } finally {
            $this->assertStringContainsString('failed: exited with status 3.', $this->log());
        }
    }

    #[RequiresPhpExtension('posix')]
    public function testAWorkerKilledByASignalCountsAsAFailure(): void
    {
        $forkManager = new ForkManager($this->fileLogger());
        $forkManager->addWorker(function (): void {
            posix_kill(getmypid(), SIGKILL);
            sleep(5);
        });
        $forkManager->addWorker(fn() => $this->touch('worker-1'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^1 worker process\(es\) failed/');
        try {
            $forkManager->execute();
        } finally {
            $this->assertStringContainsString('failed: killed by signal ' . SIGKILL . '.', $this->log());
        }
    }

    public function testAllWorkersSucceedingCompletesEachOnceAndThrowsNothing(): void
    {
        $forkManager = new ForkManager($this->fileLogger());
        $forkManager->setMaxConcurrency(2);
        foreach (range(0, 4) as $i) {
            $forkManager->addWorker(fn() => $this->touch("worker-$i"));
        }
        $completed = [];
        $forkManager->setCompletionCallback(function (int $workerIndex) use (&$completed): void {
            $completed[] = $workerIndex;
        });

        $forkManager->execute();

        sort($completed);
        $this->assertSame([0, 1, 2, 3, 4], $completed, 'each worker completes exactly once');
        foreach (range(0, 4) as $i) {
            $this->assertFileExists($this->dir . "/worker-$i");
        }
        $this->assertStringNotContainsString('failed', $this->log());
    }

    public function testASecondRunInTheSameProcessStillSeesItsOwnFailures(): void
    {
        $first = new ForkManager($this->fileLogger());
        $first->addWorker(fn() => null);
        $first->addWorker(fn() => null);
        $first->execute();

        $second = new ForkManager($this->fileLogger());
        $second->addWorker(function (): void {
            exit(2);
        });
        $second->addWorker(fn() => null);

        $this->expectException(RuntimeException::class);
        $second->execute();
    }

    private function touch(string $name): void
    {
        touch($this->dir . '/' . $name);
    }

    /** @return string[] */
    private function lines(string $name): array
    {
        return array_values(array_filter(explode("\n", (string) file_get_contents($this->dir . '/' . $name))));
    }

    private function log(): string
    {
        return (string) @file_get_contents($this->dir . '/log');
    }

    /**
     * Children log into the same file as the parent, so the test can see what happened on both sides.
     */
    private function fileLogger(): LoggerInterface
    {
        $file = $this->dir . '/log';

        return new class ($file) extends AbstractLogger {
            public function __construct(private string $file)
            {
            }

            public function log($level, string|Stringable $message, array $context = []): void
            {
                file_put_contents($this->file, "$level: $message\n", FILE_APPEND | LOCK_EX);
            }
        };
    }
}
