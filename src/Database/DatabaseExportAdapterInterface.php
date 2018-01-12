<?php

namespace DevopsToolCore\Database;

use Psr\Log\LoggerInterface;

interface DatabaseExportAdapterInterface
{
    const DEFAULT_WORKING_DIR = '.devops-database-export';

    /**
     * Asserts that the adapter is usable in the current environment.
     *
     * @throws \RuntimeException If not usable. Message should include reason adapter is not usable.
     */
    public function assertIsUsable(): void;

    /**
     * @return array An array with all allowed options as keys and a description of them as values.
     */
    public function getOptionsHelp(): array;

    /**
     * @param string $database      Database to export.
     * @param string $path          Path to write export to.
     * @param array  $options       Export options. These may be specific to the adapter being used.
     *
     * @throws \RuntimeException    If given path is not a writable, empty directory and cannot be created.
     * @throws \RuntimeException    If an error occurs on export.
     * @throws \DomainException     If invalid options are given for the adapter being used.
     *
     * @return string Filename export was written to
     */
    public function exportToFile(
        string $database,
        string $path,
        array $options = []
    ): string;

    /**
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void;

    /**
     * @param string $name
     *
     * @return void
     */
    public function selectConnection(string $name): void;
}

