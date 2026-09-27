<?php

declare(strict_types=1);

namespace ConductorCore\Crypt;

use ConductorCore\Config\DecryptConfigPostProcessor;
use ConductorCore\Config\EnvironmentConfig;

use function getenv;
use function is_callable;
use function is_string;
use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * @deprecated Since 6.1, removed in 7.0 (CTAP-1968). Wire {@see DecryptConfigPostProcessor} as a
 *     `ConfigAggregator` post-processor instead; it decrypts both the `enc:v1:` envelope and the
 *     `ENC[defuse/php-encryption,…]` one. The crypt commands no longer use this class.
 */
class Crypt
{
    /**
     * The per-provider wrapper every scaffolded `config/config.php` called. Still decrypts, both
     * envelopes, with `$cryptKey` as `CONDUCTOR_CRYPT_KEY` and retired keys read from
     * `CONDUCTOR_CRYPT_KEYS_PREVIOUS` directly, and emits one `E_USER_DEPRECATED` per call.
     *
     * @deprecated Since 6.1, removed in 7.0. Register `new DecryptConfigPostProcessor($crypt)` instead.
     */
    public static function decryptExpressiveConfig(callable|array $config, ?string $cryptKey = null): callable
    {
        trigger_error(sprintf(
            '%s::decryptExpressiveConfig() is deprecated and conductor/core 7.0 removes it (CTAP-1968).'
            . ' Register ConductorCore\Config\DecryptConfigPostProcessor as the first ConfigAggregator'
            . ' post-processor in config/config.php and pass the YAML providers unwrapped.',
            self::class,
        ), E_USER_DEPRECATED);

        $previousKeys = getenv(EnvironmentConfig::CRYPT_KEYS_PREVIOUS_VARIABLE);
        $crypt        = $cryptKey === null && (! is_string($previousKeys) || $previousKeys === '')
            ? null
            : (new CryptResolverFactory())->fromKeys($cryptKey, is_string($previousKeys) ? $previousKeys : null);

        $postProcessor = new DecryptConfigPostProcessor($crypt);

        // A generator, as before, so the per-file configs a provider yields merge the same way.
        return static function () use ($config, $postProcessor) {
            if (is_callable($config)) {
                foreach ($config() as $data) {
                    yield $postProcessor($data);
                }
            } else {
                yield $postProcessor($config);
            }
        };
    }
}
