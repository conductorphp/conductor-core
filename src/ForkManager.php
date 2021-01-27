<?php

namespace ConductorCore;

use Psr\Log\{LoggerInterface, NullLogger, LogLevel};
declare(ticks = 1);
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
     * @var int
     */
    protected $parentPID;

    /**
     * @var null | int
     */
    protected $limit = 10;

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
     * @var array
     */
    protected $signalQueue=[];

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
        $this->limit = $limit ?? $this->limit;
        $this->parentPID = getmypid();
        pcntl_signal(SIGCHLD, [$this, "childSignalHandler"]);

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
        echo "Running \n";

        foreach ($this->workers as $workerId => $worker) {
            while(count($this->pids) >= $this->limit){
                echo "Maximum children allowed, waiting...\n";
                //echo ("Pids: ".implode(';',$this->pids)."\n");
                sleep(1);
            }
            $launched = $this->launchJob($worker);
        }

        //Wait for child processes to finish before exiting here
        while(count($this->pids)){
            //echo "Waiting for current jobs to finish... \n";
            //echo ("Pids: ".implode(';',$this->pids)."\n");
            foreach($this->pids as $key => $pid) {
                $res = pcntl_waitpid($pid, $status, WNOHANG);
                // child process is finished.
                if($res == -1 || $res > 0) {
                    $this->logger->log(LogLevel::DEBUG, "Processes with pid - $pid - finished.");
                    unset($this->pids[$key]);
                }
            }
            sleep(1);
        }
    }

    /**
     * Launch a job from the job queue
     */
    protected function launchJob($worker){
        $pid = pcntl_fork();
        if($pid == -1){
            //Problem launching the job
            $this->logger->log(LogLevel::ERROR, "Can't fork process. Check PCNTL extension.");
            exit(0);
        }
        else if ($pid){
            // Parent process
            // Sometimes you can receive a signal to the childSignalHandler function before this code executes if
            // the child script executes quickly enough!

            $this->pids[$pid] = $pid;

            // In the event that a signal for this pid was caught before we get here, it will be in our signalQueue array
            // So let's go ahead and process it now as if we'd just received the signal
            if(isset($this->signalQueue[$pid])){
                //    echo "found $pid in the signal queue, processing it now \n";
                $this->childSignalHandler(SIGCHLD, $pid, $this->signalQueue[$pid]);
                unset($this->signalQueue[$pid]);
            }
        }
        else{
            //Forked child, do your deeds....
            $exitStatus = 0; //Error code if you need to or whatever
            // echo "Doing something fun in pid ".getmypid()."\n";
            call_user_func_array($worker, []);
            exit($exitStatus);
        }
        return true;
    }

    public function childSignalHandler($signo, $pidArray=null, $status=null){
        $pid = $pidArray['pid']??null;
        $status = $pidArray['status']??null;

        //If no pid is provided, that means we're getting the signal from the system.  Let's figure out
        //which child process ended
        if(!$pid){
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }

        //Make sure we get all of the exited children
        while($pid > 0){

            if($pid && isset($this->pids[$pid])){
                $exitCode = pcntl_wexitstatus($status);
                if($exitCode != 0){
                    echo "$pid exited with status ".$exitCode."\n";
                }
                unset($this->pids[$pid]);
            }
            else if($pid){
                //Oh no, our job has finished before this parent process could even note that it had been launched!
                //Let's make note of it and handle it when the parent process is ready for it
                //echo "..... Adding $pid to the signal queue ..... \n";
                $this->signalQueue[$pid] = $status;
            }
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
        return true;
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
