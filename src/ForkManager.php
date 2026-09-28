<?php

namespace ConductorCore;

use Psr\Log\{LoggerInterface, NullLogger};
use Throwable;

declare(ticks=1);

class ForkManager
{
    public static int $dispatchInterval = 0;
    private int $maxConcurrency = 10;
    private array $pids = [];
    private array $workers = [];
    private LoggerInterface $logger;
    /** @var array<int, int> pid => raw wait status of a child reaped before launchWorker() recorded it */
    private array $signalQueue = [];
    private $completionCallback = null;
    private array $workerIndexByPid = [];
    /** @var array<int, string> pid => why it failed, for every child that did not exit 0 */
    private array $failures = [];

    public function __construct(
        ?LoggerInterface $logger = null
    ) {
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    /**
     * @throws Exception\RuntimeException if any worker failed. When the workers ran in forked children this is
     *         thrown once, in the parent, after every child has finished.
     */
    public function execute(): void
    {
        if (count($this->workers) === 0) {
            $this->logger->warning("No workers configured.");
            return;
        }

        if (self::isPcntlEnabled() && count($this->workers) > 1) {
            $this->processConcurrently();
        } else {
            $this->processSequentially();
        }
    }

    public static function isPcntlEnabled(): bool
    {
        return extension_loaded('pcntl');
    }

    /**
     * @throws Exception\RuntimeException if any child process exits with a non-zero status or is killed by a signal.
     */
    private function processConcurrently(): void
    {
        if (!extension_loaded('pcntl')) {
            throw new Exception\RuntimeException('PHP extension pcntl not enabled.');
        }

        $this->failures = [];
        pcntl_signal(SIGCHLD, [$this, "childSignalHandler"]);

        try {
            foreach ($this->workers as $workerIndex => $worker) {
                while (count($this->pids) >= $this->maxConcurrency) {
                    sleep(1);
                }
                $this->launchWorker($worker, $workerIndex);
            }

            // Wait for child processes to finish before exiting here
            while (count($this->pids)) {
                foreach ($this->pids as $pid) {
                    $status = 0;
                    $res = pcntl_waitpid($pid, $status, WNOHANG);
                    if ($res > 0) {
                        $this->finishChild($pid, $status);
                    } elseif ($res === -1) {
                        // Already reaped by the signal handler, which recorded its status.
                        $this->forgetChild($pid);
                    }
                }
                if (count($this->pids)) {
                    sleep(1);
                }
            }
        } finally {
            // Leave no handler behind: a later ForkManager (or proc_open) in this process must reap its own children.
            pcntl_signal(SIGCHLD, SIG_DFL);
        }

        if ($this->failures) {
            throw new Exception\RuntimeException(sprintf(
                '%d worker process(es) failed (pid %s).',
                count($this->failures),
                implode(', ', array_keys($this->failures))
            ));
        }
    }

    /**
     * Launch a worker from the worker queue
     */
    private function launchWorker(callable $worker, int $workerIndex): void
    {
        if (!extension_loaded('pcntl')) {
            throw new Exception\RuntimeException('PHP extension pcntl not enabled.');
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            //Problem launching the worker
            throw new Exception\RuntimeException("Can't fork process. Check PCNTL extension.");
        }

        if ($pid) {
            // Parent process
            // Sometimes you can receive a signal to the childSignalHandler function before this code executes if
            // the child script executes quickly enough!

            $this->pids[$pid] = $pid;
            $this->workerIndexByPid[$pid] = $workerIndex;

            // In the event that a signal for this pid was caught before we get here, its wait status will be in our
            // signalQueue array. So let's go ahead and process it now as if we'd just reaped it.
            if (isset($this->signalQueue[$pid])) {
                $status = $this->signalQueue[$pid];
                unset($this->signalQueue[$pid]);
                $this->finishChild($pid, $status);
            }
        } else {
            $this->runWorkerInChild($worker);
        }
    }

    /**
     * The forked child. It must never return: whatever the worker does, the only way out of this method is exit(),
     * or the child would unwind into the parent's code (the rest of the deploy plan) and fork workers of its own.
     */
    private function runWorkerInChild(callable $worker): never
    {
        $exitCode = 1;
        try {
            $worker();
            $exitCode = 0;
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Worker %d failed: %s (%s)', getmypid(), $e->getMessage(), get_class($e)));
            $this->logger->debug($e->getTraceAsString());
        } finally {
            exit($exitCode);
        }
    }

    /**
     * SIGCHLD handler. Reaps every finished child and records its raw wait status; a child that finished before
     * launchWorker() recorded its pid is queued for launchWorker() to pick up.
     *
     * @param array|null $siginfo PHP's siginfo array (its 'status' is the decoded exit code / signal number, not a
     *                            wait status), so it is only used to pick which pid to reap first.
     */
    public function childSignalHandler(int $signo, ?array $siginfo = null): void
    {
        if (!extension_loaded('pcntl')) {
            throw new Exception\RuntimeException('PHP extension pcntl not enabled.');
        }

        $pid = $siginfo['pid'] ?? null;
        if ($pid) {
            $status = 0;
            if (pcntl_waitpid($pid, $status, WNOHANG) > 0) {
                $this->reaped($pid, $status);
            }
        }

        // Make sure we get all the exited children, including ones whose signal coalesced with this one
        $status = 0;
        $pid = pcntl_waitpid(-1, $status, WNOHANG);
        while ($pid > 0) {
            $this->reaped($pid, $status);
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
    }

    private function reaped(int $pid, int $status): void
    {
        if (isset($this->pids[$pid])) {
            $this->finishChild($pid, $status);
        } else {
            //Oh no, our worker has finished before this parent process could even note that it had been launched!
            //Let's make note of it and handle it when the parent process is ready for it
            $this->signalQueue[$pid] = $status;
        }
    }

    /**
     * A child of ours has been reaped: record how it ended and let go of it.
     */
    private function finishChild(int $pid, int $status): void
    {
        $this->logger->debug("Process with pid - $pid - finished.");

        if (pcntl_wifsignaled($status)) {
            $this->recordFailure($pid, 'killed by signal ' . pcntl_wtermsig($status));
        } elseif (pcntl_wifexited($status) && pcntl_wexitstatus($status) !== 0) {
            $this->recordFailure($pid, 'exited with status ' . pcntl_wexitstatus($status));
        }

        $this->forgetChild($pid);
    }

    private function forgetChild(int $pid): void
    {
        if ($this->completionCallback && isset($this->workerIndexByPid[$pid])) {
            call_user_func($this->completionCallback, $this->workerIndexByPid[$pid]);
        }
        unset($this->pids[$pid], $this->workerIndexByPid[$pid]);
    }

    private function recordFailure(int $pid, string $reason): void
    {
        if (isset($this->failures[$pid])) {
            return;
        }
        $this->failures[$pid] = $reason;
        $this->logger->error("Worker $pid failed: $reason.");
    }

    private function processSequentially(): void
    {
        $this->logger->info(sprintf("Running %s workers sequentially...", count($this->workers)));
        foreach ($this->workers as $worker) {
            $worker();
        }
    }

    public function setMaxConcurrency(int $maxConcurrency): void
    {
        $this->maxConcurrency = $maxConcurrency;
    }

    public function addWorker(callable $worker): void
    {
        $this->workers[] = $worker;
    }

    public function setCompletionCallback(callable $callback): void
    {
        $this->completionCallback = $callback;
    }
}
