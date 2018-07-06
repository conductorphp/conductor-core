<?php

namespace ConductorCore\Crypt;

use ConductorCore\Exception;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

/**
 * Class Crypt
 *
 * @todo    Replace defuse/php-encryption with libsodium methods once we update to PHP 7.2
 * @package ConductorCore\Crypt
 */
class Crypt
{
    const ENCRYPTION_TYPE_DEFUSE_PHP_ENCRYPTION = 'defuse/php-encryption';

    /**
     * @return string
     */
    public function generateKey(): string
    {
        return Key::createNewRandomKey()->saveToAsciiSafeString();
    }

    /**
     * @param string $message
     * @param string $key
     *
     * @return string
     */
    public function encrypt(string $message, string $key): string
    {
        $key = Key::loadFromAsciiSafeString($key);
        return 'ENC[' . self::ENCRYPTION_TYPE_DEFUSE_PHP_ENCRYPTION . ',' . Crypto::encrypt($message, $key) . ']';
    }

    /**
     * @param string $ciphertext
     * @param string $key
     *
     * @return string
     */
    public function decrypt(string $ciphertext, string $key): string
    {
        $numMatches = preg_match_all('%^ENC\[([^,]+),(.*)\]$%', $ciphertext, $matches);
        if (0 == $numMatches) {
            throw new Exception\RuntimeException(sprintf(
                "\$ciphertext must be in the format ENC[\$encryptionType,\$ciphertext].\n"
                . "Provided ciphertext: %s",
                $ciphertext
            ));
        }

        $encryptionType = $matches[1][0];
        $ciphertext = $matches[2][0];

        switch ($encryptionType) {
            case self::ENCRYPTION_TYPE_DEFUSE_PHP_ENCRYPTION:
                $key = Key::loadFromAsciiSafeString($key);
                return Crypto::decrypt($ciphertext, $key);

            default:
                throw new Exception\RuntimeException(sprintf(
                    'Unsupported encryption type "%s". Supported encryption types: "%s".',
                    $encryptionType,
                    implode('", "', [self::ENCRYPTION_TYPE_DEFUSE_PHP_ENCRYPTION])
                ));
        }


    }

    /**
     * @param callable|array $config
     *
     * @return callable
     */
    public static function decryptExpressiveConfig($config, $cryptKey = null): callable
    {
        // Return as a generator to deal with merging individual file configs correctly.
        return function () use ($config, $cryptKey) {
            $crypt = new self();
            $decryptConfig = function ($data, $dataKey = null) use (&$decryptConfig, $crypt, $cryptKey) {
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
                } else {
                    if (!is_null($cryptKey) && preg_match('/^ENC\[[^,]+,.*\]/', $data)) {
                        try {
                            $data = $crypt->decrypt($data, $cryptKey);
                        } catch (\Exception $e) {
                            $message = "Error decrypting configuration key \"$dataKey\".";
                            throw new Exception\RuntimeException($message, 0, $e);
                        }
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
