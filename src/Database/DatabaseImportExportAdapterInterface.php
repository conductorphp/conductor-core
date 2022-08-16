<?php

namespace ConductorCore\Database;

use DomainException;
use Psr\Log\LoggerInterface;
use RuntimeException;

interface DatabaseImportExportAdapterInterface
{
    public const DEFAULT_WORKING_DIR = '.conductor-database-export';

    /**
     * @param string $database Database to export.
     * @param string $path Path to write export to.
     * @param array $options Export options. These may be specific to the adapter being used.
     *
     * @return string Filename export was written to
     * @throws RuntimeException    If an error occurs on export.
     * @throws DomainException     If invalid options are given for the adapter being used.
     *
     * @throws RuntimeException    If given path is not a writable, empty directory and cannot be created.
     */
    public function exportToFile(
        string $database,
        string $path,
        array  $options = []
    ): string;

    /**
     * @param string $filename Filename to import from.
     * @param string $database Database to import into.
     * @param array $options Import options. These may be specific to the adapter being used.
     *
     * @return void
     * @throws RuntimeException    If an error occurs on import.
     * @throws DomainException     If invalid options are given for the adapter being used.
     *
     * @throws RuntimeException    If given file is not readable or not in the correct format.
     */
    public function importFromFile(string $filename, string $database, array $options = []): void;

    public static function getFileExtension(): string;

    public function setLogger(LoggerInterface $logger): void;

    /**
     * Asserts that the adapter is usable in the current environment.
     *
     * @throws RuntimeException If not usable. Message should include reason adapter is not usable.
     */
    public function assertIsUsable(): void;
}

