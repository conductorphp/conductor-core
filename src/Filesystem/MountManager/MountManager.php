<?php

namespace ConductorCore\Filesystem\MountManager;

use ConductorCore\Exception;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\PathNormalizer;
use Psr\Log\LoggerInterface;

class MountManager extends \League\Flysystem\MountManager
{
    private array $filesystems;
    private PathNormalizer $pathNormalizer;
    private Plugin\SyncPlugin $syncPlugin;
    private string $defaultPrefix;
    private string $workingDirectory;

    public function __construct(
        array          $filesystems,
        PathNormalizer $pathNormalizer
    ) {
        $this->filesystems = $filesystems;
        parent::__construct($filesystems);
        $this->syncPlugin = new Plugin\SyncPlugin();
        $this->pathNormalizer = $pathNormalizer;
    }

    public function setSyncPlugin(Plugin\SyncPlugin $syncPlugin): void
    {
        $this->syncPlugin = $syncPlugin;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->syncPlugin->setLogger($logger);
    }

    public function setWorkingDirectory(string $path): void
    {
        $this->workingDirectory = $path;
    }

    public function getFilesystemPrefixes(): array
    {
        return array_keys($this->filesystems);
    }

    /**
     * @param string $from in the format {prefix}://{path}
     * @param string $to in the format {prefix}://{path}
     *
     * @return bool True if all operations succeeded; False if any operations failed
     * @throws FilesystemException
     */
    public function sync(string $from, string $to, array $config = []): bool
    {
        return $this->syncPlugin->sync($this, $from, $to, $config);
    }

    /**
     * Overrode because mountManager copy command was failing with bad credentials after PHP 8.2 upgrade
     */
    public function copy(string $source, string $destination, array $config = []): void
    {
        $this->syncPlugin->sync($this, $source, $destination, $config);
    }

    /**
     * Get the filesystem with the corresponding prefix.
     */
    public function getFilesystem(string $prefix): FilesystemOperator
    {
        if (!isset($this->filesystems[$prefix])) {
            throw new Exception\RuntimeException('No filesystem mounted with prefix ' . $prefix);
        }

        return $this->filesystems[$prefix];
    }

    /**
     * Retrieve the prefix from an arguments array.
     *
     * @return array [:prefix, :arguments]
     * @throws Exception\InvalidArgumentException
     *
     */
    public function filterPrefix(array $arguments): array
    {
        if (empty($arguments)) {
            throw new Exception\InvalidArgumentException('At least one argument needed');
        }

        $path = array_shift($arguments);

        if (!is_string($path)) {
            throw new Exception\InvalidArgumentException('First argument should be a string');
        }

        [$prefix, $path] = $this->getPrefixAndPath($path);
        array_unshift($arguments, $path);

        return [$prefix, $arguments];
    }

    /**
     * Updated to use local prefix if no prefix present in given path. Also, prepend working dir if present
     */
    public function getPrefixAndPath(string $path): array
    {
        // If no prefix present, set local prefix
        if (strpos($path, '://') < 1) {
            try {
                $path = $this->resolveAbsolutePath($path);
            } catch (Exception\RuntimeException $e) {
                throw new Exception\InvalidArgumentException('No prefix detected in path: ' . $path, 0, $e);
            }

            // Prepend local prefix and strip initial /
            $path = ltrim($path, DIRECTORY_SEPARATOR);
            $path = 'local://' . $path;
        }

        [$prefix, $path] = explode('://', $path, 2);
        return [
            $prefix,
            $this->pathNormalizer->normalizePath($path),
        ];
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
        $path = preg_replace('%^(\.' . DIRECTORY_SEPARATOR . ')?%', '', $path);
        $path = $this->workingDirectory . DIRECTORY_SEPARATOR . $path;
        return rtrim($path, DIRECTORY_SEPARATOR);
    }

}
