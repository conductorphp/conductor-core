<?php

namespace DevopsToolCore\Crypt;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

/**
 * Class Crypt
 *
 * @todo Replace defuse/php-encryption with libsodium methods once we update to PHP 7.2
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

    public static function decryptExpressiveConfig(array $config, string $key): array
    {
        $crypt = new self();
        $decryptConfig = function ($data) use (&$decryptConfig, $crypt, $key) {
            if (is_array($data)) {
                foreach ($data as $key => &$value) {
                    $value = $decryptConfig($value);
                }
                unset($value);
            } else {
                if (preg_match('/^ENC\[defuse\/php-encryption,.*\]/', $data)) {
                    $data = preg_replace('/^ENC\[defuse\/php-encryption,(.*)\]/', '$1', $data);
                    $data = $crypt->decrypt($data, $key);
                }
            }
            return $data;
        };
        return $decryptConfig($config);
    }
}
