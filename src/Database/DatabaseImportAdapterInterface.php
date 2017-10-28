<?php

namespace DevopsToolCore\Database;

interface DatabaseImportAdapterInterface
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
     *
     * @return void
     */
    public function importFromFile($database, $filename);
}

