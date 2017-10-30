<?php

namespace DevopsToolCore\Database;

use Psr\Log\LoggerInterface;

interface DatabaseImportAdapterInterface
{
    /**
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger);

    /**
     * Asserts that the adapter is usable in the current environment.
     *
     * @throws \RuntimeException If not usable. Message should include reason adapter is not usable.
     */
    public function assertIsUsable();

    /**
     * @return array An array with all allowed options as keys and a description of them as values.
     */
    public function getOptionsHelp();

    /**
     * @param string $filename      Filename to import from.
     * @param string $database      Database to import into.
     * @param array  $options       Import options. These may be specific to the adapter being used.
     *
     * @throws \RuntimeException    If given file is not readable or not in the correct format.
     * @throws \RuntimeException    If an error occurs on import.
     * @throws \DomainException     If invalid options are given for the adapter being used.
     *
     * @return void
     */
    public function importFromFile($filename, $database, array $options = []);
}

