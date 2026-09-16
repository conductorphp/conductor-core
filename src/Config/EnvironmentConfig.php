<?php

declare(strict_types=1);

namespace ConductorCore\Config;

use ConductorCore\Exception\RuntimeException;

use function getenv;
use function is_string;
use function sprintf;

/**
 * Which environment conductor is running against, and the key for any `ENC[…]` values.
 *
 * Both come from the process environment, and from nowhere else (CTAP-1724, CTAP-1721, CTAP-1741):
 *
 *     CONDUCTOR_ENVIRONMENT=production CONDUCTOR_CRYPT_KEY=def000… conductor app:deploy
 *
 * Every project's scaffolded `config/config.php` used to include a PHP file carrying the two values,
 * which forced a file to be written onto every instance and was the one thing a platform whose
 * secret store surfaces values as environment variables could not do for us. Core 5.x read that
 * file as a deprecated fallback; 6.0 does not read it at all, and a missing environment fails the
 * bootstrap instead of quietly selecting `development`. `bin/conductor` exits 1 on that failure, so
 * a runner without the variable stops before any plan step runs.
 *
 * An empty environment variable counts as unset, consistent with {@see EnvVarInterpolator}.
 *
 * A project's `config/config.php` reads:
 *
 *     $environmentConfig = EnvironmentConfig::resolve();
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

    public function __construct(
        public string $environment,
        public ?string $cryptKey = null,
    ) {
    }

    /**
     * `CONDUCTOR_ENVIRONMENT` is required; `CONDUCTOR_CRYPT_KEY` is optional and `null` when unset.
     *
     * @param string|null $configDir Ignored. Accepted so a `config.php` written against 5.x, which
     *     passed `__DIR__`, keeps working; nothing is read from the directory any more.
     * @throws RuntimeException When `CONDUCTOR_ENVIRONMENT` is unset or empty.
     */
    public static function resolve(?string $configDir = null): self
    {
        $environment = self::fromEnvironment(self::ENVIRONMENT_VARIABLE);

        if ($environment === null) {
            throw new RuntimeException(sprintf(
                '%1$s is not set. Conductor selects its environment from that variable alone, and an'
                . ' empty value counts as unset. Export it where conductor runs, for example'
                . ' %1$s=production (CTAP-1741).',
                self::ENVIRONMENT_VARIABLE,
            ));
        }

        return new self($environment, self::fromEnvironment(self::CRYPT_KEY_VARIABLE));
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
}
