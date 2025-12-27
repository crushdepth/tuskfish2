<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\WebAuthnService class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 */

/**
 * Wrapper for lbuchs/WebAuthn library.
 *
 * Isolates third-party dependency and adds Tuskfish-specific configuration.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 * @var         \lbuchs\WebAuthn\WebAuthn $webAuthn Instance of the WebAuthn library.
 */

class WebAuthnService
{
    private \lbuchs\WebAuthn\WebAuthn $webAuthn;
    private string $rpName;
    private string $rpId;

    /**
     * Constructor.
     *
     * @param   string $rpName Relying party name (site name).
     * @param   string $rpId Relying party ID (domain).
     */
    public function __construct(string $rpName, string $rpId)
    {
        $this->rpName = $rpName;
        $this->rpId = $rpId;

        // Require HTTPS except localhost.
        // Support reverse proxies (nginx, Apache) that terminate SSL.
        $isHttps = !empty($_SERVER['HTTPS']) ||
                   ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ||
                   ($_SERVER['REQUEST_SCHEME'] ?? '') === 'https';

        // Allow localhost for development (WebAuthn spec permits this).
        // Check both IP and hostname for Docker compatibility.
        $isLocalhost = $_SERVER['REMOTE_ADDR'] === '127.0.0.1' ||
                       $_SERVER['REMOTE_ADDR'] === '::1' ||
                       $_SERVER['SERVER_NAME'] === 'localhost' ||
                       \str_ends_with($_SERVER['SERVER_NAME'], '.localhost');

        if (!$isLocalhost && !$isHttps) {
            throw new \RuntimeException('WebAuthn requires HTTPS');
        }

        // Validate RP ID matches current domain.
        // Use SERVER_NAME (server-configured) not HTTP_HOST (user-controlled).
        $currentDomain = $_SERVER['SERVER_NAME'];
        if ($rpId !== $currentDomain && !\str_ends_with($currentDomain, '.' . $rpId)) {
            throw new \RuntimeException('RP ID mismatch');
        }

        // Load library.
        require_once TFISH_CLASS_PATH . 'webauthn/WebAuthn.php';

        $this->webAuthn = new \lbuchs\WebAuthn\WebAuthn($rpName, $rpId, null, true);
    }

    /**
     * Get registration options for client.
     *
     * @param   int $userId User ID.
     * @param   string $userEmail User email.
     * @param   array $excludeCredentialIds Existing credential IDs to exclude.
     * @return  object Registration options.
     */
    public function getRegistrationOptions(int $userId, string $userEmail, array $excludeCredentialIds = []): object
    {
        // Generic configuration for all authenticator types.
        $options = $this->webAuthn->getCreateArgs(
            (string)$userId,                    // userId
            $userEmail,                         // userName
            $userEmail,                         // userDisplayName
            60,                                 // timeout (seconds)
            'preferred',                        // residentKey: prefer passkeys, allow non-resident
            'preferred',                        // userVerification: prefer biometrics, allow touch
            null,                               // crossPlatformAttachment: allow both platform and cross-platform
            $excludeCredentialIds               // excludeCredentialIds
        );

        return $options;
    }

    /**
     * Verify registration response.
     *
     * @param   string $clientDataJSON Client data JSON from browser.
     * @param   string $attestationObject Attestation object from browser.
     * @param   string $challenge Expected challenge (base64).
     * @return  object Registration data with credentialId, publicKey, signCount, etc.
     */
    public function verifyRegistration(string $clientDataJSON, string $attestationObject, string $challenge): object
    {
        // Decode base64url-encoded data from browser
        $clientDataDecoded = \lbuchs\WebAuthn\Binary\ByteBuffer::fromBase64Url($clientDataJSON)->getBinaryString();
        $attestationDecoded = \lbuchs\WebAuthn\Binary\ByteBuffer::fromBase64Url($attestationObject)->getBinaryString();
        $challengeBuffer = new \lbuchs\WebAuthn\Binary\ByteBuffer($challenge);

        $data = $this->webAuthn->processCreate(
            $clientDataDecoded,
            $attestationDecoded,
            $challengeBuffer,
            false,  // requireUserVerification: false (generic support)
            true,   // requireUserPresent: true
            false,  // failIfRootMismatch: false (no attestation validation)
            false   // requireCtsProfileMatch: false (Android compatibility)
        );

        return $data;
    }

    /**
     * Get authentication options for client.
     *
     * @param   array $credentialIds Array of credential IDs for this user.
     * @return  object Authentication options.
     */
    public function getAuthenticationOptions(array $credentialIds): object
    {
        $options = $this->webAuthn->getGetArgs(
            $credentialIds,     // credentialIds
            60,                 // timeout (seconds)
            true,               // allowUsb
            true,               // allowNfc
            true,               // allowBle
            true,               // allowHybrid
            true,               // allowInternal (platform authenticators)
            'preferred'         // requireUserVerification: preferred
        );

        return $options;
    }

    /**
     * Verify authentication response.
     *
     * @param   string $clientDataJSON Client data JSON from browser.
     * @param   string $authenticatorData Authenticator data from browser.
     * @param   string $signature Signature from browser.
     * @param   string $credentialPublicKey Base64-encoded public key.
     * @param   string $challenge Expected challenge (base64).
     * @param   int|null $prevSignatureCount Previous signature count.
     * @return  bool True on success, false on failure.
     */
    public function verifyAuthentication(
        string $clientDataJSON,
        string $authenticatorData,
        string $signature,
        string $credentialPublicKey,
        string $challenge,
        ?int $prevSignatureCount = null
    ): bool
    {
        // Decode base64-encoded data from browser
        $clientDataDecoded = \base64_decode($clientDataJSON);
        $authenticatorDataDecoded = \base64_decode($authenticatorData);
        $signatureDecoded = \base64_decode($signature);

        // Decode public key from database (stored as base64-encoded PEM string)
        // Library expects PEM string, not binary
        $publicKeyPem = \base64_decode($credentialPublicKey);

        $challengeBuffer = new \lbuchs\WebAuthn\Binary\ByteBuffer($challenge);

        return $this->webAuthn->processGet(
            $clientDataDecoded,
            $authenticatorDataDecoded,
            $signatureDecoded,
            $publicKeyPem,  // Pass PEM string directly, not ByteBuffer
            $challengeBuffer,
            $prevSignatureCount,
            false,  // requireUserVerification: false (generic support)
            true    // requireUserPresent: true
        );
    }

    /**
     * Get current challenge as base64 string.
     *
     * @return  string Base64-encoded challenge.
     */
    public function getChallenge(): string
    {
        return $this->webAuthn->getChallenge()->getBinaryString();
    }

    /**
     * Get signature counter from last verification.
     *
     * @return  int Signature counter.
     */
    public function getSignatureCounter(): int
    {
        return (int)$this->webAuthn->getSignatureCounter();
    }
}
