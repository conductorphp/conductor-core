<?php

namespace ConductorCore\Database;

use Exception;
use PDO;
use ConductorCore\ShellCommandHelper;

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
    public function databaseExists(string $database): bool;

    /**
     * @param string $database
     *
     * @return bool
     */
    public function databaseIsEmpty(string $database): bool;

    /**
     * @param string $database
     *
     * @return void
     */
    public function dropDatabase(string $database): void;

    /**
     * @param string $database
     *
     * @return void
     */
    public function createDatabase(string $database): void;

    /**
     * @param string $name
     *
     * @return void
     */
    public function dropDatabaseIfExists(string $name): void;

    /**
     * @param string $query
     * @param string $database
     */
    public function run(string $query, string $database): void;

    /**
     *
     * @return string[]
     */
    public function getDatabases(): array;
}
