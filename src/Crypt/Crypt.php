<?php

namespace DevopsToolCore\Crypt;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

/**
 * Class Crypt
 *
 * @todo    Replace defuse/php-encryption with libsodium methods once we update to PHP 7.2
 * @package DevopsToolCore\Crypt
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
     * @param callable $config
     *
     * @return callable
     */
    public static function decryptExpressiveConfig(callable $config, $cryptKey = null): callable
    {
        // Do nothing if no $cryptKey set. Not throwing an exception here because this allows for simpler code in
        // config/config.php
        if (is_null($cryptKey)) {
            return $config;
        }

        // Return as a generator to deal with merging individual file configs correctly.
        return function () use ($config, $cryptKey) {
            $crypt = new self();
            $decryptConfig = function ($data) use (&$decryptConfig, $crypt, $cryptKey) {
                if (is_array($data)) {
                    foreach ($data as $key => &$value) {
                        $value = $decryptConfig($value);
                    }
                    unset($value);
                } else {
                    if (preg_match('/^ENC\[defuse\/php-encryption,.*\]/', $data)) {
                        $data = preg_replace('/^ENC\[defuse\/php-encryption,(.*)\]/', '$1', $data);
                        $data = $crypt->decrypt($data, $cryptKey);
                    }
                }
                return $data;
            };

            foreach ($config() as $data) {
                yield $decryptConfig($data);
            }
        };
    }
}
