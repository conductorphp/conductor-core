<?php

declare(strict_types=1);

namespace ConductorCore\Config;

use function getenv;
use function is_array;
use function is_file;
use function is_string;
use function rtrim;
use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * Which environment conductor is running against, and the key for any `ENC[…]` values.
 *
 * Both come from the process environment (CTAP-1724, CTAP-1721):
 *
 *     CONDUCTOR_ENVIRONMENT=production CONDUCTOR_CRYPT_KEY=def000… conductor app:deploy
 *
 * They used to be read out of `config/env.php` by every project's scaffolded `config/config.php`.
 * That forces a PHP file to be written onto every instance just to carry two values, and it is the
 * one thing a platform whose secret store surfaces values as environment variables cannot do for us.
 * The file is still honored as a fallback on the 5.x line, with a deprecation warning whenever it is
 * present; `conductor/core` 6.0 stops reading it, and an unset `CONDUCTOR_ENVIRONMENT` fails instead
 * of defaulting to `development` (CTAP-1741).
 *
 * An empty environment variable counts as unset, consistent with {@see EnvVarInterpolator}.
 *
 * A project's `config/config.php` reads:
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

    /**
     * @deprecated since 5.4, removed in 6.0 (CTAP-1741). An unset `CONDUCTOR_ENVIRONMENT` fails
     *     instead of selecting this environment.
     */
    public const DEFAULT_ENVIRONMENT = 'development';

    public function __construct(
        public string $environment,
        public ?string $cryptKey = null,
    ) {
    }

    /**
     * Environment variables first, then the deprecated `<configDir>/env.php`, then the historical
     * default.
     *
     * A non-empty `CONDUCTOR_ENVIRONMENT` or `CONDUCTOR_CRYPT_KEY` always wins. When the file is
     * present, its values fill whichever of the two the environment did not supply, exactly as the
     * scaffolded `config.php` always used them, and one `E_USER_DEPRECATED` warning names the file.
     * With neither a variable nor a file, the environment is `development` and the warning says so.
     * Both fallbacks go away in 6.0 (CTAP-1741).
     *
     * @param string $configDir The project's `config/` directory, i.e. `__DIR__` from `config.php`.
     */
    public static function resolve(string $configDir): self
    {
        $environment = self::fromEnvironment(self::ENVIRONMENT_VARIABLE);
        $cryptKey    = self::fromEnvironment(self::CRYPT_KEY_VARIABLE);

        $envFile = rtrim($configDir, '/') . '/env.php';
        if (is_file($envFile)) {
            $fromFile = self::readEnvFile($envFile);

            $environment ??= isset($fromFile['environment']) && is_string($fromFile['environment'])
                ? $fromFile['environment']
                : null;
            $cryptKey ??= isset($fromFile['crypt_key']) && is_string($fromFile['crypt_key'])
                ? $fromFile['crypt_key']
                : null;

            trigger_error(
                sprintf(
                    'Reading %s is deprecated and conductor/core 6.0 ignores the file (CTAP-1741).'
                    . ' Set %s, and %s while the configuration still carries ENC[...] values,'
                    . ' in the process environment and delete the file.',
                    $envFile,
                    self::ENVIRONMENT_VARIABLE,
                    self::CRYPT_KEY_VARIABLE,
                ),
                E_USER_DEPRECATED,
            );
        }

        if ($environment === null) {
            if (! is_file($envFile)) {
                trigger_error(
                    sprintf(
                        '%1$s is not set. Defaulting to the "%2$s" environment is deprecated and'
                        . ' conductor/core 6.0 fails instead (CTAP-1741). Set %1$s in the process environment.',
                        self::ENVIRONMENT_VARIABLE,
                        self::DEFAULT_ENVIRONMENT,
                    ),
                    E_USER_DEPRECATED,
                );
            }

            $environment = self::DEFAULT_ENVIRONMENT;
        }

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
        $config = include $file;

        return is_array($config) ? $config : [];
    }
}
