<?php

namespace ConductorCoreTest\Database;

use Prophecy\PhpUnit\ProphecyTrait;
use ConductorCore\Database\DatabaseAdapterManager;
use ConductorCore\Database\DatabaseImportExportAdapterInterface;
use ConductorCore\Database\DatabaseImportExportAdapterManager;
use PHPUnit\Framework\TestCase;

class DatabaseImportExportAdapterManagerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var DatabaseImportExportAdapterInterface
     */
    private $mysqldumpImportExportDatabaseAdapter;
    /**
     * @var DatabaseImportExportAdapterInterface
     */
    private $mydumperImportExportDatabaseAdapter;

    /**
     * @var DatabaseAdapterManager
     */
    private $databaseImportExportAdapterManager;

    public function setUp(): void
    {
        $this->mysqldumpImportExportDatabaseAdapter = $this->prophesize(DatabaseImportExportAdapterInterface::class);
        $this->mydumperImportExportDatabaseAdapter = $this->prophesize(DatabaseImportExportAdapterInterface::class);
        // Make the mydumper adapter different from the mysqldump one
        $this->mydumperImportExportDatabaseAdapter->exportToFile('test', 'test');
        $this->databaseImportExportAdapterManager = new DatabaseImportExportAdapterManager(
            [
                'mysqldump' => $this->mysqldumpImportExportDatabaseAdapter->reveal(),
                'mydumper' => $this->mydumperImportExportDatabaseAdapter->reveal(),
            ]
        );
    }

    public function testGetAdapterNames()
    {
        $this->assertEquals(['mysqldump', 'mydumper'], $this->databaseImportExportAdapterManager->getAdapterNames());
    }

    public function testGetAdapter()
    {
        $this->assertEquals(
            $this->mysqldumpImportExportDatabaseAdapter->reveal(),
            $this->databaseImportExportAdapterManager->getAdapter('mysqldump')
        );
    }

}
