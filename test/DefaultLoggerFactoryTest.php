<?php

namespace ConductorCoreTest;

use ConductorCore\DefaultLoggerFactory;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The narrative a deployer reads ("Plan: …", "Step: …") is logged at INFO; what conductor ran and
 * what it printed is logged at DEBUG. These pin which flag shows which, because that mapping is the
 * whole point of -v versus -vv.
 */
class DefaultLoggerFactoryTest extends TestCase
{
    /** @return iterable<string, array{0: int, 1: bool, 2: bool}> */
    public static function verbosities(): iterable
    {
        yield 'default shows neither' => [OutputInterface::VERBOSITY_NORMAL, false, false];
        yield '-v shows the narrative' => [OutputInterface::VERBOSITY_VERBOSE, true, false];
        yield '-vv shows the shell commands too' => [OutputInterface::VERBOSITY_VERY_VERBOSE, true, true];
        yield '-vvv shows everything' => [OutputInterface::VERBOSITY_DEBUG, true, true];
    }

    #[DataProvider('verbosities')]
    public function testInfoShowsAtVerboseAndDebugAtVeryVerbose(int $verbosity, bool $info, bool $debug): void
    {
        $output = new BufferedOutput($verbosity);
        $logger = $this->logger($output);

        $logger->info('Step: composer-install');
        $logger->debug('Running shell command: composer install');

        $written = $output->fetch();
        $this->assertSame($info, str_contains($written, 'Step: composer-install'), 'INFO line');
        $this->assertSame($debug, str_contains($written, 'Running shell command'), 'DEBUG line');
    }

    public function testWarningsShowAtTheDefaultVerbosity(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL);
        $this->logger($output)->warning('disk is nearly full');

        $this->assertStringContainsString('disk is nearly full', $output->fetch());
    }

    private function logger(OutputInterface $output): Logger
    {
        $logger = (new DefaultLoggerFactory())($this->createStub(ContainerInterface::class), Logger::class);
        foreach ($logger->getHandlers() as $handler) {
            $this->assertInstanceOf(ConsoleHandler::class, $handler);
            $handler->setOutput($output);
        }

        return $logger;
    }
}
