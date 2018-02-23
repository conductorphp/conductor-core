<?php

namespace ConductorCoreTest\Crypt;

use ConductorCore\Crypt\Crypt;
use PHPUnit\Framework\TestCase;

class CryptTest extends TestCase
{
    const TEST_KEY = 'def00000de54d8d8cb4804e9748968de2edc1b130ba31df5c5fa0eb662bfe4c6d2caaec614eb5de3628bd3220331f21b3e3b6ccb1332a7691d081b6317c721657ded544f';
    const TEST_MESSAGE = 'Encrypt me!';

    /**
     * @var Crypt
     */
    private $crypt;

    public function setUp()
    {
        $this->crypt = new Crypt();
    }

    public function testGenerateKey()
    {
        $this->assertInternalType('string', $this->crypt->generateKey());
    }

    public function testEncrypt()
    {
        $ciphertext = $this->crypt->encrypt(self::TEST_MESSAGE, self::TEST_KEY);
        $this->assertNotEquals(self::TEST_MESSAGE, $ciphertext);
    }

    public function testDecrypt()
    {
        $ciphertext = $this->crypt->encrypt(self::TEST_MESSAGE, self::TEST_KEY);
        $message = $this->crypt->decrypt($ciphertext, self::TEST_KEY);
        $this->assertEquals(self::TEST_MESSAGE, $message);
    }

    public function testDecryptExpressiveConfigWithNoKey()
    {
        $ciphertext = $this->crypt->encrypt(self::TEST_MESSAGE, self::TEST_KEY);
        $config = [
            'plaintext' => self::TEST_MESSAGE,
            'encrypted' => "ENC[defuse/php-encryption,$ciphertext]",
        ];

        $generator = $this->crypt::decryptExpressiveConfig($config);
        $config = [];
        foreach ($generator() as $data) {
            $config = array_replace_recursive($config, $data);
        }
        $this->assertEquals(self::TEST_MESSAGE, $config['plaintext']);
        $this->assertEquals("ENC[defuse/php-encryption,$ciphertext]", $config['encrypted']);
    }

    public function testDecryptExpressiveConfigWithArray()
    {
        $ciphertext = $this->crypt->encrypt(self::TEST_MESSAGE, self::TEST_KEY);
        $config = [
            'plaintext' => self::TEST_MESSAGE,
            'encrypted' => "ENC[defuse/php-encryption,$ciphertext]",
        ];

        $generator = $this->crypt::decryptExpressiveConfig($config, self::TEST_KEY);
        $config = [];
        foreach ($generator() as $data) {
            $config = array_replace_recursive($config, $data);
        }
        $this->assertEquals(self::TEST_MESSAGE, $config['plaintext']);
        $this->assertEquals(self::TEST_MESSAGE, $config['encrypted']);
    }

    public function testDecryptExpressiveConfigWithGenerator()
    {
        $config = function () {
            $ciphertext = $this->crypt->encrypt(self::TEST_MESSAGE, self::TEST_KEY);
            return [
                [
                    'plaintext' => self::TEST_MESSAGE,
                    'encrypted' => "ENC[defuse/php-encryption,$ciphertext]",
                ]
            ];
        };

        $generator = $this->crypt::decryptExpressiveConfig($config, self::TEST_KEY);
        $config = [];
        foreach ($generator() as $data) {
            $config = array_replace_recursive($config, $data);
        }
        $this->assertEquals(self::TEST_MESSAGE, $config['plaintext']);
        $this->assertEquals(self::TEST_MESSAGE, $config['encrypted']);
    }

}
