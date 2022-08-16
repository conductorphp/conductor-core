<?php

namespace ConductorCore\Database;

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

    public function databaseExists(string $database): bool;

    public function databaseIsEmpty(string $database): bool;

    public function dropDatabase(string $database): void;

    public function createDatabase(string $database): void;

    public function dropDatabaseIfExists(string $database): void;

    public function run(string $query, string $database): void;

    /**
     * @return string[]
     */
    public function getDatabases(): array;
}
