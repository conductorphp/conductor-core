<?php

declare(strict_types=1);

namespace ConductorCoreTest\Config;

use ConductorCore\Config\ConfigDirectory;
use ConductorCore\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class ConfigDirectoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/conductor-config-' . uniqid();
        mkdir($this->root . '/config/app', 0777, true);
        file_put_contents($this->root . '/config/app/global.yaml', "---\n");
    }

    public function testTheOptionWinsThenTheVariableThenTheWorkingDirectory(): void
    {
        $this->assertSame($this->root, ConfigDirectory::resolve($this->root, '/nowhere', '/nowhere')->path);
        $this->assertSame($this->root, ConfigDirectory::resolve(null, $this->root, '/nowhere')->path);
        $this->assertSame($this->root, ConfigDirectory::resolve('', '', $this->root)->path);
    }

    public function testADirectoryWithoutTheMarkerIsRefusedNamingWhereTheChoiceCameFrom(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'No conductor configuration in "' . sys_get_temp_dir() . '" (from CONDUCTOR_CONFIG_DIR): expected '
            . sys_get_temp_dir() . '/config/app/global.yaml. Run conductor from the configuration directory,'
            . ' pass --config <dir>, or set CONDUCTOR_CONFIG_DIR.'
        );
        ConfigDirectory::resolve(null, sys_get_temp_dir(), '/');
    }

    public function testANonDirectoryIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--config names "/definitely/not/here", which is not a directory.');
        ConfigDirectory::resolve('/definitely/not/here', null, '/');
    }

    public function testAPreStandaloneHostIsRefusedWithTheGuide(): void
    {
        file_put_contents($this->root . '/config/config.php', "<?php return [];\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is laid out as a conductor host from before conductor/conductor');
        $this->expectExceptionMessage(ConfigDirectory::MIGRATION_GUIDE);
        ConfigDirectory::resolve($this->root, null, '/');
    }

    public function testAComposerJsonRequiringConductorAlsoMarksAPreStandaloneHost(): void
    {
        file_put_contents($this->root . '/composer.json', '{"require": {"conductor/core": "^6.0"}}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('before conductor/conductor');
        ConfigDirectory::resolve($this->root, null, '/');
    }

    public function testOptionFromArgvHandlesBothSpellings(): void
    {
        $this->assertSame('/x', ConfigDirectory::optionFromArgv(['conductor', '--config=/x', 'list']));
        $this->assertSame('/y', ConfigDirectory::optionFromArgv(['conductor', 'list', '--config', '/y']));
        $this->assertNull(ConfigDirectory::optionFromArgv(['conductor', 'list', '--config']));
        $this->assertNull(ConfigDirectory::optionFromArgv(['conductor', 'list']));
    }

    public function testPathsAndCacheFile(): void
    {
        $directory = ConfigDirectory::resolve($this->root, null, '/');

        $this->assertSame($this->root . '/config/app', $directory->appConfigPath());
        $this->assertSame($this->root . '/config/app/files', $directory->filesPath());
        $this->assertSame($this->root . '/config/app/environments/qa/files', $directory->environmentFilesPath('qa'));
        $this->assertSame(
            $this->root . '/data/cache/config-cache-1.2.3-qa.php',
            $directory->cachePath('qa', '1.2.3'),
        );
        $this->assertDirectoryExists($this->root . '/data/cache');
        $this->assertSame(
            $this->root . '/data/cache/config-cache-dev-main_abc-production.php',
            $directory->cachePath('production', 'dev-main@abc'),
            'anything not filename-safe in the version is folded to _',
        );
    }
}
