<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\Crypto class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Authenticated symmetric encryption for secrets stored at rest (eg. the SMTP password).
 *
 * Uses libsodium's secretbox (XSalsa20-Poly1305), which is built into PHP and authenticated, so
 * tampering with the ciphertext is detected. The key is a random 32-byte value written to
 * config.php as the constant TFISH_ENCRYPTION_KEY during installation. Because the key lives in
 * config.php (outside the web root, and not in the database), this protects a stored secret if the
 * database alone is disclosed; it does not protect against compromise of the config file itself.
 *
 * Stored ciphertext is tagged with a version prefix (enc:v1:) so reads can distinguish encrypted
 * values from legacy plaintext, and degrade safely if the key is absent or changed.
 *
 * Behaviour is deliberately permissive at the edges so the feature is opt-in and reversible:
 * - With no key available, encrypt() returns the plaintext unchanged (legacy plaintext storage).
 * - An empty string is never encrypted (keeps empty-password checks working).
 * - decrypt() returns plaintext unchanged when there is no enc:v1: prefix (legacy value), and
 *   returns an empty string if a tagged value cannot be decrypted (missing/changed key, or
 *   tampering) so callers fail soft rather than fatal.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */
class Crypto
{
    /** Marker prepended to encrypted values to identify them and allow versioning. */
    private const PREFIX = 'enc:v1:';

    /**
     * Generate a new random encryption key, base64 encoded for storage in config.php.
     *
     * @return string Base64 encoded 32-byte key.
     */
    public static function newKeyBase64(): string
    {
        return \base64_encode(\sodium_crypto_secretbox_keygen());
    }

    /**
     * Encrypt a secret for storage.
     *
     * Returns the input unchanged if it is empty or if no key is available (so installs without a
     * key continue to store plaintext). Otherwise returns a self-describing enc:v1: token.
     *
     * @param string $plaintext The value to encrypt.
     * @param string|null $keyBase64 Optional base64 key (defaults to the TFISH_ENCRYPTION_KEY constant).
     * @return string Encrypted enc:v1: token, or the original value when not encrypting.
     */
    public static function encrypt(string $plaintext, ?string $keyBase64 = null): string
    {
        if ($plaintext === '') {
            return $plaintext;
        }

        $key = self::resolveKey($keyBase64);

        if ($key === null) {
            return $plaintext;
        }

        $nonce = \random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = \sodium_crypto_secretbox($plaintext, $nonce, $key);

        return self::PREFIX . \base64_encode($nonce . $cipher);
    }

    /**
     * Decrypt a stored secret.
     *
     * A value without the enc:v1: prefix is treated as legacy plaintext and returned unchanged. A
     * tagged value that cannot be decrypted (no/changed key, corruption or tampering) returns an
     * empty string so the caller fails soft.
     *
     * @param string $stored The stored value.
     * @param string|null $keyBase64 Optional base64 key (defaults to the TFISH_ENCRYPTION_KEY constant).
     * @return string The decrypted plaintext, the original value (legacy plaintext), or ''.
     */
    public static function decrypt(string $stored, ?string $keyBase64 = null): string
    {
        if (\strncmp($stored, self::PREFIX, \strlen(self::PREFIX)) !== 0) {
            return $stored;
        }

        $key = self::resolveKey($keyBase64);

        if ($key === null) {
            return '';
        }

        $decoded = \base64_decode(\substr($stored, \strlen(self::PREFIX)), true);

        if ($decoded === false || \strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }

        $nonce = \substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = \substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        try {
            $plaintext = \sodium_crypto_secretbox_open($cipher, $nonce, $key);
        } catch (\SodiumException $e) {
            return '';
        }

        return $plaintext === false ? '' : $plaintext;
    }

    /**
     * Resolve and validate the raw binary key from an explicit value or the config constant.
     *
     * @param string|null $keyBase64 Explicit base64 key, or null to use TFISH_ENCRYPTION_KEY.
     * @return string|null 32-byte binary key, or null if unavailable/malformed.
     */
    private static function resolveKey(?string $keyBase64): ?string
    {
        if ($keyBase64 === null) {
            if (!\defined('TFISH_ENCRYPTION_KEY')) {
                return null;
            }

            $keyBase64 = (string) \constant('TFISH_ENCRYPTION_KEY');
        }

        $key = \base64_decode($keyBase64, true);

        if ($key === false || \strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return null;
        }

        return $key;
    }
}
