<?php

namespace ConductorCore\Filesystem\MountManager;

use ConductorCore\Exception;
use InvalidArgumentException;
use League\Flysystem\FilesystemNotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MountManager extends \League\Flysystem\MountManager
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Plugin\SyncPlugin
     */
    private $syncPlugin;
    /**
     * @var string
     */
    private $defaultPrefix;
    /**
     * @var string
     */
    private $workingDirectory;

    /**
     * MountManager constructor.
     *
     * @param array                $filesystems
     * @param LoggerInterface|null $logger
     */
    public function __construct(array $filesystems = [], LoggerInterface $logger = null)
    {
        parent::__construct($filesystems);
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
        $this->syncPlugin = new Plugin\SyncPlugin();
    }

    /**
     * @param Plugin\SyncPlugin $syncPlugin
     */
    public function setSyncPlugin(Plugin\SyncPlugin $syncPlugin): void
    {
        $this->syncPlugin = $syncPlugin;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->syncPlugin->setLogger($logger);
        $this->logger = $logger;
    }

    /**
     * @param string $path
     */
    public function setWorkingDirectory(string $path): void
    {
        $this->workingDirectory = $path;
    }

    /**
     * @return array
     */
    public function getFilesystemPrefixes(): array
    {
        return array_keys($this->filesystems);
    }

    /**
     * @param string $directory
     * @param bool   $recursive
     *
     * @throws InvalidArgumentException
     * @throws FilesystemNotFoundException
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false): array
    {
        $result = parent::listContents($directory, $recursive);

        list(, $directory) = $this->getPrefixAndPath($directory);
        $relativePathPos = strlen(trim($directory, '/')) + 1;

        foreach ($result as &$file) {
            $file['relative_path'] = substr($file['path'], $relativePathPos);
        }

        return $result;
    }

    /**
     * @param string $from in the format {prefix}://{path}
     * @param string $to   in the format {prefix}://{path}
     * @param array  $config
     *
     * @return void
     */
    public function sync(string $from, string $to, array $config = []): void
    {
        $this->syncPlugin->sync($this, $from, $to, $config);
    }

    /**
     * This method is the same as copy, except that it does a putStream rather than writeStream, allowing it to write
     * even if the file exists.
     *
     * @param       $from
     * @param       $to
     * @param array $config
     *
     * @return bool
     */
    public function putFile(string $from, string $to, array $config = []): bool
    {
        $this->logger->debug("Pushing file $from to $to");
        list($prefixFrom, $from) = $this->getPrefixAndPath($from);
        $buffer = $this->getFilesystem($prefixFrom)->readStream($from);

        if ($buffer === false) {
            return false;
        }

        list($prefixTo, $to) = $this->getPrefixAndPath($to);

        $result = $this->getFilesystem($prefixTo)->putStream($to, $buffer, $config);

        if (is_resource($buffer)) {
            fclose($buffer);
        }

        return $result;
    }

    /**
     * Updated to use local prefix if no prefix present in given path. Also, prepend working dir if present
     *
     * @inheritDoc
     */
    public function getPrefixAndPath($path): array
    {
        // If no prefix present, set local prefix
        if (strpos($path, '://') < 1) {
            try {
                $path = $this->resolveAbsolutePath($path);
            } catch (Exception\RuntimeException $e) {
                throw new InvalidArgumentException('No prefix detected in path: ' . $path, null, $e);
            }

            // Prepend local prefix and strip initial /
            $path = ltrim($path, DIRECTORY_SEPARATOR);
            $path = 'local://' . $path;
        }

        return explode('://', $path, 2);
    }

    /**
     * @param string $path
     * @return string
     * @throws Exception\RuntimeException
     */
    private function resolveAbsolutePath(string $path): string
    {
        if ($path[0] === DIRECTORY_SEPARATOR) {
            return $path;
        }

        if (!isset($this->workingDirectory)) {
            throw new Exception\RuntimeException("Could not resolve absolute path because working directory "
                . "is not set.");
        }

        // Remove ./, if present
        $path = preg_replace('%^\.' . DIRECTORY_SEPARATOR . '?%', '', $path);
        $path = $this->workingDirectory . DIRECTORY_SEPARATOR . $path;
        return rtrim($path, DIRECTORY_SEPARATOR);
    }

}
