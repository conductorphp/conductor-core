<?php

namespace DevopsToolCore\Database\ImportExportAdapter;

use App\Exception;
use DevopsToolCore\ShellCommandHelper;
use DevopsToolCore\Database\DatabaseConfig;
use Monolog\Handler\NullHandler;
use Psr\Log\LoggerInterface;

class MysqldumpDatabaseAdapter implements DatabaseImportExportAdapterInterface
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
        if (!self::isUsable()) {
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
                'gzip',
                'gunzip',
                'mysql',
                'mysqldump',
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
        return 'sql.gz';
    }

    /**
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

        $dumpStructureCommand = $this->getDumpStructureCommand($database, $removeDefiners);
        $dumpDataCommand = 'mysqldump ' . escapeshellarg($database) . ' '
            . $this->getCommandConnectionArguments() . ' '
            . '--single-transaction --quick --lock-tables=false '
            . '--order-by-primary --skip-comments --no-create-db --no-create-info --skip-triggers ';
        if ($ignoreTables) {
            foreach ($ignoreTables as $table) {
                $dumpDataCommand .= '--ignore-table=' . escapeshellarg("$database.$table") . ' ';
            }
        }

        $command = "($dumpStructureCommand && $dumpDataCommand) "
            . '| gzip -9 > ' . escapeshellarg($filename);

        try {
            $this->shellCommandHelper->runShellCommand($command, ShellCommandHelper::PRIORITY_LOW);
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
        $fileInfo = new \SplFileInfo($filename);
        $command = 'cat ' . escapeshellarg($filename) . ' ';
        if ('gz' == $fileInfo->getExtension()) {
            $command .= '| gunzip ';
        }
        $command .= '| mysql ' . escapeshellarg($database) . ' '
            . $this->getCommandConnectionArguments();

        try {
            $this->shellCommandHelper->runShellCommand($command, ShellCommandHelper::PRIORITY_LOW);
        } catch (\Exception $e) {
            throw new Exception\RuntimeException($e->getMessage());
        }
        return $filename;
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
        // Normalize filename
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
