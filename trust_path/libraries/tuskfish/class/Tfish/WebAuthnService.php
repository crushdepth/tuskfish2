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
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\EmailCheck;

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
        // Validate RP name (displayed to users during authentication)
        if (empty($rpName) || \strlen($rpName) > 255) {
            throw new \RuntimeException('Invalid RP name');
        }

        $this->rpName = $rpName;
        $this->rpId = $rpId;

        // Validate RP ID against configured domain (not user-controlled headers)
        // CRITICAL: Never trust $_SERVER['SERVER_NAME'] or HTTP_HOST for security decisions
        $configuredDomain = \parse_url(TFISH_URL, PHP_URL_HOST);
        $configuredScheme = \parse_url(TFISH_URL, PHP_URL_SCHEME);

        // Validate parse_url succeeded (prevents bypass if TFISH_URL is malformed)
        if (!$configuredDomain || !$configuredScheme) {
            throw new \RuntimeException('Invalid TFISH_URL configuration');
        }

        if ($rpId !== $configuredDomain) {
            throw new \RuntimeException('RP ID must match configured domain');
        }

        // Require HTTPS except for localhost development
        // Use configured URL scheme, not user-controlled headers
        $isLocalhost = $configuredDomain === 'localhost' || \str_ends_with($configuredDomain, '.localhost');

        if (!$isLocalhost && $configuredScheme !== 'https') {
            throw new \RuntimeException('WebAuthn requires HTTPS in production');
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
        // Validate user email to prevent injection attacks
        $userEmail = $this->trimString($userEmail);

        if (!$this->isEmail($userEmail) || \mb_strlen($userEmail, 'UTF-8') > 255) {
            throw new \RuntimeException('Invalid email format');
        }

        // Validate userId is positive
        if ($userId < 1) {
            throw new \RuntimeException('Invalid user ID');
        }

        // Validate exclude credentials array size (DoS protection)
        if (\count($excludeCredentialIds) > 100) {
            throw new \RuntimeException('Too many credentials to exclude');
        }

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

        // Override attestation to 'none' to avoid browser privacy dialogue
        // We don't validate attestation anyway (failIfRootMismatch: false)
        // All cryptographic security remains intact without attestation
        $options->publicKey->attestation = 'none';

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
        // Validate input lengths to prevent DoS (1MB limit)
        if (\strlen($clientDataJSON) > 1048576 || \strlen($attestationObject) > 1048576) {
            throw new \RuntimeException('Input data exceeds maximum size');
        }

        if (\strlen($challenge) > 1024) {
            throw new \RuntimeException('Challenge exceeds maximum size');
        }

        try {
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
        } catch (\Exception $e) {
            // Log detailed error internally, throw generic error to client
            \error_log('WebAuthn registration verification error: ' . $e->getMessage());
            throw new \RuntimeException('Invalid credential data');
        }
    }

    /**
     * Get authentication options for client.
     *
     * @param   array $credentialIds Array of credential IDs for this user.
     * @return  object Authentication options.
     */
    public function getAuthenticationOptions(array $credentialIds): object
    {
        // Validate credential IDs array size (DoS protection)
        if (\count($credentialIds) > 100) {
            throw new \RuntimeException('Too many credentials');
        }

        // Validate array contains only binary strings
        foreach ($credentialIds as $credId) {
            if (!\is_string($credId) || \strlen($credId) > 1024) {
                throw new \RuntimeException('Invalid credential ID format');
            }
        }

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
        // Validate input lengths to prevent DoS (1MB limit for browser data)
        if (\strlen($clientDataJSON) > 1048576 ||
            \strlen($authenticatorData) > 1048576 ||
            \strlen($signature) > 1048576) {
            throw new \RuntimeException('Input data exceeds maximum size');
        }

        if (\strlen($challenge) > 1024 || \strlen($credentialPublicKey) > 8192) {
            throw new \RuntimeException('Challenge or key exceeds maximum size');
        }

        // Decode base64-encoded data from browser with strict validation
        $clientDataDecoded = \base64_decode($clientDataJSON, true);
        $authenticatorDataDecoded = \base64_decode($authenticatorData, true);
        $signatureDecoded = \base64_decode($signature, true);

        if ($clientDataDecoded === false || $authenticatorDataDecoded === false || $signatureDecoded === false) {
            throw new \RuntimeException('Invalid base64 encoding in authentication data');
        }

        // Decode public key from database (stored as base64-encoded PEM string)
        // Library expects PEM string, not binary
        $publicKeyPem = \base64_decode($credentialPublicKey, true);

        if ($publicKeyPem === false) {
            throw new \RuntimeException('Invalid base64 encoding in credential public key');
        }

        try {
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
        } catch (\Exception $e) {
            // Log detailed error internally, throw generic error to client
            \error_log('WebAuthn authentication verification error: ' . $e->getMessage());
            throw new \RuntimeException('Authentication verification failed');
        }
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
