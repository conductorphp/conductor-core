<?php

namespace ConductorCore\Crypt;

use ConductorCore\Exception;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;

/**
 * @todo    Replace defuse/php-encryption with libsodium methods once we update to PHP 7.2
 */
class Crypt
{
    private const ENCRYPTION_TYPE_DEFUSE_PHP_ENCRYPTION = 'defuse/php-encryption';

    public function generateKey(): string
    {
        return Key::createNewRandomKey()->saveToAsciiSafeString();
    }

    /**
     * @throws EnvironmentIsBrokenException
     * @throws BadFormatException
     */
    public function encrypt(string $message, string $key): string
    {
        $key = Key::loadFromAsciiSafeString($key);
        return 'ENC[' . self::ENCRYPTION_TYPE_DEFUSE_PHP_ENCRYPTION . ',' . Crypto::encrypt($message, $key) . ']';
    }

    /**
     * @throws EnvironmentIsBrokenException
     * @throws BadFormatException
     * @throws WrongKeyOrModifiedCiphertextException
     */
    public function decrypt(string $ciphertext, string $key): string
    {
        $numMatches = preg_match_all('%^ENC\[([^,]+),(.*)\]$%', $ciphertext, $matches);
        if (0 === $numMatches) {
            throw new Exception\RuntimeException(sprintf(
                "\$ciphertext must be in the format ENC[\$encryptionType,\$ciphertext].\n"
                . "Provided ciphertext: %s",
                $ciphertext
            ));
        }

        $encryptionType = $matches[1][0];
        $ciphertext = $matches[2][0];

        if ($encryptionType === self::ENCRYPTION_TYPE_DEFUSE_PHP_ENCRYPTION) {
            $key = Key::loadFromAsciiSafeString($key);
            return Crypto::decrypt($ciphertext, $key);
        }

        throw new Exception\RuntimeException(sprintf(
            'Unsupported encryption type "%s". Supported encryption types: "%s".',
            $encryptionType,
            implode('", "', [self::ENCRYPTION_TYPE_DEFUSE_PHP_ENCRYPTION])
        ));


    }

    public static function decryptExpressiveConfig(callable|array $config, ?string $cryptKey = null): callable
    {
        // Return as a generator to deal with merging individual file configs correctly.
        return static function () use ($config, $cryptKey) {
            $crypt = new self();
            $decryptConfig = function (array|string|null $data, $dataKey = null) use (&$decryptConfig, $crypt, $cryptKey) {
                if (is_null($data)) {
                    return null;
                }

                if (is_array($data)) {
                    foreach ($data as $key => &$value) {
                        if ($dataKey) {
                            $dataKey .= "/$key";
                        } else {
                            $dataKey = $key;
                        }
                        $value = $decryptConfig($value, $dataKey);
                    }
                    unset($value);
                } elseif (!is_null($cryptKey) && preg_match('/^ENC\[[^,]+,.*\]/', $data)) {
                    try {
                        $data = $crypt->decrypt($data, $cryptKey);
                    } catch (\Exception $e) {
                        $message = "Error decrypting configuration key \"$dataKey\".";
                        throw new Exception\RuntimeException($message, 0, $e);
                    }
                }
                return $data;
            };

            if (is_callable($config)) {
                foreach ($config() as $data) {
                    yield $decryptConfig($data);
                }
            } else {
                yield $decryptConfig($config);
            }
        };
    }
}
