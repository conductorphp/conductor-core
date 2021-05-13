<?php

namespace ConductorCore;

use Psr\Log\{LoggerInterface, NullLogger, LogLevel};

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
    protected $isParent = 1;

    /**
     * @var null | int
     */
    protected $limit;

    /**
     * @var array
     */
    protected $pids = [];

    /**
     * @var array
     */
    protected $workers = [];

    /**
     * @var int
     */
    public static $dispatchInterval = 0;

    /**
     * @var null | LoggerInterface
     */
    protected $logger;

    /**
     * ForkManager constructor.
     *
     * @param LoggerInterface|null $logger
     * @param null $limit
     */
    public function __construct(
        LoggerInterface $logger = null,
        $limit = null
    ) {
        $this->limit = $limit;

        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function execute() : void
    {
        foreach ($this->workers as $worker) {

            // Limit the number of child processes
            if ($this->limit && (count($this->pids) >= $this->limit)) {
                $pid = pcntl_waitpid(-1, $status);
                unset($this->pids[$pid]);
            }

            $pid = pcntl_fork();

            if ($pid == -1) {
                $this->logger->log(LogLevel::ERROR, "Can't fork process. Check PCNTL extension.");
                exit(0);
            } else if ($pid) {
                $this->pids[$pid] = $pid;
            } else {
                // child
                $this->isParent = 0;
                call_user_func_array($worker, []);
                exit(0);
            }
        }

        //wait until all child processes will be finished
        while(true) {
            if (count($this->pids) > 0) {
                foreach($this->pids as $key => $pid) {
                    $res = pcntl_waitpid($pid, $status, WNOHANG);

                    // child process is finished.
                    if($res == -1 || $res > 0) {
                        $this->logger->log(LogLevel::DEBUG, "Processes with pid - $pid - finished.");
                        unset($this->pids[$key]);
                    }
                }
            } else {
                break;
            }
        }

    }

    protected function pause() : void
    {
        if (static::$dispatchInterval ) {
            sleep(static::$dispatchInterval);
        }
    }

    /**
     * @param callable $worker
     * @return void
     */
    public function addWorker(callable $worker) : void
    {
        $this->workers[] = $worker;
    }

    public function __destruct()
    {
        if(!$this->isParent) {
            return;
        }

        $this->logger->debug("All child processes finished.");
    }

    /**
     * Check  if pcntl module enabled.
     *
     * @return bool
     */
    public static function isPcntlEnabled() : bool
    {
        return (bool) extension_loaded('pcntl');
    }

}
