<?php

declare(strict_types=1);

namespace Tests;

/**
 * \Tests\CryptoTest class file.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     tests
 */

 /**
 * Unit tests for the Crypto encryption helper.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        class \Tfish\Crypto
 */

use PHPUnit\Framework\TestCase;
use Tfish\Crypto;

class CryptoTest extends TestCase
{
    private string $key;

    protected function setUp(): void
    {
        $this->key = Crypto::newKeyBase64();
    }

    public function testNewKeyIsThirtyTwoBytes(): void
    {
        $raw = \base64_decode(Crypto::newKeyBase64(), true);
        $this->assertNotFalse($raw);
        $this->assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, \strlen($raw));
    }

    public function testRoundTrip(): void
    {
        $secret = 'hunter2-correct-horse';
        $cipher = Crypto::encrypt($secret, $this->key);

        $this->assertStringStartsWith('enc:v1:', $cipher);
        $this->assertNotSame($secret, $cipher);
        $this->assertSame($secret, Crypto::decrypt($cipher, $this->key));
    }

    public function testEncryptionIsNonDeterministic(): void
    {
        // A fresh nonce each call means identical input yields different ciphertext.
        $a = Crypto::encrypt('same', $this->key);
        $b = Crypto::encrypt('same', $this->key);
        $this->assertNotSame($a, $b);
        $this->assertSame('same', Crypto::decrypt($a, $this->key));
        $this->assertSame('same', Crypto::decrypt($b, $this->key));
    }

    public function testEmptyStringIsNotEncrypted(): void
    {
        $this->assertSame('', Crypto::encrypt('', $this->key));
        $this->assertSame('', Crypto::decrypt('', $this->key));
    }

    public function testEncryptWithoutKeyReturnsPlaintext(): void
    {
        // No key available => legacy plaintext storage.
        $this->assertSame('secret', Crypto::encrypt('secret', ''));
    }

    public function testLegacyPlaintextPassesThroughDecrypt(): void
    {
        // A value with no enc:v1: prefix is treated as legacy plaintext.
        $this->assertSame('plain-old-password', Crypto::decrypt('plain-old-password', $this->key));
    }

    public function testTaggedValueWithWrongKeyFailsSoft(): void
    {
        $cipher = Crypto::encrypt('secret', $this->key);
        $otherKey = Crypto::newKeyBase64();
        $this->assertSame('', Crypto::decrypt($cipher, $otherKey));
    }

    public function testTaggedValueWithNoKeyFailsSoft(): void
    {
        $cipher = Crypto::encrypt('secret', $this->key);
        $this->assertSame('', Crypto::decrypt($cipher, ''));
    }

    public function testTamperedCiphertextFailsSoft(): void
    {
        $cipher = Crypto::encrypt('secret', $this->key);
        // Flip the final character of the base64 payload.
        $tampered = \substr($cipher, 0, -1) . ($cipher[-1] === 'A' ? 'B' : 'A');
        $this->assertSame('', Crypto::decrypt($tampered, $this->key));
    }

    public function testMalformedKeyFailsSoft(): void
    {
        $cipher = Crypto::encrypt('secret', $this->key);
        $this->assertSame('', Crypto::decrypt($cipher, 'not-a-valid-key'));
        // Too-short key cannot encrypt either, so plaintext is returned.
        $this->assertSame('secret', Crypto::encrypt('secret', 'short'));
    }
}
