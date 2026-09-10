<?php

namespace ConductorCoreTest\Filesystem\MountManager\Plugin;

use Aws\S3\S3ClientInterface;
use ConductorCore\Filesystem\MountManager\MountManager;
use ConductorCore\Filesystem\MountManager\Plugin\SyncPlugin;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\WhitespacePathNormalizer;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class SyncPluginTest extends TestCase
{
    use ProphecyTrait;

    private SyncPlugin $plugin;
    private string $root;

    public function setUp(): void
    {
        $this->plugin = new SyncPlugin();
        $this->root = sys_get_temp_dir() . '/sync-plugin-test-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0777, true);
    }

    public function tearDown(): void
    {
        if (is_dir($this->root)) {
            $this->removeDirectory($this->root);
        }
    }

    public function testIsObjectStorageDetectsS3(): void
    {
        $filesystem = new Filesystem(
            new AwsS3V3Adapter($this->prophesize(S3ClientInterface::class)->reveal(), 'test-bucket')
        );

        $this->assertTrue($this->isObjectStorage($filesystem));
    }

    public function testIsObjectStorageIsFalseForLocalFilesystem(): void
    {
        $filesystem = new Filesystem(new LocalFilesystemAdapter(sys_get_temp_dir()));

        $this->assertFalse($this->isObjectStorage($filesystem));
    }

    /**
     * The adapter lookup reflects into Flysystem's private $adapter property. It must not call
     * ReflectionProperty::setAccessible(), which is a no-op since PHP 8.1 and deprecated in 8.5.
     */
    public function testIsObjectStorageDoesNotTriggerDeprecations(): void
    {
        $deprecations = [];
        set_error_handler(
            static function (int $errno, string $errstr) use (&$deprecations): bool {
                $deprecations[] = $errstr;
                return true;
            },
            E_DEPRECATED
        );

        try {
            $this->isObjectStorage(new Filesystem(new LocalFilesystemAdapter(sys_get_temp_dir())));
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
    }

    /**
     * A file source with an existing directory destination must copy the file INTO that directory, the way cp,
     * rsync and aws s3 cp do. It used to copy nothing at all and still return true, because the directory
     * satisfied the destination existence check and its own mtime won the timestamp comparison.
     */
    public function testSyncCopiesFileIntoExistingDirectoryDestination(): void
    {
        file_put_contents("{$this->root}/snapshot.tgz", 'snapshot contents');
        mkdir("{$this->root}/destination");

        $result = $this->plugin->sync($this->mountManager(), 'local://snapshot.tgz', 'local://destination');

        $this->assertTrue($result);
        $this->assertFileExists("{$this->root}/destination/snapshot.tgz");
        $this->assertSame('snapshot contents', file_get_contents("{$this->root}/destination/snapshot.tgz"));
    }

    public function testSyncOverwritesAnOlderFileInsideADirectoryDestination(): void
    {
        file_put_contents("{$this->root}/snapshot.tgz", 'new contents');
        mkdir("{$this->root}/destination");
        file_put_contents("{$this->root}/destination/snapshot.tgz", 'stale contents');
        touch("{$this->root}/destination/snapshot.tgz", time() - 3600);

        $result = $this->plugin->sync($this->mountManager(), 'local://snapshot.tgz', 'local://destination');

        $this->assertTrue($result);
        $this->assertSame('new contents', file_get_contents("{$this->root}/destination/snapshot.tgz"));
    }

    public function testSyncLeavesACurrentFileInsideADirectoryDestinationAlone(): void
    {
        file_put_contents("{$this->root}/snapshot.tgz", 'source contents');
        touch("{$this->root}/snapshot.tgz", time() - 3600);
        mkdir("{$this->root}/destination");
        file_put_contents("{$this->root}/destination/snapshot.tgz", 'destination contents');

        $result = $this->plugin->sync($this->mountManager(), 'local://snapshot.tgz', 'local://destination');

        $this->assertTrue($result);
        $this->assertSame('destination contents', file_get_contents("{$this->root}/destination/snapshot.tgz"));
    }

    public function testSyncCopiesFileToAFileDestination(): void
    {
        file_put_contents("{$this->root}/snapshot.tgz", 'snapshot contents');
        mkdir("{$this->root}/destination");

        $result = $this->plugin->sync(
            $this->mountManager(),
            'local://snapshot.tgz',
            'local://destination/renamed.tgz'
        );

        $this->assertTrue($result);
        $this->assertSame('snapshot contents', file_get_contents("{$this->root}/destination/renamed.tgz"));
        $this->assertFileDoesNotExist("{$this->root}/destination/snapshot.tgz");
    }

    public function testSyncCopiesFileToADestinationThatDoesNotExistYet(): void
    {
        file_put_contents("{$this->root}/snapshot.tgz", 'snapshot contents');

        $result = $this->plugin->sync($this->mountManager(), 'local://snapshot.tgz', 'local://destination.tgz');

        $this->assertTrue($result);
        $this->assertSame('snapshot contents', file_get_contents("{$this->root}/destination.tgz"));
    }

    private function mountManager(): MountManager
    {
        return new MountManager(
            ['local' => new Filesystem(new LocalFilesystemAdapter($this->root))],
            new WhitespacePathNormalizer()
        );
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = "$directory/$entry";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }

    private function isObjectStorage(FilesystemOperator $filesystem): bool
    {
        $method = new \ReflectionMethod($this->plugin, 'isObjectStorage');
        return $method->invoke($this->plugin, $filesystem);
    }
}
