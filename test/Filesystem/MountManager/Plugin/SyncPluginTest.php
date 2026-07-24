<?php

namespace ConductorCoreTest\Filesystem\MountManager\Plugin;

use Aws\S3\S3ClientInterface;
use ConductorCore\Filesystem\MountManager\Plugin\SyncPlugin;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class SyncPluginTest extends TestCase
{
    use ProphecyTrait;

    private SyncPlugin $plugin;

    public function setUp(): void
    {
        $this->plugin = new SyncPlugin();
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

    private function isObjectStorage(FilesystemOperator $filesystem): bool
    {
        $method = new \ReflectionMethod($this->plugin, 'isObjectStorage');
        return $method->invoke($this->plugin, $filesystem);
    }
}
