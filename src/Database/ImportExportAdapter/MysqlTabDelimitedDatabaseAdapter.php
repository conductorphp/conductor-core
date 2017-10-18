<?php

namespace DevopsToolCore\Database\ImportExportAdapter;

use App\Exception;
use DevopsToolCore\ShellCommandHelper;
use DevopsToolCore\Database\DatabaseConfig;
use Monolog\Handler\NullHandler;
use Psr\Log\LoggerInterface;

class MysqlTabDelimitedDatabaseAdapter implements DatabaseImportExportAdapterInterface
{
    /**
     * @var DatabaseConfig
     */
    private $databaseConfig;
    /**
     * @var ShellCommandHelper
     */
    private $shellCommandHelper;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var boolean
     */
    private static $isUsable;

    public function __construct(
        DatabaseConfig $databaseConfig = null,
        ShellCommandHelper $shellCommandHelper = null,
        LoggerInterface $logger = null
    ) {
        if (!static::isUsable()) {
            throw new Exception\RuntimeException(__CLASS__ . ' is not usable in this environment.');
        }

        $this->databaseConfig = $databaseConfig;
        if (is_null($shellCommandHelper)) {
            $shellCommandHelper = new ShellCommandHelper();
        }
        $this->shellCommandHelper = $shellCommandHelper;
        if (is_null($logger)) {
            $logger = new NullHandler();
        }
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public static function isUsable()
    {
        if (is_null(self::$isUsable)) {
            $usedFunctions = [
                'mysql',
                'mysqldump',
                'mysqlimport',
                'tar',
            ];
            exec('which ' . implode(' &> /dev/null && which ', $usedFunctions) . ' &> /dev/null', $output, $return);
            self::$isUsable = (0 == $return);
        }

        return self::$isUsable;
    }

    /**
     * @inheritdoc
     */
    public static function getFileExtension()
    {
        return 'tgz';
    }

    /**
     * Note: SELECT INTO OUTFILE has a few blockers that make it mostly inaccessible:
     *     * Amazon RDS doesn't support it
     *     * It requires MySQL server access
     *     * It requires access to files written by the mysql user to the directory specified in secure_file_priv
     *
     * @param            $database
     * @param array      $ignoreTables
     * @param string     $filename
     * @param bool       $removeDefiners
     *
     * @throws Exception\RuntimeException If command fails
     * @return string filename
     */
    public function exportToFile(
        $database,
        $filename,
        array $ignoreTables = [],
        $removeDefiners = true
    ) {
        $filename = $this->normalizeFilename($filename);
        $path = dirname($filename);
        if (!(is_dir($path) && is_writable($path))) {
            throw new Exception\RuntimeException(
                sprintf(
                    'Path "%s" is not a writable directory.',
                    $path
                )
            );
        }

        $fileInfo = new \SplFileInfo($filename);
        $path = $fileInfo->getPath();
        $workingDir = $path . '/' . substr($fileInfo->getBasename(), 0, -(strlen(self::getFileExtension()) + 1));
        if (!is_dir($workingDir)) {
            mkdir($workingDir);
        } elseif (count(scandir($workingDir)) > 2) {
            throw new Exception\RuntimeException(
                sprintf(
                    'Path "%s" must be empty.',
                    $workingDir
                )
            );
        }
        $command = $this->getTabDelimitedFileExportCommand(
            $database,
            $workingDir,
            $filename,
            $ignoreTables,
            $removeDefiners
        );

        try {
            $this->shellCommandHelper->runShellCommand($command, ShellCommandHelper::PRIORITY_LOW);
            $this->shellCommandHelper->runShellCommand('rm -rf ' . escapeshellarg($workingDir));

        } catch (\Exception $e) {
            throw new Exception\RuntimeException($e->getMessage());
        }
        return $filename;
    }

    /**
     * @param            $database
     * @param string     $filename
     *
     * @throws Exception\RuntimeException If command fails
     * @return string filename
     */
    public function importFromFile(
        $database,
        $filename
    ) {
        $filename = $this->normalizeFilename($filename);
        $workingDir = dirname($filename);
        if (!is_writable($workingDir)) {
            throw new Exception\RuntimeException("Directory \"$workingDir\" is not writable.");
        }
        $this->shellCommandHelper->runShellCommand(
            'cd ' . escapeshellarg($workingDir)
            . ' && tar xzf ' . escapeshellarg(basename($filename))
        );
        $extractedDir = substr($filename, 0, -(strlen(self::getFileExtension()) + 1));
        $command = $this->getTabDelimitedFileImportCommand($database, $extractedDir);

        try {
            $this->shellCommandHelper->runShellCommand($command, ShellCommandHelper::PRIORITY_LOW);
            $this->shellCommandHelper->runShellCommand('rm -rf ' . escapeshellarg($extractedDir));
        } catch (\Exception $e) {
            throw new Exception\RuntimeException($e->getMessage());
        }
        return $filename;
    }

    /**
     * @param       $database
     * @param       $workingDir
     * @param       $filename
     * @param array $ignoreTables
     * @param       $removeDefiners
     *
     * @return string
     */
    private function getTabDelimitedFileExportCommand(
        $database,
        $workingDir,
        $filename,
        array $ignoreTables,
        $removeDefiners
    ) {
        $dumpStructureCommand = $this->getDumpStructureCommand($database, $removeDefiners)
            . '> ' . escapeshellarg("$workingDir/schema.sql");

        // Get all table names
        $command = 'mysql --skip-column-names --silent -e "SHOW TABLES from \`' . $database . '\`;" '
            . $this->getCommandConnectionArguments() . ' ';
        $tables = trim($this->shellCommandHelper->runShellCommand($command));
        if ($tables) {
            $tables = array_diff(
                explode(PHP_EOL, trim($this->shellCommandHelper->runShellCommand($command))),
                $ignoreTables
            );
        }

        $this->shellCommandHelper->runShellCommand($command);
        $dumpDataCommand = '';
        if ($tables) {
            $rowsPerFile = 100000;
            foreach ($tables as $table) {
                $numRowsCommand = 'mysql ' . escapeshellarg($database)
                    . ' --skip-column-names --silent -e "SELECT COUNT(*) FROM \`' . $table . '\`" '
                    . $this->getCommandConnectionArguments() . ' ';
                $numRows = (int)$this->shellCommandHelper->runShellCommand($numRowsCommand);
                if (0 == $numRows) {
                    continue;
                }

                $getPrimaryKeyCommand = 'mysql ' . escapeshellarg($database) . ' --skip-column-names --silent -e '
                    . '"SELECT \`COLUMN_NAME\` FROM \`information_schema\`.\`COLUMNS\` '
                    . 'WHERE \`TABLE_SCHEMA\` = ' . escapeshellarg($database) . ' '
                    . 'AND \`TABLE_NAME\` = ' . escapeshellarg($table) . ' '
                    . 'AND \`COLUMN_KEY\` = \'PRI\'" '
                    . $this->getCommandConnectionArguments() . ' ';
                $primaryKeys = trim($this->shellCommandHelper->runShellCommand($getPrimaryKeyCommand));
                if ($primaryKeys) {
                    $orderBy = 'ORDER BY \`' . implode('\`,\`', explode("\n", $primaryKeys)) . '\` ';
                } else {
                    $orderBy = '';
                }

                $numFiles = ceil($numRows / $rowsPerFile);
                $fileNumber = 1;
                for ($i = 0; $i < $numRows; $i += $rowsPerFile) {
                    $dumpDataCommand .= "echo 'Exporting \"$table\" data [$fileNumber/$numFiles]...' 1>&2 && "
                        . 'mysql ' . escapeshellarg($database) . ' --skip-column-names -e "SELECT * FROM \`' . $table
                        . '\` '
                        . $orderBy
                        . 'LIMIT ' . $i . ',' . ($i + $rowsPerFile) . '" '
                        . '> ' . escapeshellarg("$workingDir/$table.$fileNumber.txt") . ' '
                        . '&& ';
                    $fileNumber++;
                }
            }
            $dumpDataCommand = substr($dumpDataCommand, 0, -4);
        }
        $tarCommand = 'cd ' . escapeshellarg(dirname($workingDir)) . ' && '
            . 'tar czf ' . escapeshellarg(basename($filename)) . ' ' . escapeshellarg(basename($workingDir));

        $command = $dumpStructureCommand;
        if ($dumpDataCommand) {
            $command .= " && $dumpDataCommand";
        }
        $command .= "&& $tarCommand";
        return $command;
    }

    private function getTabDelimitedFileImportCommand($database, $extractedDir)
    {
        $importSchemaCommand = 'mysql ' . escapeshellarg($database) . ' '
            . $this->getCommandConnectionArguments() . ' '
            . "< $extractedDir/schema.sql";

        $dataFiles = glob("$extractedDir/*.txt");
        $importDataCommand = 'mysqlimport ' . escapeshellarg($database) . ' --local --verbose '
            . $this->getCommandConnectionArguments() . ' '
            . implode(' ', $dataFiles);

        return "$importSchemaCommand && $importDataCommand";
    }

    /**
     * @return string
     */
    private function getCommandConnectionArguments()
    {
        if ($this->databaseConfig) {
            $connectionArguments = sprintf(
                '-h %s -P %s -u %s -p%s ',
                escapeshellarg($this->databaseConfig->host),
                escapeshellarg($this->databaseConfig->port),
                escapeshellarg($this->databaseConfig->username),
                escapeshellarg($this->databaseConfig->password)
            );
        } else {
            $connectionArguments = '';
        }
        return $connectionArguments;
    }

    /**
     * @param $database
     * @param $removeDefiners
     *
     * @return string
     */
    private function getDumpStructureCommand($database, $removeDefiners)
    {
        $dumpStructureCommand = 'mysqldump ' . escapeshellarg($database) . ' '
            . $this->getCommandConnectionArguments() . ' '
            . '--single-transaction --quick --lock-tables=false --skip-comments --no-data --verbose ';
        if ($removeDefiners) {
            $dumpStructureCommand .= '| sed "s/DEFINER=[^*]*\*/\*/g" ';
        }
        return $dumpStructureCommand;
    }

    /**
     * @param $filename
     *
     * @return string
     */
    private function normalizeFilename($filename)
    {
        // Normalize path
        if (!preg_match('%^\.{0,2}/%', $filename)) {
            $filename = './' . $filename;
        }

        // Normalize file extension
        $ext = self::getFileExtension();
        if (".$ext" != substr($filename, -(strlen($ext) + 1))) {
            $filename .= ".$ext";
        }

        return $filename;
    }

}
