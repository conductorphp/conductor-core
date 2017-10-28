<?php

namespace DevopsToolCore\Database;

use Exception;
use PDO;
use DevopsToolCore\ShellCommandHelper;

// @todo Add pdo php extension requirement to composer.json
// @todo Deprecate this class after all usages are removed
class DatabaseAdapter
{
    /**
     * @var PDO
     */
    private $pdo;
    /**
     * @var ShellCommandHelper
     */
    private $shellCommandHelper;

    public function __construct(PDO $pdo, ShellCommandHelper $shellCommandHelper = null)
    {
        $this->pdo = $pdo;
        $disabled = explode(',', ini_get('disable_functions'));
        if (in_array('exec', $disabled)) {
            throw new Exception("Php exec function must be enabled to use " . __CLASS__);
        }
        if (is_null($shellCommandHelper)) {
            $shellCommandHelper = new ShellCommandHelper();
        }
        $this->shellCommandHelper = $shellCommandHelper;
        try {
            $shellCommandHelper->runShellCommand('which mysql &>/dev/null');
        } catch (Exception $e) {
            throw new Exception("Mysql shell client must be installed to use " . __CLASS__);
        }
    }

    public function dumpToGzipFile($database, array $ignoredDatabaseTables, $filename, $stripDefiners = true)
    {
        if (!$this->databaseExists($database)) {
            throw new Exception("Database \"$database\" does not exist.");
        }
        $dumpStructureCommand = 'mysqldump ' . escapeshellarg($database)
            . ' --single-transaction --quick --lock-tables=false --skip-comments --no-data';
        $dumpDataCommand = 'mysqldump ' . escapeshellarg($database)
            . ' --single-transaction --quick --lock-tables=false --skip-comments --no-create-db --no-create-info --skip-triggers ';
        if ($ignoredDatabaseTables) {
            foreach ($ignoredDatabaseTables as $table) {
                $dumpDataCommand .= '--ignore-table=' . escapeshellarg("$database.$table") . ' ';
            }
        }
        $command = "($dumpStructureCommand && $dumpDataCommand) ";
        if ($stripDefiners) {
            $command .= '| sed "s/DEFINER=[^*]*\*/\*/g" ';
        }
        $command .= '| gzip -9 > ' . escapeshellarg($filename);
        $command = 'ionice -c3 nice -n 19 bash -c ' . escapeshellarg($command);
        $this->shellCommandHelper->runShellCommand($command);
        return $filename;
    }

    /**
     * @param string $database
     *
     * @return bool
     */
    public function databaseExists($database)
    {
        // This command returns no content if the database doesn't exist
        $command = "mysql -e 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \"$database\"'";
        $output = $this->shellCommandHelper->runShellCommand($command);
        return (bool)$output;
    }

    /**
     * @param string $database
     *
     * @return bool
     */
    public function databaseIsEmpty($database)
    {
        // This command returns no content if the database doesn't exist
        $command
            = "mysql --skip-column-names --silent -e 'SELECT COUNT(DISTINCT `table_name`) FROM `information_schema`.`columns` WHERE `table_schema` = \"$database\"'";
        $numTables = trim($this->shellCommandHelper->runShellCommand($command));
        if (!is_numeric($numTables)) {
            throw new Exception("Invalid result \"$numTables\" returned from mysql command \"$command\".");
        }
        return 0 == $numTables;
    }

    /**
     * @param string $database
     * @param string $filename
     * @param array|null $stringReplacements
     */
    public function runSqlFile($database, $filename, array $stringReplacements = null)
    {
        $command = 'cat ' . escapeshellarg($filename) . ' | ';
        if (preg_match('/\.gz$/', $filename)) {
            $command .= 'gunzip | ';
        }
        if ($stringReplacements) {
            foreach ($stringReplacements as $search => $replace) {
                $command .= 'sed ' . escapeshellarg("s|$search|$replace|g") . ' | ';
            }
        }
        $command .= 'mysql ' . escapeshellarg($database);
        $this->shellCommandHelper->runShellCommand($command);
    }

    public function dropDatabase($database)
    {
        // @todo Deal with escaping db name here. DROP DATABASE doesn't seem to like quoted values.
        //       I also tried prepared statements
        $this->pdo->query("DROP DATABASE $database");
    }

    public function createDatabase($database)
    {
        $command = 'mysqladmin create ' . escapeshellarg($database);
        $this->shellCommandHelper->runShellCommand($command);
    }

    /**
     *
     * @param string $prefix
     *
     * @return array
     */
    public function getDatabasesWithPrefix($prefix)
    {
        $databases = [];
        foreach ($this->pdo->query("SHOW DATABASES LIKE '${prefix}_%'")->fetchAll() as $database) {
            $databases[] = $database[0];
        }
        return $databases;
    }
}
