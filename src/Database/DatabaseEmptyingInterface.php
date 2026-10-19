<?php

namespace ConductorCore\Database;

use RuntimeException;

/**
 * A database adapter that can empty a database without dropping it.
 *
 * Dropping and recreating a database needs rights over the database itself. A managed database's app
 * account (Webscale, RDS) often has rights only inside its own schema, so a snapshot restore that drops
 * the database cannot run there. A restore empties an existing database through an adapter that
 * implements this instead (CTAP-2756).
 */
interface DatabaseEmptyingInterface
{
    /**
     * Drops every object in the database (tables, views, routines and events) and leaves the database
     * itself, with its grants and defaults, in place. Works with rights inside the database alone.
     *
     * @throws RuntimeException If an object cannot be dropped. Objects dropped before it stay dropped.
     */
    public function emptyDatabase(string $database): void;
}
