<?php
/**
 * This class exists only to fix a bug in Symfony\Bridge\Monolog\Handler\ConsoleHandler
 *
 * @see  https://github.com/symfony/monolog-bridge/pull/2
 * @todo Remove this class once this PR is merged
 */

namespace ConductorCore;

use Monolog\LogRecord;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Output\OutputInterface;

class MonologConsoleHandler extends ConsoleHandler
{
    protected ?OutputInterface $output;

    /**
     * Constructor.
     *
     * @param OutputInterface|null $output The console output to use (the handler remains disabled when passing null
     *                                                until the output is set, e.g. by using console events)
     * @param bool $bubble Whether the messages that are handled can bubble up the stack
     * @param array $verbosityLevelMap Array that maps the OutputInterface verbosity to a minimum logging
     *                                                level (leave empty to use the default mapping)
     */
    public function __construct(?OutputInterface $output = null, bool $bubble = true, array $verbosityLevelMap = [])
    {
        parent::__construct($output, $bubble, $verbosityLevelMap);
        $this->output = $output;
    }

    protected function write(array|LogRecord $record): void
    {
        // at this point we've determined for sure that we want to output the record, so use the output's own verbosity
        $this->output->write((string)$record['formatted']);
    }
}
