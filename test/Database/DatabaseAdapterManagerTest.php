<?php

namespace ConductorCoreTest\Database;

use Prophecy\PhpUnit\ProphecyTrait;
use ConductorCore\Database\DatabaseAdapterInterface;
use ConductorCore\Database\DatabaseAdapterManager;
use ConductorCore\Exception;
use PHPUnit\Framework\TestCase;

class DatabaseAdapterManagerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var DatabaseAdapterInterface
     */
    private $readDatabaseAdapter;
    /**
     * @var DatabaseAdapterInterface
     */
    private $writeDatabaseAdapter;

    /**
     * @var DatabaseAdapterManager
     */
    private $databaseAdapterManager;

    public function setUp(): void
    {
        $this->readDatabaseAdapter = $this->prophesize(DatabaseAdapterInterface::class);
        $this->writeDatabaseAdapter = $this->prophesize(DatabaseAdapterInterface::class);
        // Give each double distinguishable behavior so the assertions can tell which
        // adapter came back. getAdapter() returns a clone, so identity cannot.
        $this->readDatabaseAdapter->getDatabases()->willReturn(['read_db']);
        $this->writeDatabaseAdapter->getDatabases()->willReturn(['write_db']);
        $this->databaseAdapterManager = new DatabaseAdapterManager(
            [
                'read' => $this->readDatabaseAdapter->reveal(),
                'write' => $this->writeDatabaseAdapter->reveal(),
            ]
        );
    }

    public function testGetAdapterNames()
    {
        $this->assertEquals(['read', 'write'], $this->databaseAdapterManager->getAdapterNames());
    }

    public function testGetAdapterReturnsTheRequestedAdapter()
    {
        // Asserted by behavior rather than by comparing the objects: getAdapter()
        // returns a clone, so identity does not hold, and deep-comparing two test
        // doubles makes PHPUnit 13 warn because it cannot compare their closures.
        $this->assertSame(['read_db'], $this->databaseAdapterManager->getAdapter('read')->getDatabases());
        $this->assertSame(['write_db'], $this->databaseAdapterManager->getAdapter('write')->getDatabases());
    }

    /**
     * Callers mutate the adapter they are handed, so each call must yield an
     * isolated copy rather than the shared instance.
     */
    public function testGetAdapterReturnsACloneNotTheSharedInstance()
    {
        $adapter = $this->databaseAdapterManager->getAdapter('read');

        $this->assertNotSame($this->readDatabaseAdapter->reveal(), $adapter);
        $this->assertNotSame($adapter, $this->databaseAdapterManager->getAdapter('read'));
    }

    public function testGetAdapterThrowsOnUnknownName()
    {
        $this->expectException(Exception\DomainException::class);
        $this->databaseAdapterManager->getAdapter('nope');
    }

}
