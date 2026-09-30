<?php

declare(strict_types=1);

namespace ConductorCore\Crypt;

use ConductorCore\Config\EnvironmentConfig;
use ConductorCore\Exception\CryptException;

use function base64_decode;
use function hash;
use function sprintf;
use function strlen;
use function substr;
use function trim;

use const SODIUM_CRYPTO_SECRETBOX_KEYBYTES;

/**
 * The sodium keys conductor encrypts and decrypts with, from the two values the
 * {@see CryptResolverFactory} resolved:
 *
 * - `CONDUCTOR_CRYPT_KEY` — exactly one base64-encoded 32-byte key, what `crypt:generate-key`
 *   prints. The CURRENT key: `crypt:encrypt` writes under it.
 * - `CONDUCTOR_CRYPT_KEYS_PREVIOUS` — retired keys, kept only so values encrypted before a rotation
 *   stay readable. Order carries no meaning: an envelope names the key it used.
 *
 * A key's id is the first 16 hex characters of sha256(material), the same id the middleware's
 * `rmg/lib-crypt-sodium` derives, so moving a key into the retired list never renumbers it.
 *
 * Constructed from VALUES, never from the environment, so it cannot fall back to an application's
 * own `ENCRYPTION_KEY`. Parsing is lazy and a malformed key throws at use, not at construction: the
 * container builds every command just to render the list.
 */
final class SodiumKeyRing
{
    private const KEY_BYTES = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;

    /** @var array{id: string, material: string}|null */
    private ?array $current = null;

    /** @var array<string, string>|null material by key id */
    private ?array $previous = null;

    /** @param list<string> $previousKeys already split and trimmed, empty entries removed */
    public function __construct(private readonly ?string $currentKey, private readonly array $previousKeys = [])
    {
    }

    /** @return array{id: string, material: string} */
    public function current(): array
    {
        if ($this->currentKey === null || trim($this->currentKey) === '') {
            throw new CryptException(sprintf(
                'No encryption key configured. Set %s (exactly one base64-encoded 32-byte key, from'
                . ' `conductor crypt:generate-key`) before encrypting a value. %s holds retired keys only'
                . ' and cannot stand in for it.',
                EnvironmentConfig::CRYPT_KEY_VARIABLE,
                EnvironmentConfig::CRYPT_KEYS_PREVIOUS_VARIABLE,
            ));
        }

        return $this->current ??= self::parse(EnvironmentConfig::CRYPT_KEY_VARIABLE, trim($this->currentKey));
    }

    /** The key material for $keyId, or null when neither variable carries it. */
    public function find(string $keyId): ?string
    {
        if ($this->currentKey !== null && trim($this->currentKey) !== '' && $this->current()['id'] === $keyId) {
            return $this->current()['material'];
        }

        if ($this->previous === null) {
            $this->previous = [];
            foreach ($this->previousKeys as $position => $encoded) {
                $key = self::parse(
                    sprintf('%s entry #%d', EnvironmentConfig::CRYPT_KEYS_PREVIOUS_VARIABLE, $position + 1),
                    $encoded,
                );
                $this->previous[$key['id']] ??= $key['material'];
            }
        }

        return $this->previous[$keyId] ?? null;
    }

    /** @return array{id: string, material: string} */
    private static function parse(string $source, string $encoded): array
    {
        $material = base64_decode($encoded, true);
        if ($material === false) {
            throw new CryptException(sprintf(
                '%s is not valid base64. %s holds exactly one key; put retired keys in %s, newline- or'
                . ' comma-separated.',
                $source,
                EnvironmentConfig::CRYPT_KEY_VARIABLE,
                EnvironmentConfig::CRYPT_KEYS_PREVIOUS_VARIABLE,
            ));
        }

        if (strlen($material) !== self::KEY_BYTES) {
            throw new CryptException(sprintf(
                '%s decodes to %d bytes; expected exactly %d (generate one with `conductor crypt:generate-key`).',
                $source,
                strlen($material),
                self::KEY_BYTES,
            ));
        }

        return ['id' => substr(hash('sha256', $material), 0, 16), 'material' => $material];
    }
}
