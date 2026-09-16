<?php

namespace ConductorCore;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Output\OutputInterface;

class DefaultLoggerFactory implements FactoryInterface
{
    /**
     * Which log level each console verbosity shows.
     *
     * Symfony's default map puts INFO at -vv and DEBUG at -vvv, which left conductor with one useful
     * flag: -v added nothing over the default, and the plan/step narrative (INFO) only appeared
     * together with nothing else at -vv. Here INFO shows at -v and DEBUG at -vv, so:
     *
     * - default: warnings and errors
     * - -v:      plus the deploy narrative — "Deploying", "Plan: …", "Step: …", "completed"
     * - -vv:     plus every shell command conductor runs and what it printed
     * - -vvv:    the same lines as -vv; the difference is that child processes now run at -vvv too
     *            (see Shell\ChildProcessVerbosity)
     */
    public const VERBOSITY_LEVEL_MAP = [
        OutputInterface::VERBOSITY_QUIET => Level::Error,
        OutputInterface::VERBOSITY_NORMAL => Level::Warning,
        OutputInterface::VERBOSITY_VERBOSE => Level::Info,
        OutputInterface::VERBOSITY_VERY_VERBOSE => Level::Debug,
        OutputInterface::VERBOSITY_DEBUG => Level::Debug,
    ];

    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Logger
    {
        return new Logger(
            'default', [
                (new ConsoleHandler(null, true, self::VERBOSITY_LEVEL_MAP))->setFormatter(new ConsoleFormatter()),
            ]
        );
    }
}
