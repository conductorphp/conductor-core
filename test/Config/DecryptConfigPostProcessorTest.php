<?php

declare(strict_types=1);

namespace ConductorCoreTest\Config;

use ConductorCore\Config\DecryptConfigPostProcessor;
use ConductorCore\Crypt\CryptResolverFactory;
use ConductorCore\Crypt\SodiumCrypt;
use ConductorCore\Exception\RuntimeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CTAP-1968. The walker replaces `Crypt::decryptExpressiveConfig()`: one pass over the merged
 * config, decrypting by envelope, naming the config path when a value cannot be opened.
 */
final class DecryptConfigPostProcessorTest extends TestCase
{
    private const KEY   = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';
    private const VALUE = 'enc:v1:630dcd2966c43366:jMXfEsjW2rV1az2mT9WTo+AhUlLjQoS6NNAmtSXuNxqKW2vZrfHRC/MwcwaxX66JX248';

    private function config(): array
    {
        return [
            'plaintext' => 'left alone',
            'number'    => 42,
            'flag'      => true,
            'nothing'   => null,
            'top'       => self::VALUE,
            'nested'    => [
                'deeper' => ['secret' => self::VALUE, 'list' => [self::VALUE, 'plain']],
                'sibling' => 'also left alone',
            ],
        ];
    }

    #[Test]
    public function decryptsEveryEnvelopeAndLeavesEverythingElseAlone(): void
    {
        $processor = new DecryptConfigPostProcessor((new CryptResolverFactory())->fromKeys(self::KEY, null));

        $result = $processor($this->config());

        $this->assertSame('Encrypt me!', $result['top']);
        $this->assertSame('Encrypt me!', $result['nested']['deeper']['secret']);
        $this->assertSame(['Encrypt me!', 'plain'], $result['nested']['deeper']['list']);
        $this->assertSame('left alone', $result['plaintext']);
        $this->assertSame('also left alone', $result['nested']['sibling']);
        $this->assertSame(42, $result['number']);
        $this->assertTrue($result['flag']);
        $this->assertNull($result['nothing']);
    }

    /** No key configured: encrypted values stay as written, so a keyless conductor still boots (CTAP-1963 asserts the key). */
    #[Test]
    public function withoutACryptTheConfigIsReturnedUntouched(): void
    {
        $processor = new DecryptConfigPostProcessor(null);

        $this->assertSame($this->config(), $processor($this->config()));
    }

    #[Test]
    public function aValueThatCannotBeOpenedFailsTheLoadNamingThePathTheKeyIdAndTheVariables(): void
    {
        $processor = new DecryptConfigPostProcessor((new CryptResolverFactory())->fromKeys(SodiumCrypt::generateKey(), null));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Error decrypting configuration key "top": no key with id 630dcd2966c43366 is carried by'
            . ' CONDUCTOR_CRYPT_KEY or CONDUCTOR_CRYPT_KEYS_PREVIOUS',
        );

        $processor($this->config());
    }

    /** The path is the full slash-joined route to the value, not the accumulated path of its siblings. */
    #[Test]
    public function theNamedPathIsTheValuesOwn(): void
    {
        $processor = new DecryptConfigPostProcessor((new CryptResolverFactory())->fromKeys(SodiumCrypt::generateKey(), null));

        try {
            $processor(['a' => ['x' => 'plain', 'y' => 'plain'], 'b' => ['c' => [0 => 'plain', 1 => self::VALUE]]]);
            $this->fail('expected the load to fail');
        } catch (RuntimeException $e) {
            $this->assertStringStartsWith('Error decrypting configuration key "b/c/1": ', $e->getMessage());
        }
    }
}
