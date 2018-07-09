<?php

namespace ConductorCore\Filesystem\MountManager;

use InvalidArgumentException;
use League\Flysystem\FilesystemNotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use React\EventLoop\Factory as EventLoopFactory;
use React\HttpClient\Client as ReactHttpClient;
use React\EventLoop\LoopInterface;
use React\HttpClient\Client;
use React\HttpClient\Request;
use React\HttpClient\Response;
use React\Stream\WritableResourceStream;
use React\Socket\Connector as Connector;

class MountManager extends \League\Flysystem\MountManager
{
    use \League\Flysystem\ConfigAwareTrait;

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Plugin\SyncPlugin
     */
    private $syncPlugin;

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
        $this->logger->debug("Start Pushing file $from to $to");
        list($prefixFrom, $from) = $this->getPrefixAndPath($from);

        $buffer = $this->getFilesystem($prefixFrom)->readStream($from);

        if ($buffer === false) {
            return false;
        }

        list($prefixTo, $to) = $this->getPrefixAndPath($to);

        $result = $this->getFilesystem($prefixTo)->putStream($to, $buffer, $config);

        if (is_resource($buffer)) {
            $this->logger->debug("End Pushing file $from to $to");
            fclose($buffer);
        }

        return $result;
    }

    public function getPrefixAndPath($path): array
    {
        return parent::getPrefixAndPath($path);
    }

    public function putFileAsync($loop, string $from, string $to, array $config = []): bool
    {
        $this->logger->debug("Start Pushing file $from to $to");
        list($prefixFrom, $from) = $this->getPrefixAndPath($from);
        $readStream = $this->getFilesystem($prefixFrom)->readStream($from);
        //$readStream = fopen($url, 'r');


        list($prefixTo, $to) = $this->getPrefixAndPath($to);
//        $this->setConfig($config);
//        $config = $this->prepareConfig($config);
//        $writeStream = $this->getFilesystem($prefixTo)->getAdapter()->writeStream($to, $readStream, $config);

//        if (!is_resource($writeStream)) {
//            if (isset($writeStream['type']) && $writeStream['type'] == 'file') {
//                $writeStream = fopen($file, 'w');
//            }
//        }

        $locationTo = $this->getFilesystem($prefixTo)->getAdapter()->applyPathPrefix($to);

        if(!file_exists(dirname($locationTo)))
            mkdir(dirname($locationTo), 0777, true);

        $writeStream = fopen($locationTo, 'w');


        stream_set_blocking($readStream, 0);
        stream_set_blocking($writeStream, 0);


        $read = new \React\Stream\Stream($readStream, $loop);
        $write = new \React\Stream\Stream($writeStream, $loop);

        $read->on('end', function () use ($from, $to) {
            $this->logger->debug("Stop Pushing file $from to $to");
        });

        $read->pipe($write);

        return true;
    }
}
