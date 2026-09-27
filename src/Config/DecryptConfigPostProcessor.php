<?php

declare(strict_types=1);

namespace ConductorCore\Config;

use ConductorCore\Crypt\CryptInterface;
use ConductorCore\Exception\CryptException;
use ConductorCore\Exception\RuntimeException;

use function is_array;
use function is_string;
use function sprintf;

/**
 * Decrypts every encrypted string in the merged config, once, at load time (CTAP-1968).
 *
 * Wired into the project's `config/config.php` as the FIRST `ConfigAggregator` post-processor, ahead
 * of `${VAR}` interpolation, so a decrypted plaintext is what the rest of the load sees:
 *
 *     $environmentConfig = EnvironmentConfig::resolve();
 *     $crypt             = (new CryptResolverFactory())->fromEnvironmentConfig($environmentConfig);
 *
 *     $aggregator = new ConfigAggregator([…providers…], $cacheConfig['config_cache_path'], [
 *         new DecryptConfigPostProcessor($crypt),
 *         new EnvVarInterpolationPostProcessor(),
 *     ]);
 *
 * Replaces the per-provider `Crypt::decryptExpressiveConfig()` wrapper: one pass over everything,
 * including `config/autoload/*.php`, instead of a wrapper each YAML provider remembers to apply.
 *
 * Which strings are encrypted is decided by the value alone (its envelope), so nothing here knows
 * which keys are secret. A `null` crypt (no `CONDUCTOR_CRYPT_KEY` and no `CONDUCTOR_CRYPT_KEYS_PREVIOUS`)
 * leaves every value as written: that is what lets a keyless conductor boot and run
 * `crypt:generate-key`, and why a deploy plan asserts the key as its first step (CTAP-1963). A
 * value that IS an envelope and cannot be opened fails the load naming the config path, the key id
 * and the variables that could carry it:
 *
 *     Error decrypting configuration key "application_orchestration/application/skeleton/…/key":
 *     no key with id 630dcd2966c43366 is carried by CONDUCTOR_CRYPT_KEY or CONDUCTOR_CRYPT_KEYS_PREVIOUS
 */
final class DecryptConfigPostProcessor
{
    public function __construct(private readonly ?CryptInterface $crypt)
    {
    }

    /**
     * @param array<string, mixed> $config The merged config, as `ConfigAggregator` hands it over.
     * @return array<string, mixed>
     */
    public function __invoke(array $config): array
    {
        if ($this->crypt === null) {
            return $config;
        }

        return $this->walk($config, null);
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function walk(array $data, ?string $path): array
    {
        foreach ($data as $key => $value) {
            $childPath = $path === null ? (string) $key : $path . '/' . $key;

            if (is_array($value)) {
                $data[$key] = $this->walk($value, $childPath);
            } elseif (is_string($value) && $this->crypt->isEncrypted($value)) {
                $data[$key] = $this->decrypt($value, $childPath);
            }
        }

        return $data;
    }

    private function decrypt(string $value, string $path): string
    {
        try {
            return $this->crypt->decrypt($value);
        } catch (CryptException $e) {
            throw new RuntimeException(
                sprintf('Error decrypting configuration key "%s": %s', $path, $e->getMessage()),
                0,
                $e,
            );
        }
    }
}
