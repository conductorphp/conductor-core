<?php

namespace DevopsToolCore\Database\ImportExportAdapter;

interface DatabaseImportExportAdapterInterface
{
    const FORMAT_MYDUMPER = 'mydumper';
    const FORMAT_SQL = 'sql';
    const FORMAT_TAB_DELIMITED = 'tab';

    /**
     * Returns whether or not this adapter is usable in the current environment.
     *
     * @return boolean
     */
    public static function isUsable();

    /**
     * Returns file extention used for exports
     *
     * @return string
     */
    public static function getFileExtension();

    /**
     * @param string $database
     * @param string $filename
     * @param array  $ignoreTables
     * @param bool   $removeDefiners
     *
     * @return string Filename
     */
    public function exportToFile(
        $database,
        $filename,
        array $ignoreTables = [],
        $removeDefiners = true
    );

    /**
     * @param string $database
     * @param string $filename
     *
     * @return void
     */
    public function importFromFile($database, $filename);
}

