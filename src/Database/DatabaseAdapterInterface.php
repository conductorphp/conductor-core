<?php

namespace DevopsToolCore\Database;

use Exception;
use PDO;
use DevopsToolCore\ShellCommandHelper;

interface DatabaseAdapterInterface
{
    /**
     * @return array Database names as the keys and metadata as key/value pairs
     */
    public function getDatabaseMetadata(): array;

    /**
     * @param string $database Database name
     *
     * @return array Table names as the keys and metadata as key/value pairs
     */
    public function getTableMetadata(string $database): array;

    /**
     * @param string $database
     *
     * @return bool
     */
//    public function databaseExists($database);

    /**
     * @param string $database
     *
     * @return bool
     */
//    public function databaseIsEmpty($database);

    /**
     * @param string $database
     * @param string $filename
     * @param array|null $stringReplacements
     */
//    public function runSqlFile($database, $filename, array $stringReplacements = null);

    /**
     * @param string $name
     *
     * @return void
     */
    public function dropDatabaseIfExists(string $name): void;

//    public function createDatabase($database);

    /**
     *
     * @return string[]
     */
    public function getDatabases(): array;
}
