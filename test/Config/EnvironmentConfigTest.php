<?php

declare(strict_types=1);

namespace ConductorCoreTest\Config;

use ConductorCore\Config\EnvironmentConfig;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function putenv;
use function restore_error_handler;
use function rmdir;
use function set_error_handler;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

use const E_USER_DEPRECATED;

/**
 * CTAP-1724 / CTAP-1721 / CTAP-1741. `CONDUCTOR_ENVIRONMENT` and `CONDUCTOR_CRYPT_KEY` select the
 * environment and key. `config/env.php` and the `development` default are deprecated fallbacks on
 * the 5.x line and go away in 6.0.
 */
class EnvironmentConfigTest extends TestCase
{
    private string $configDir;

    /** @var list<string> */
    private array $deprecations = [];

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

    /** Resolves while recording every E_USER_DEPRECATED, so a test can assert the exact count. */
    private function resolve(string $configDir): EnvironmentConfig
    {
        $this->deprecations = [];
        set_error_handler(function (int $level, string $message): bool {
            if ($level === E_USER_DEPRECATED) {
                $this->deprecations[] = $message;

                return true;
            }

            return false;
        });

        try {
            return EnvironmentConfig::resolve($configDir);
        } finally {
            restore_error_handler();
        }
    }

    private function assertOneDeprecationNaming(string ...$fragments): void
    {
        $this->assertCount(1, $this->deprecations, 'exactly one deprecation warning');
        foreach ($fragments as $fragment) {
            $this->assertStringContainsString($fragment, $this->deprecations[0]);
        }
        $this->assertStringContainsString('CTAP-1741', $this->deprecations[0]);
    }

    public function testEnvironmentVariablesAreUsedWithNoEnvFile(): void
    {
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=production');
        putenv(EnvironmentConfig::CRYPT_KEY_VARIABLE . '=def000key');

        $config = $this->resolve($this->configDir);

        $this->assertSame('production', $config->environment);
        $this->assertSame('def000key', $config->cryptKey);
        $this->assertSame(['environment' => 'production', 'crypt_key' => 'def000key'], $config->toArray());
        $this->assertSame([], $this->deprecations, 'the supported path emits nothing');
    }

    /** All four noco conductors today: `env.php` only, no variables. Same values, now with a warning. */
    public function testEnvFileIsTheFallbackAndIsDeprecated(): void
    {
        $this->writeEnvFile('qa', 'filekey');

        $config = $this->resolve($this->configDir);

        $this->assertSame('qa', $config->environment);
        $this->assertSame('filekey', $config->cryptKey);
        $this->assertOneDeprecationNaming($this->configDir . '/env.php', 'CONDUCTOR_ENVIRONMENT');
    }

    public function testTheEnvironmentVariableWinsWhenBothAreSet(): void
    {
        $this->writeEnvFile('qa', 'filekey');
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=production');

        $config = $this->resolve($this->configDir);

        $this->assertSame('production', $config->environment);
        // Only the variable that is set overrides; the key still comes from the file.
        $this->assertSame('filekey', $config->cryptKey);
        $this->assertOneDeprecationNaming($this->configDir . '/env.php');
    }

    /**
     * A stale `env.php` whose values are all overridden is exactly the file the migration guide has
     * to tell people to `rm -f`; the warning is what makes it visible (CTAP-1741).
     */
    public function testAPresentEnvFileWarnsEvenWhenNothingIsReadFromIt(): void
    {
        $this->writeEnvFile('qa', 'filekey');
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=production');
        putenv(EnvironmentConfig::CRYPT_KEY_VARIABLE . '=def000key');

        $config = $this->resolve($this->configDir);

        $this->assertSame('production', $config->environment);
        $this->assertSame('def000key', $config->cryptKey);
        $this->assertOneDeprecationNaming($this->configDir . '/env.php');
    }

    /** `CONDUCTOR_CRYPT_KEY=` passed through by compose for an unset host variable is not a key. */
    public function testAnEmptyEnvironmentVariableCountsAsUnset(): void
    {
        $this->writeEnvFile('qa', 'filekey');
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=');
        putenv(EnvironmentConfig::CRYPT_KEY_VARIABLE . '=');

        $config = $this->resolve($this->configDir);

        $this->assertSame('qa', $config->environment);
        $this->assertSame('filekey', $config->cryptKey);
        $this->assertOneDeprecationNaming($this->configDir . '/env.php');
    }

    /** A `${VAR}`-only configuration has no `ENC[…]` values and needs no key at all. */
    public function testNoKeyFromEitherSourceIsNull(): void
    {
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=production');

        $config = $this->resolve($this->configDir);

        $this->assertNull($config->cryptKey);
        $this->assertSame([], $this->deprecations);
    }

    /** The historical default: still `development` on 5.x, with the warning 6.0 turns into a failure. */
    public function testDefaultsToDevelopmentWithNothingSetAndWarns(): void
    {
        $config = $this->resolve($this->configDir);

        $this->assertSame(EnvironmentConfig::DEFAULT_ENVIRONMENT, $config->environment);
        $this->assertNull($config->cryptKey);
        $this->assertOneDeprecationNaming('CONDUCTOR_ENVIRONMENT is not set', 'development');
    }

    /** An empty variable is unset, so with no file it is the same deprecated default. */
    public function testAnEmptyEnvironmentVariableWithNoFileIsTheDeprecatedDefault(): void
    {
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=');

        $config = $this->resolve($this->configDir);

        $this->assertSame(EnvironmentConfig::DEFAULT_ENVIRONMENT, $config->environment);
        $this->assertOneDeprecationNaming('CONDUCTOR_ENVIRONMENT is not set');
    }

    public function testTrailingSlashOnTheConfigDirIsTolerated(): void
    {
        $this->writeEnvFile('uat', null);

        $this->assertSame('uat', $this->resolve($this->configDir . '/')->environment);
        $this->assertOneDeprecationNaming($this->configDir . '/env.php');
    }
}
