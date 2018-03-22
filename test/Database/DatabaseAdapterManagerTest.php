<?php

namespace ConductorCoreTest\Database;

use ConductorCore\Database\DatabaseAdapterInterface;
use ConductorCore\Database\DatabaseAdapterManager;
use PHPUnit\Framework\TestCase;

class DatabaseAdapterManagerTest extends TestCase
{
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

    public function setUp()
    {
        $this->readDatabaseAdapter = $this->prophesize(DatabaseAdapterInterface::class);
        $this->writeDatabaseAdapter = $this->prophesize(DatabaseAdapterInterface::class);
        // Make the write adapter different from the read one
        $this->writeDatabaseAdapter->run('test', 'test');
        $this->databaseAdapterManager = new DatabaseAdapterManager(
            [
                'read'  => $this->readDatabaseAdapter->reveal(),
                'write' => $this->writeDatabaseAdapter->reveal(),
            ]
        );
    }

    public function testGetAdapterNames()
    {
        $this->assertEquals(['read', 'write'], $this->databaseAdapterManager->getAdapterNames());
    }

    public function testGetAdapter()
    {
        $this->assertEquals($this->readDatabaseAdapter->reveal(), $this->databaseAdapterManager->getAdapter('read'));
    }

}
