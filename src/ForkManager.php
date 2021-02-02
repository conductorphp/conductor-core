<?php

namespace ConductorCore;

use Psr\Log\{LoggerInterface, NullLogger, LogLevel};

declare(ticks=1);

/**
 * Class ForkManager.
 * Forking and Managing child processes.
 *
 * @package ConductorCore
 */
class ForkManager
{
    /**
     * @var int
     */
    private $isParent = 1;

    /**
     * @var int
     */
    private $parentPid;

    /**
     * @var null | int
     */
    private $maxConcurrency = 10;

    /**
     * @var array
     */
    private $pids = [];

    /**
     * @var array
     */
    private $workers = [];

    /**
     * @var int
     */
    public static $dispatchInterval = 0;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $signalQueue = [];

    /**
     * ForkManager constructor.
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        LoggerInterface $logger = null
    )
    {
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    /**
     * @return void
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

    /**
     * @throws Exception\RuntimeException if any child process exits with a non-zero status.
     */
    private function processConcurrently(): void
    {
        $status = 0;
        $this->logger->info(sprintf("Running %s workers with concurrency %s...", count($this->workers), $this->maxConcurrency));
        $this->parentPid = getmypid();
        pcntl_signal(SIGCHLD, [$this, "childSignalHandler"]);

        // @todo Handle gathering exit status of child processes better. If one child fails, we want to be able to
        //       throw an exception at the end of this method. It is not handled in launchWorker as far as I can tell.

        foreach ($this->workers as $workerId => $worker) {
            while (count($this->pids) >= $this->maxConcurrency) {
                // $this->logger->debug("Maximum children allowed, waiting...");
                // $this->logger->debug(sprintf( "Pids: %s, implode(';', $this->pids)));
                sleep(1);
            }
            $this->launchWorker($worker);
        }

        // Wait for child processes to finish before exiting here
        while (count($this->pids)) {
            // $this->logger->debug("Waiting for final workers to finish...");
            // $this->logger->debug(sprintf( "Pids: %s, implode(';', $this->pids)));
            foreach ($this->pids as $key => $pid) {
                $res = pcntl_waitpid($pid, $status, WNOHANG);
                // child process is finished.
                if ($res == -1 || $res > 0) {
                    $this->logger->debug("Process with pid - $pid - finished.");
                    unset($this->pids[$key]);
                }
            }
            sleep(1);
        }

        if ($status > 0 && $status <= 255) {
            throw new Exception\RuntimeException('A child process exited with a non-zero status.');
        }
    }

    private function processSequentially(): void
    {
        $this->logger->info(sprintf("Running %s workers sequentially...", count($this->workers)));
        foreach ($this->workers as $worker) {
            $worker();
        }
    }

    /**
     * @param int $maxConcurrency
     */
    public function setMaxConcurrency(int $maxConcurrency): void
    {
        $this->maxConcurrency = $maxConcurrency;
    }

    /**
     * Launch a worker from the worker queue
     * @param callable $worker
     */
    private function launchWorker(callable $worker): void
    {
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

            // In the event that a signal for this pid was caught before we get here, it will be in our signalQueue array
            // So let's go ahead and process it now as if we'd just received the signal
            if (isset($this->signalQueue[$pid])) {
                //    $this->logger->debug("found $pid in the signal queue, processing it now");
                $this->childSignalHandler(SIGCHLD, $pid, $this->signalQueue[$pid]);
                unset($this->signalQueue[$pid]);
            }
        } else {
            //Forked child, do your deeds....
            // $this->logger->debug("Doing something fun in pid ".getmypid().");
            $worker();
        }
    }

    public function childSignalHandler(int $signo, array $pidArray = null, int $status = null): void
    {
        $pid = $pidArray['pid'] ?? null;
        $status = $pidArray['status'] ?? null;

        //If no pid is provided, that means we're getting the signal from the system.  Let's figure out
        //which child process ended
        if (!$pid) {
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }

        //Make sure we get all of the exited children
        while ($pid > 0) {

            if ($pid && isset($this->pids[$pid])) {
                $exitCode = pcntl_wexitstatus($status);
                if ($exitCode !== 0) {
                    $this->logger->error("$pid exited with status " . $exitCode);
                }
                unset($this->pids[$pid]);
            } else if ($pid) {
                //Oh no, our worker has finished before this parent process could even note that it had been launched!
                //Let's make note of it and handle it when the parent process is ready for it
                // $this->logger->debug("..... Adding $pid to the signal queue .....");
                $this->signalQueue[$pid] = $status;
            }
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
    }

    private function pause(): void
    {
        if (static::$dispatchInterval) {
            sleep(static::$dispatchInterval);
        }
    }

    /**
     * @param callable $worker
     * @return void
     */
    public function addWorker(callable $worker): void
    {
        $this->workers[] = $worker;
    }

    /**
     * Check if pcntl module enabled.
     *
     * @return bool
     */
    public static function isPcntlEnabled(): bool
    {
        return (bool)extension_loaded('pcntl');
    }

}
