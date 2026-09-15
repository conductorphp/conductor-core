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

    public function run(string $sql, string $database): void;

    /**
     * Run a read query and return its rows.
     *
     * The counterpart to {@see run()}, which returns void. Without this, anything that needs to
     * LOOK at a database — check whether a column exists before generating SQL against it, read a
     * snapshot's schema to pick a predicate — has to open its own connection alongside the
     * adapter's, duplicating the credential wiring the adapter already owns.
     *
     * Bind values through $parameters rather than interpolating them; use {@see quote()} only for
     * a value that has to become part of SQL TEXT (a literal inside a statement handed to
     * {@see run()}), where there is no placeholder to bind to.
     *
     * @param string                    $database   Database to run against, as for {@see run()}.
     * @param array<string, mixed>|null $parameters Values for the driver's own placeholders.
     * @return list<array<string, mixed>> Rows as column => value; an empty list when none match.
     */
    public function fetchAll(string $sql, string $database, ?array $parameters = null): array;

    /**
     * Quote a value as a SQL literal, including its surrounding delimiters.
     *
     * For building SQL TEXT that {@see run()} will execute — prefer binding via
     * {@see fetchAll()}'s $parameters wherever a placeholder is possible. Callers previously had to
     * hand-roll this (`"'" . addslashes($value) . "'"`), which is not the driver's escaping and
     * does not honor the connection's charset.
     */
    public function quote(string $value): string;

    /**
     * @return string[]
     */
    public function getDatabases(): array;
}
