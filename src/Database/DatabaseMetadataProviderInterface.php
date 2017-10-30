<?php

namespace DevopsToolCore\Database;

use Psr\Log\LoggerInterface;

interface DatabaseMetadataProviderInterface
{
    /**
     * @return array Database names as the keys and metadata as key/value pairs
     */
    public function getDatabaseMetadata();

    /**
     * @param string $database Database name
     *
     * @return array Table names as the keys and metadata as key/value pairs
     */
    public function getTableMetadata($database);
}
