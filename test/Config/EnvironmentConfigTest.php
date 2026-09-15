<?php

declare(strict_types=1);

namespace ConductorCoreTest\Config;

use ConductorCore\Config\EnvironmentConfig;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function putenv;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function rmdir;

/**
 * CTAP-1724 / CTAP-1721. `CONDUCTOR_ENVIRONMENT` and `CONDUCTOR_CRYPT_KEY` select the environment
 * and key, with `config/env.php` as the fallback the scaffolded `config.php` always read.
 */
class EnvironmentConfigTest extends TestCase
{
    private string $configDir;

    protected function setUp(): void
    {
        $this->configDir = sys_get_temp_dir() . '/conductor-env-config-' . uniqid();
        if (! is_dir($this->configDir)) {
            mkdir($this->configDir, 0700, true);
        }
    }

    protected function tearDown(): void
    {
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE);
        putenv(EnvironmentConfig::CRYPT_KEY_VARIABLE);

        @unlink($this->configDir . '/env.php');
        @rmdir($this->configDir);
    }

    private function writeEnvFile(string $environment, ?string $cryptKey): void
    {
        file_put_contents(
            $this->configDir . '/env.php',
            '<?php return ' . var_export(['environment' => $environment, 'crypt_key' => $cryptKey], true) . ';',
        );
    }

    public function testEnvironmentVariablesAreUsedWithNoEnvFile(): void
    {
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=production');
        putenv(EnvironmentConfig::CRYPT_KEY_VARIABLE . '=def000key');

        $config = EnvironmentConfig::resolve($this->configDir);

        $this->assertSame('production', $config->environment);
        $this->assertSame('def000key', $config->cryptKey);
        $this->assertSame(['environment' => 'production', 'crypt_key' => 'def000key'], $config->toArray());
    }

    /** All four noco conductors today: `env.php` only, no variables. Must behave identically. */
    public function testEnvFileIsTheFallback(): void
    {
        $this->writeEnvFile('qa', 'filekey');

        $config = EnvironmentConfig::resolve($this->configDir);

        $this->assertSame('qa', $config->environment);
        $this->assertSame('filekey', $config->cryptKey);
    }

    public function testTheEnvironmentVariableWinsWhenBothAreSet(): void
    {
        $this->writeEnvFile('qa', 'filekey');
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=production');

        $config = EnvironmentConfig::resolve($this->configDir);

        $this->assertSame('production', $config->environment);
        // Only the variable that is set overrides; the key still comes from the file.
        $this->assertSame('filekey', $config->cryptKey);
    }

    /** `CONDUCTOR_CRYPT_KEY=` passed through by compose for an unset host variable is not a key. */
    public function testAnEmptyEnvironmentVariableCountsAsUnset(): void
    {
        $this->writeEnvFile('qa', 'filekey');
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=');
        putenv(EnvironmentConfig::CRYPT_KEY_VARIABLE . '=');

        $config = EnvironmentConfig::resolve($this->configDir);

        $this->assertSame('qa', $config->environment);
        $this->assertSame('filekey', $config->cryptKey);
    }

    /** A `${VAR}`-only configuration has no `ENC[…]` values and needs no key at all. */
    public function testNoKeyFromEitherSourceIsNull(): void
    {
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=production');

        $config = EnvironmentConfig::resolve($this->configDir);

        $this->assertNull($config->cryptKey);
    }

    /** The historical default, kept until CTAP-1721 makes an unknown environment fail loudly. */
    public function testDefaultsToDevelopmentWithNothingSet(): void
    {
        $config = EnvironmentConfig::resolve($this->configDir);

        $this->assertSame(EnvironmentConfig::DEFAULT_ENVIRONMENT, $config->environment);
        $this->assertNull($config->cryptKey);
    }

    public function testTrailingSlashOnTheConfigDirIsTolerated(): void
    {
        $this->writeEnvFile('uat', null);

        $this->assertSame('uat', EnvironmentConfig::resolve($this->configDir . '/')->environment);
    }
}
