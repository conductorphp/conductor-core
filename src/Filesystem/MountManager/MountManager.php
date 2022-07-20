<?php

namespace ConductorCore\Filesystem\MountManager;

use ConductorCore\Exception;
use InvalidArgumentException;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToResolveFilesystemMount;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MountManager extends \League\Flysystem\MountManager
{
    private $filesystems;
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
        $this->filesystems = $filesystems;
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

    // /**
    //  * @param string $location 
    //  * @param bool $deep 
    //  * @return DirectoryListing 
    //  * @throws UnableToResolveFilesystemMount 
    //  * @throws FilesystemException 
    //  * @throws InvalidArgumentException 
    //  */
    // public function listContents(string $location, bool $deep = self::LIST_SHALLOW): DirectoryListing
    // {
    //     $result = parent::listContents($location, $deep);

    //     list(, $location) = $this->getPrefixAndPath($location);
    //     $relativePathPos = strlen(trim($location, '/')) + 1;

    //     return $result->map(function(FileAttributes $file) use ($relativePathPos) {
    //         return )$file + ['relative_path' => substr($file['path'], $relativePathPos)];
    //     });
    // }

    /**
     * @param string $from in the format {prefix}://{path}
     * @param string $to   in the format {prefix}://{path}
     * @param array  $config
     *
     * @return bool True if all operations succeeded; False if any operations failed
     */
    public function sync(string $from, string $to, array $config = []): bool
    {
        return $this->syncPlugin->sync($this, $from, $to, $config);
    }

    // /**
    //  * @param       $from
    //  * @param       $to
    //  * @param array $config
    //  *
    //  * @return bool
    //  */
    // public function putFile(string $from, string $to, array $config = []): bool
    // {
    //     $this->logger->debug("Pushing file $from to $to");
    //     $origFrom = $from;
    //     list($prefixFrom, $from) = $this->getPrefixAndPath($from);
    //     $buffer = $this->getFilesystem($prefixFrom)->readStream($from);

    //     if ($buffer === false) {
    //         $this->logger->error(sprintf(
    //             'Failed to open stream "%s" for read.',
    //             $origFrom
    //         ));

    //         return false;
    //     }

    //     list($prefixTo, $to) = $this->getPrefixAndPath($to);

    //     $this->getFilesystem($prefixTo)->writeStream($to, $buffer, $config);

    //     if (is_resource($buffer)) {
    //         fclose($buffer);
    //     }

    //     return true;
    // }

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
                throw new InvalidArgumentException('No prefix detected in path: ' . $path, 0, $e);
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

    /**
     * Get the filesystem with the corresponding prefix.
     *
     * @param string $prefix
     *
     * @throws FilesystemNotFoundException
     *
     * @return FilesystemOperator
     */
    public function getFilesystem($prefix): FilesystemOperator
    {
        if ( ! isset($this->filesystems[$prefix])) {
            throw new \Exception('No filesystem mounted with prefix ' . $prefix);
        }

        return $this->filesystems[$prefix];
    }

    /**
     * Retrieve the prefix from an arguments array.
     *
     * @param array $arguments
     *
     * @throws InvalidArgumentException
     *
     * @return array [:prefix, :arguments]
     */
    public function filterPrefix(array $arguments)
    {
        if (empty($arguments)) {
            throw new InvalidArgumentException('At least one argument needed');
        }

        $path = array_shift($arguments);

        if ( ! is_string($path)) {
            throw new InvalidArgumentException('First argument should be a string');
        }

        [$prefix, $path] = $this->getPrefixAndPath($path);
        array_unshift($arguments, $path);

        return [$prefix, $arguments];
    }

}
