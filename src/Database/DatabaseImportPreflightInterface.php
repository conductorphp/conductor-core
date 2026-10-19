<?php

namespace ConductorCore\Database;

use RuntimeException;

/**
 * An import/export adapter that can tell, without touching the target, whether importFromFile() would
 * accept a file.
 *
 * A snapshot restore drops the target database before it imports into it, so an import that refuses
 * the file afterwards leaves the database empty. A restore asks an adapter that implements this
 * before it drops anything (CTAP-2732).
 */
interface DatabaseImportPreflightInterface
{
    /**
     * Must leave both the file and the target database as they were.
     *
     * @param string $filename File that is about to be imported.
     * @param string $database Database it is about to be imported into. It may not exist yet.
     *
     * @throws RuntimeException If the import would be refused. The message says why.
     */
    public function assertCanImportFile(string $filename, string $database): void;
}
