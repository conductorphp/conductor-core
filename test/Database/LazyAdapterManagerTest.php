<?php

declare(strict_types=1);

namespace ConductorCoreTest\Database;

use ConductorCore\Database\DatabaseAdapterInterface;
use ConductorCore\Database\DatabaseAdapterManager;
use ConductorCore\Database\DatabaseImportExportAdapterInterface;
use ConductorCore\Database\DatabaseImportExportAdapterManager;
use ConductorCore\Exception\RuntimeRequirementException;
use ConductorCore\Shell\Adapter\ShellAdapterInterface;
use ConductorCore\Shell\ShellAdapterManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The three managers accept closures beside built adapters and only invoke them on getAdapter(), so
 * a configured adapter whose runtime is missing fails when selected, never at boot (CTAP-1759).
 */
final class LazyAdapterManagerTest extends TestCase
{
    public function testDatabaseAdapterIsBuiltOnFirstRequestOnlyAndGetsTheLogger(): void
    {
        $built   = 0;
        $logger  = new NullLogger();
        $adapter = new class implements DatabaseAdapterInterface, LoggerAwareInterface {
            public ?LoggerInterface $logger = null;
            public function setLogger(LoggerInterface $logger): void { $this->logger = $logger; }
            public function getDatabaseMetadata(): array { return []; }
            public function getTableMetadata(string $database): array { return []; }
            public function databaseExists(string $database): bool { return false; }
            public function databaseIsEmpty(string $database): bool { return true; }
            public function dropDatabase(string $database): void { }
            public function createDatabase(string $database): void { }
            public function dropDatabaseIfExists(string $database): void { }
            public function run(string $sql, string $database): void { }
            public function fetchAll(string $sql, string $database, ?array $parameters = null): array { return []; }
            public function quote(string $value): string { return $value; }
            public function getDatabases(): array { return ['lazy']; }
        };

        $manager = new DatabaseAdapterManager([
            'lazy'  => static function () use (&$built, $adapter) { $built++; return $adapter; },
            'built' => $this->createStub(DatabaseAdapterInterface::class),
        ]);
        $manager->setLogger($logger);

        $this->assertSame(['lazy', 'built'], $manager->getAdapterNames());
        $this->assertSame(0, $built, 'listing names builds nothing');

        $this->assertSame(['lazy'], $manager->getAdapter('lazy')->getDatabases());
        $manager->getAdapter('lazy');
        $this->assertSame(1, $built, 'built once, then cloned');
        $this->assertSame($logger, $adapter->logger, 'a logger set before the build reaches the adapter');
    }

    public function testAFailingRequirementSurfacesFromGetAdapterNotConstruction(): void
    {
        $manager = new DatabaseImportExportAdapterManager([
            'mydumper' => static function (): DatabaseImportExportAdapterInterface {
                throw new RuntimeRequirementException('database import/export adapter "mydumper" needs command "mydumper"');
            },
        ]);

        $this->assertSame(['mydumper'], $manager->getAdapterNames());

        $this->expectException(RuntimeRequirementException::class);
        $manager->getAdapter('mydumper');
    }

    public function testShellAdapterIsBuiltOnFirstRequestAndCloned(): void
    {
        $built = 0;
        $manager = new ShellAdapterManager([
            'remote' => function () use (&$built) { $built++; return $this->createStub(ShellAdapterInterface::class); },
        ]);

        $this->assertSame(0, $built);
        $first  = $manager->getAdapter('remote');
        $second = $manager->getAdapter('remote');
        $this->assertSame(1, $built);
        $this->assertNotSame($first, $second);
    }
}
