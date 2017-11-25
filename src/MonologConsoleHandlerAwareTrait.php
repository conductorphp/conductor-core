<?php

namespace DevopsToolCore;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Output\OutputInterface;

trait MonologConsoleHandlerAwareTrait
{
    /**
     * @param OutputInterface $output
     */
    private function injectOutputIntoLogger(OutputInterface $output, LoggerInterface $logger)
    {
        if ($logger instanceof Logger) {
            foreach ($logger->getHandlers() as $handler) {
                if ($handler instanceof ConsoleHandler) {
                    $handler->setOutput($output);
                }
            }
        }
    }
}