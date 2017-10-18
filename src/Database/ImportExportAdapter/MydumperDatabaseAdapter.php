<?php

namespace DevopsToolCore\Database\ImportExportAdapter;

use App\Exception;
use DevopsToolCore\ShellCommandHelper;
use DevopsToolCore\Database\DatabaseConfig;
use Monolog\Handler\NullHandler;
use Psr\Log\LoggerInterface;

class MydumperDatabaseAdapter implements DatabaseImportExportAdapterInterface
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
                'mysql',
                'mydumper',
                'myloader',
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
        $command = $this->getMyDumperExportCommand($database, $workingDir, $filename, $ignoreTables, $removeDefiners);

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
        $this->shellCommandHelper->runShellCommand(
            'cd ' . escapeshellarg($workingDir)
            . ' && tar xzf ' . escapeshellarg(basename($filename))
        );
        $extractedDir = substr($filename, 0, -(strlen(self::getFileExtension()) + 1));
        $command = $this->getMyDumperImportCommand($database, $extractedDir);

        try {
            $this->shellCommandHelper->runShellCommand($command, ShellCommandHelper::PRIORITY_LOW);
            $this->shellCommandHelper->runShellCommand('rm -rf ' . escapeshellarg($extractedDir));
        } catch (\Exception $e) {
            throw new Exception\RuntimeException($e->getMessage());
        }
        return $filename;
    }

    private function getMyDumperExportCommand($database, $workingDir, $filename, $ignoreTables, $removeDefiners)
    {
        # find command has a bug where it will fail if you do not have read permissions to the current working directory
        # Temporarily switching into the working directory while running this command.
        # @link https://unix.stackexchange.com/questions/349894/can-i-tell-find-to-to-not-restore-initial-working-directory 

        $command = 'mysql --skip-column-names --silent -e "SHOW TABLES from \`' . $database . '\`;" '
            . $this->getMysqlCommandConnectionArguments() . ' ';
        $tables = trim($this->shellCommandHelper->runShellCommand($command));
        if ($tables) {
            $tables = array_diff(
                explode(PHP_EOL, $tables),
                $ignoreTables
            );
        }

        $dumpStructureCommand = 'mydumper --database ' . escapeshellarg($database) . ' --outputdir ' . escapeshellarg(
                $workingDir
            ) . ' '
            . '-v 3 --no-data --triggers --events --routines --less-locking --lock-all-tables '
            . $this->getMysqldumperCommandConnectionArguments() . ' ';

        if ($removeDefiners) {
            $dumpStructureCommand .= '&& cd ' . escapeshellarg($workingDir)
                . ' && find . -name "*-schema-triggers.sql" -exec sed -ri \'s|DEFINER=[^ ]+ *||g\' {} \;';
        }

        $dumpDataCommand = 'mydumper --database ' . escapeshellarg($database) . ' --outputdir ' . escapeshellarg(
                $workingDir
            ) . ' '
            . '-v 3 --no-schemas --less-locking --lock-all-tables '
            . $this->getMysqldumperCommandConnectionArguments() . ' ';
        if ($tables) {
            $dumpDataCommand .= '--tables-list ' . implode(',', $tables);
        }

        // Magento has an issue with its tables where it defaults timestamp fields to '0000-00-00 00:00:00', which mysql doesn't like on import
        $fixTimestampDefaultIssueCommand = 'cd ' . escapeshellarg($workingDir)
            . ' && find . -name "*.sql" -exec sed -ri "s|(timestamp\|datetime) (NOT )?NULL DEFAULT \'0000-00-00 00:00:00\'|\1 \2NULL DEFAULT CURRENT_TIMESTAMP|gI" {} \;';

        $tarCommand = 'cd ' . escapeshellarg(dirname($workingDir)) . ' && '
            . 'tar czf ' . escapeshellarg(basename($filename)) . ' ' . escapeshellarg(basename($workingDir));

        return "$dumpStructureCommand && $dumpDataCommand && $fixTimestampDefaultIssueCommand && $tarCommand";
    }

    private function getMyDumperImportCommand($database, $extractedDir)
    {
        $importCommand = 'myloader --database ' . escapeshellarg($database) . ' --directory ' . escapeshellarg(
                $extractedDir
            )
            . ' -v 3 --overwrite-tables '
            . $this->getMysqldumperCommandConnectionArguments();

        return $importCommand;
    }

    /**
     * @return string
     */
    private function getMysqlCommandConnectionArguments()
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
     * @return string
     */
    private function getMysqldumperCommandConnectionArguments()
    {
        if ($this->databaseConfig) {
            $connectionArguments = sprintf(
                '-h %s -P %s -u %s -p %s ',
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
