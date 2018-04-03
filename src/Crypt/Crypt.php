<?php

namespace ConductorCore\Crypt;

use ConductorCore\Exception\RuntimeException;
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
        return Crypto::encrypt($message, $key);
    }

    /**
     * @param string $ciphertext
     * @param string $key
     *
     * @return string
     */
    public function decrypt(string $ciphertext, string $key): string
    {
        $key = Key::loadFromAsciiSafeString($key);
        return Crypto::decrypt($ciphertext, $key);
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
                    if (!is_null($cryptKey) && preg_match('/^ENC\[defuse\/php-encryption,.*\]/', $data)) {
                        $data = preg_replace('/^ENC\[defuse\/php-encryption,(.*)\]/', '$1', $data);
                        try {
                            $data = $crypt->decrypt($data, $cryptKey);
                        } catch (\Exception $e) {
                            $message = "Error decrypting configuration key \"$dataKey\".";
                            throw new RuntimeException($message, 0, $e);
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
