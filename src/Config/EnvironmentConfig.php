<?php

declare(strict_types=1);

namespace ConductorCore\Config;

use function getenv;
use function is_array;
use function is_file;
use function is_string;
use function rtrim;

/**
 * Which environment conductor is running against, and the key for any `ENC[…]` values.
 *
 * Both used to be read straight out of `config/env.php` by every project's scaffolded
 * `config/config.php`. That forces a PHP file to be written onto every instance just to carry two
 * values, and it is the one thing a platform whose secret store surfaces values as environment
 * variables cannot do for us. So the environment variables come first and `env.php` is the fallback
 * (CTAP-1724, CTAP-1721):
 *
 *     CONDUCTOR_ENVIRONMENT=production CONDUCTOR_CRYPT_KEY=def000… conductor app:deploy
 *
 * The names are the placeholder strings `config/env.php.dist` has always shipped with. An empty
 * environment variable counts as unset, consistent with {@see EnvVarInterpolator}.
 *
 * A project's `config/config.php` replaces its `env.php` block with:
 *
 *     $environmentConfig = EnvironmentConfig::resolve(__DIR__);
 *     $environment       = $environmentConfig->environment;
 *     $cryptKey          = $environmentConfig->cryptKey;
 *     …
 *     new ArrayProvider($environmentConfig->toArray()),
 *
 * The crypt key is optional. A configuration that carries its secrets as `${VAR}` placeholders
 * instead of `ENC[…]` values needs no key at all.
 */
final readonly class EnvironmentConfig
{
    public const ENVIRONMENT_VARIABLE = 'CONDUCTOR_ENVIRONMENT';
    public const CRYPT_KEY_VARIABLE   = 'CONDUCTOR_CRYPT_KEY';
    public const DEFAULT_ENVIRONMENT  = 'development';

    public function __construct(
        public string $environment,
        public ?string $cryptKey = null,
    ) {
    }

    /**
     * Environment variables first, then `<configDir>/env.php`, then the historical defaults.
     *
     * The file's values are used exactly as written, as the scaffolded `config.php` always did, so
     * an `env.php`-only setup behaves identically to before. The only new behavior is that a
     * non-empty `CONDUCTOR_ENVIRONMENT` or `CONDUCTOR_CRYPT_KEY` in the process environment wins.
     *
     * @param string $configDir The project's `config/` directory, i.e. `__DIR__` from `config.php`.
     */
    public static function resolve(string $configDir): self
    {
        $fromFile = self::readEnvFile(rtrim($configDir, '/') . '/env.php');

        $environment = self::fromEnvironment(self::ENVIRONMENT_VARIABLE)
            ?? (isset($fromFile['environment']) && is_string($fromFile['environment'])
                ? $fromFile['environment']
                : self::DEFAULT_ENVIRONMENT);

        $cryptKey = self::fromEnvironment(self::CRYPT_KEY_VARIABLE)
            ?? (isset($fromFile['crypt_key']) && is_string($fromFile['crypt_key'])
                ? $fromFile['crypt_key']
                : null);

        return new self($environment, $cryptKey);
    }

    /**
     * The `['environment' => …, 'crypt_key' => …]` pair the merged config has always carried.
     *
     * @return array{environment: string, crypt_key: string|null}
     */
    public function toArray(): array
    {
        return [
            'environment' => $this->environment,
            'crypt_key'   => $this->cryptKey,
        ];
    }

    private static function fromEnvironment(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, mixed> */
    private static function readEnvFile(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $config = include $file;

        return is_array($config) ? $config : [];
    }
}
