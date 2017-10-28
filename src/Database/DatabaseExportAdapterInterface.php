<?php

namespace DevopsToolCore\Database;

interface DatabaseExportAdapterInterface
{
    /**
     * Returns whether or not this adapter is usable in the current environment.
     *
     * @return boolean
     */
    public function isUsable();

    /**
     * Returns file extention used for exports
     *
     * @return string
     */
    public function getFileExtension();

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
        // @todo Can $removeDefiners be generalized? This seems MySQL specific
        $removeDefiners = true
    );
}

