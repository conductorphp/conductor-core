<?php

declare(strict_types=1);

namespace ConductorCoreTest\Crypt;

use ConductorCore\Config\EnvironmentConfig;
use ConductorCore\Crypt\Crypt;
use ConductorCore\Crypt\SodiumCrypt;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_replace_recursive;
use function putenv;
use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * The deprecated per-provider wrapper (CTAP-1968): kept for one release so a `config/config.php`
 * written against core 5.x/6.0 keeps decrypting, now both envelopes, while saying what to change.
 */
final class CryptTest extends TestCase
{
    private const KEY   = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';
    private const VALUE = 'enc:v1:630dcd2966c43366:jMXfEsjW2rV1az2mT9WTo+AhUlLjQoS6NNAmtSXuNxqKW2vZrfHRC/MwcwaxX66JX248';

    /** @var list<string> */
    private array $deprecations = [];

    protected function setUp(): void
    {
        set_error_handler(function (int $level, string $message): bool {
            if ($level === E_USER_DEPRECATED) {
                $this->deprecations[] = $message;

                return true;
            }

            return false;
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        putenv(EnvironmentConfig::CRYPT_KEYS_PREVIOUS_VARIABLE);
    }

    /** @return array<string, mixed> */
    private function merge(callable $generator): array
    {
        $config = [];
        foreach ($generator() as $data) {
            $config = array_replace_recursive($config, $data);
        }

        return $config;
    }

    #[Test]
    public function stillDecryptsAnArrayAndEmitsOneDeprecation(): void
    {
        $config = $this->merge(Crypt::decryptExpressiveConfig(['plaintext' => 'x', 'encrypted' => self::VALUE], self::KEY));

        $this->assertSame(['plaintext' => 'x', 'encrypted' => 'Encrypt me!'], $config);
        $this->assertCount(1, $this->deprecations);
        $this->assertStringContainsString('DecryptConfigPostProcessor', $this->deprecations[0]);
        $this->assertStringContainsString('7.0 removes it', $this->deprecations[0]);
    }

    #[Test]
    public function stillDecryptsAProviderGeneratorFileByFile(): void
    {
        $provider = static fn (): array => [['a' => self::VALUE], ['b' => self::VALUE]];

        $this->assertSame(['a' => 'Encrypt me!', 'b' => 'Encrypt me!'], $this->merge(Crypt::decryptExpressiveConfig($provider, self::KEY)));
    }

    #[Test]
    public function withNoKeyValuesPassThroughAsBefore(): void
    {
        $this->assertSame(['encrypted' => self::VALUE], $this->merge(Crypt::decryptExpressiveConfig(['encrypted' => self::VALUE])));
    }

    /** The wrapper only ever received the current key, so retired keys are read from the variable directly. */
    #[Test]
    public function retiredKeysComeFromTheEnvironmentVariable(): void
    {
        putenv(EnvironmentConfig::CRYPT_KEYS_PREVIOUS_VARIABLE . '=' . self::KEY);

        $config = $this->merge(Crypt::decryptExpressiveConfig(['encrypted' => self::VALUE], SodiumCrypt::generateKey()));

        $this->assertSame('Encrypt me!', $config['encrypted']);
    }
}
