<?php

declare(strict_types=1);

namespace ConductorCoreTest\Config;

use ConductorCore\Config\EnvironmentConfig;
use ConductorCore\Exception\RuntimeException;
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
 * CTAP-1724 / CTAP-1721 / CTAP-1741. `CONDUCTOR_ENVIRONMENT` and `CONDUCTOR_CRYPT_KEY` are the only
 * sources. The PHP file 5.x read as a fallback is ignored, and an unset environment fails.
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

    /** The file every scaffolded `config.php` used to include, as a 5.x host would still have it. */
    private function writeLegacyFile(string $environment, ?string $cryptKey): void
    {
        file_put_contents(
            $this->configDir . '/env.php',
            '<?php return ' . var_export(['environment' => $environment, 'crypt_key' => $cryptKey], true) . ';',
        );
    }

    public function testEnvironmentVariablesAreTheSource(): void
    {
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=production');
        putenv(EnvironmentConfig::CRYPT_KEY_VARIABLE . '=def000key');

        $config = EnvironmentConfig::resolve();

        $this->assertSame('production', $config->environment);
        $this->assertSame('def000key', $config->cryptKey);
        $this->assertSame(['environment' => 'production', 'crypt_key' => 'def000key'], $config->toArray());
    }

    /** A `${VAR}`-only configuration has no `ENC[…]` values and needs no key at all (CTAP-1730). */
    public function testNoKeyIsNull(): void
    {
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=production');

        $this->assertNull(EnvironmentConfig::resolve()->cryptKey);
    }

    /** `CONDUCTOR_CRYPT_KEY=` passed through by compose for an unset host variable is not a key. */
    public function testAnEmptyKeyCountsAsUnset(): void
    {
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=production');
        putenv(EnvironmentConfig::CRYPT_KEY_VARIABLE . '=');

        $this->assertNull(EnvironmentConfig::resolve()->cryptKey);
    }

    public function testAnUnsetEnvironmentFailsNamingTheVariable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CONDUCTOR_ENVIRONMENT is not set');

        EnvironmentConfig::resolve();
    }

    /** `${ENVIRONMENT}` without `:?` in compose hands conductor an empty string. Same failure. */
    public function testAnEmptyEnvironmentFailsIdentically(): void
    {
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CONDUCTOR_ENVIRONMENT is not set');

        EnvironmentConfig::resolve();
    }

    /** The CTAP-1741 acceptance case: file says `qa`, variable says `local`, the answer is `local`. */
    public function testAPresentLegacyFileHasNoEffectWhenTheVariableIsSet(): void
    {
        $this->writeLegacyFile('qa', 'filekey');
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=local');

        $config = $this->resolveWithoutDeprecations($this->configDir);

        $this->assertSame('local', $config->environment);
        $this->assertNull($config->cryptKey, 'the key in the file is not read either');
    }

    /** …and with the variable unset it fails rather than reading `qa` from the file. */
    public function testAPresentLegacyFileDoesNotRescueAnUnsetVariable(): void
    {
        $this->writeLegacyFile('qa', 'filekey');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CONDUCTOR_ENVIRONMENT is not set');

        $this->resolveWithoutDeprecations($this->configDir);
    }

    /** A 5.x `config.php` still passes `__DIR__`; the argument is accepted and ignored. */
    public function testTheLegacyConfigDirArgumentIsAccepted(): void
    {
        putenv(EnvironmentConfig::ENVIRONMENT_VARIABLE . '=uat');

        $this->assertSame('uat', EnvironmentConfig::resolve($this->configDir . '/')->environment);
    }

    /** Resolves with a handler that fails the test on any deprecation: 6.0 has nothing left to warn about. */
    private function resolveWithoutDeprecations(string $configDir): EnvironmentConfig
    {
        set_error_handler(function (int $level, string $message): bool {
            if ($level === E_USER_DEPRECATED) {
                $this->fail('unexpected deprecation: ' . $message);
            }

            return false;
        });

        try {
            return EnvironmentConfig::resolve($configDir);
        } finally {
            restore_error_handler();
        }
    }
}
