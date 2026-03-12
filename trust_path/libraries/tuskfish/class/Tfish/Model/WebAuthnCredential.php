<?php

declare(strict_types=1);

namespace Tfish\Model;

/**
 * \Tfish\Model\WebAuthnCredential class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 */

/**
 * Model for WebAuthn credential operations.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 */

class WebAuthnCredential
{
    use \Tfish\Traits\ValidateString;

    private \Tfish\Database $database;
    private \Tfish\Entity\Preference $preference;

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     */
    public function __construct(\Tfish\Database $database, \Tfish\Entity\Preference $preference)
    {
        $this->database = $database;
        $this->preference = $preference;
    }

    /**
     * Store a new WebAuthn credential.
     *
     * @param   int $userId User ID.
     * @param   string $credentialId Base64-encoded credential ID.
     * @param   string $publicKey Base64-encoded public key.
     * @param   int $signCount Initial signature counter.
     * @param   string $transports JSON array of transport methods.
     * @param   string $aaguid Authenticator AAGUID.
     * @param   string $credentialName User-friendly credential name.
     * @return  bool True on success, false on failure.
     */
    public function store(
        int $userId,
        string $credentialId,
        string $publicKey,
        int $signCount,
        string $transports,
        string $aaguid,
        string $credentialName = ''
    ): bool
    {
        if ($userId < 1) {
            return false;
        }

        // Validate signature counter is non-negative
        if ($signCount < 0) {
            return false;
        }

        $credentialId = $this->trimString($credentialId);
        $publicKey = $this->trimString($publicKey);
        $transports = $this->trimString($transports);
        $aaguid = $this->trimString($aaguid);
        $credentialName = $this->trimString($credentialName);

        // Validate credential name length (prevent database bloat and potential XSS)
        if (\mb_strlen($credentialName, 'UTF-8') > 255) {
            $credentialName = \mb_substr($credentialName, 0, 255, 'UTF-8');
        }

        if (!$this->validateCredentialId($credentialId) || !$this->validatePublicKey($publicKey)) {
            return false;
        }

        // Validate AAGUID format
        if (!$this->validateAaguid($aaguid)) {
            return false;
        }

        // Prevent duplicate credential registration
        $existing = $this->getByCredentialId($credentialId);
        if ($existing) {
            \error_log("SECURITY WARNING: Attempt to register duplicate credential ID");
            return false;
        }

        $sql = "INSERT INTO `webauthn_credentials`
                (`userId`, `credentialId`, `publicKey`, `signCount`, `transports`, `aaguid`, `credentialName`, `createdAt`)
                VALUES (:userId, :credentialId, :publicKey, :signCount, :transports, :aaguid, :credentialName, :createdAt)";

        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->bindValue(':credentialId', $credentialId, \PDO::PARAM_STR);
        $statement->bindValue(':publicKey', $publicKey, \PDO::PARAM_STR);
        $statement->bindValue(':signCount', $signCount, \PDO::PARAM_INT);
        $statement->bindValue(':transports', $transports, \PDO::PARAM_STR);
        $statement->bindValue(':aaguid', $aaguid, \PDO::PARAM_STR);
        $statement->bindValue(':credentialName', $credentialName, \PDO::PARAM_STR);
        $statement->bindValue(':createdAt', \time(), \PDO::PARAM_INT);

        return $this->database->executeTransaction($statement);
    }

    /**
     * Get all credentials for a user.
     *
     * @param   int $userId User ID.
     * @return  array Array of credential rows.
     */
    public function getByUserId(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        $sql = "SELECT * FROM `webauthn_credentials` WHERE `userId` = :userId ORDER BY `createdAt` DESC";
        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->execute();
        
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a single credential by credential ID.
     *
     * @param   string $credentialId Base64-encoded credential ID.
     * @return  array|null Credential row or null if not found.
     */
    public function getByCredentialId(string $credentialId): ?array
    {
        $credentialId = $this->trimString($credentialId);

        if (!$this->validateCredentialId($credentialId)) {
            return null;
        }

        $sql = "SELECT * FROM `webauthn_credentials` WHERE `credentialId` = :credentialId LIMIT 1";
        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':credentialId', $credentialId, \PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Update signature counter for a credential.
     *
     * @param   string $credentialId Base64-encoded credential ID.
     * @param   int $newSignCount New signature counter value.
     * @return  bool True on success, false on failure.
     */
    public function updateSignCount(string $credentialId, int $newSignCount): bool
    {
        $credentialId = $this->trimString($credentialId);

        if (!$this->validateCredentialId($credentialId) || $newSignCount < 0) {
            return false;
        }

        $credential = $this->getByCredentialId($credentialId);

        if (!$credential) {
            return false;
        }

        $currentSignCount = (int)$credential['signCount'];

        // Sign count must increment (clone detection).
        // Use atomic UPDATE with WHERE clause to prevent race conditions
        if ($newSignCount <= $currentSignCount && $currentSignCount !== 0) {
            \error_log("SECURITY ALERT: Sign count mismatch for credential {$credentialId}. Expected > {$currentSignCount}, got {$newSignCount}");
            return false;
        }

        // Atomic update: only update if signCount hasn't changed (prevents TOCTOU race)
        $sql = "UPDATE `webauthn_credentials`
                SET `signCount` = :newSignCount, `lastUsed` = :lastUsed
                WHERE `credentialId` = :credentialId AND `signCount` = :currentSignCount";

        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':newSignCount', $newSignCount, \PDO::PARAM_INT);
        $statement->bindValue(':lastUsed', \time(), \PDO::PARAM_INT);
        $statement->bindValue(':credentialId', $credentialId, \PDO::PARAM_STR);
        $statement->bindValue(':currentSignCount', $currentSignCount, \PDO::PARAM_INT);

        $result = $this->database->executeTransaction($statement);

        // If update affected 0 rows, race condition occurred or credential was modified
        if (!$result) {
            \error_log("SECURITY ALERT: Sign count update race condition detected for credential {$credentialId}");
            return false;
        }

        return true;
    }

    /**
     * Delete a credential.
     *
     * @param   int $id Credential ID.
     * @param   int $userId User ID (security check).
     * @return  bool True on success, false on failure.
     */
    public function delete(int $id, int $userId): bool
    {
        if ($id < 1 || $userId < 1) {
            return false;
        }

        // Verify credential belongs to user and delete.
        $sql = "DELETE FROM `webauthn_credentials` WHERE `id` = :id AND `userId` = :userId";
        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);

        return $this->database->executeTransaction($statement);
    }

    /**
     * Count credentials for a user.
     *
     * @param   int $userId User ID.
     * @return  int Number of credentials.
     */
    public function countByUserId(int $userId): int
    {
        if ($userId < 1) {
            return 0;
        }

        $sql = "SELECT COUNT(*) as count FROM `webauthn_credentials` WHERE `userId` = :userId";
        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return (int)($row['count'] ?? 0);
    }

    /**
     * Validate credential ID format.
     *
     * @param   string $credentialId Base64-encoded credential ID.
     * @return  bool True if valid, false otherwise.
     */
    private function validateCredentialId(string $credentialId): bool
    {
        if (empty($credentialId)) {
            return false;
        }

        // Maximum length check (DoS protection) - 1KB base64 = ~768 bytes raw
        if (\strlen($credentialId) > 1024) {
            return false;
        }

        // Validate base64 characters: A-Z, a-z, 0-9, +, /, =
        if (!\preg_match('/^[A-Za-z0-9+\/=]+$/', $credentialId)) {
            return false;
        }

        // Verify it's actually valid base64 by attempting strict decode
        return \base64_decode($credentialId, true) !== false;
    }

    /**
     * Validate public key format.
     *
     * @param   string $publicKey Base64-encoded public key.
     * @return  bool True if valid, false otherwise.
     */
    private function validatePublicKey(string $publicKey): bool
    {
        if (empty($publicKey)) {
            return false;
        }

        // Maximum length check (DoS protection) - 8KB base64 = ~6KB raw
        if (\strlen($publicKey) > 8192) {
            return false;
        }

        // Validate base64 characters: A-Z, a-z, 0-9, +, /, =
        if (!\preg_match('/^[A-Za-z0-9+\/=]+$/', $publicKey)) {
            return false;
        }

        // Verify it's actually valid base64 by attempting strict decode
        return \base64_decode($publicKey, true) !== false;
    }

    /**
     * Validate transports array.
     *
     * @param   array $transports Array of transport strings.
     * @return  bool True if valid, false otherwise.
     */
    private function validateTransports(array $transports): bool
    {
        // Prevent oversized arrays (DoS protection)
        if (\count($transports) > 10) {
            return false;
        }

        // Valid WebAuthn transport values
        $validTransports = ['usb', 'nfc', 'ble', 'hybrid', 'internal'];

        foreach ($transports as $transport) {
            if (!\is_string($transport) || !\in_array($transport, $validTransports, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate AAGUID format.
     *
     * @param   string $aaguid AAGUID hex string.
     * @return  bool True if valid, false otherwise.
     */
    private function validateAaguid(string $aaguid): bool
    {
        // Empty is valid (for 'none' attestation)
        if ($aaguid === '') {
            return true;
        }

        // Must be exactly 32 hex characters (16 bytes)
        return \preg_match('/^[0-9a-f]{32}$/i', $aaguid) === 1;
    }

    /**
     * Generate WebAuthn registration options.
     *
     * IMPORTANT: Calling code MUST validate that $userId matches the authenticated session user.
     * This method provides defensive validation but relies on upstream authorization.
     *
     * @param   int $userId User ID.
     * @param   string $userEmail User email.
     * @return  object Registration options for client.
     */
    public function generateRegistrationOptions(int $userId, string $userEmail): object
    {
        // Defensive validation (upstream MUST also validate authorization)
        if ($userId < 1) {
            throw new \RuntimeException('Invalid user ID');
        }

        // Get existing credentials to exclude
        $existingCredentials = $this->getByUserId($userId);

        // Prevent credential flooding (DoS protection)
        if (\count($existingCredentials) >= 10) {
            throw new \RuntimeException('Maximum credential limit reached (10 per user)');
        }

        $excludeIds = \array_column($existingCredentials, 'credentialId');

        // Decode base64 credential IDs back to binary for WebAuthn library
        // Use strict mode and filter out any invalid entries
        $excludeIdsBinary = \array_filter(\array_map(function($id) {
            $decoded = \base64_decode($id, true);
            if ($decoded === false) {
                \error_log("SECURITY WARNING: Invalid base64 credential ID in database: " . \substr($id, 0, 20));
            }
            return $decoded;
        }, $excludeIds), function($value) {
            return $value !== false;
        });

        // Create WebAuthn service
        // Use configured domain from TFISH_URL (not user-controlled headers)
        $rpId = \parse_url(TFISH_URL, PHP_URL_HOST);

        if (!$rpId) {
            throw new \RuntimeException(TFISH_WEBAUTHN_ERROR_INVALID_TFISH_URL);
        }

        $service = new \Tfish\WebAuthnService(
            $this->preference->siteName(),
            $rpId
        );

        // Generate options
        $options = $service->getRegistrationOptions($userId, $userEmail, $excludeIdsBinary);

        // Store challenge in session
        $challengeModel = new WebAuthnChallenge();
        $challengeModel->storeRegistration($service->getChallenge());

        return $options;
    }

    /**
     * Verify WebAuthn registration response.
     *
     * IMPORTANT: Calling code MUST validate that $userId matches the authenticated session user.
     * This method provides defensive validation but relies on upstream authorization.
     *
     * @param   string $clientDataJSON Client data from browser.
     * @param   string $attestationObject Attestation object from browser.
     * @param   string $credentialName User-provided name for credential.
     * @param   int $userId User ID.
     * @return  bool True on success, false on failure.
     */
    public function verifyRegistration(
        string $clientDataJSON,
        string $attestationObject,
        string $credentialName,
        int $userId
    ): bool
    {
        // Defensive validation (upstream MUST also validate authorization)
        if ($userId < 1) {
            throw new \RuntimeException('Invalid user ID');
        }

        $challengeModel = new WebAuthnChallenge();
        $challenge = $challengeModel->getRegistration();

        if (!$challenge) {
            throw new \RuntimeException("No challenge found in session");
        }

        // Use configured domain from TFISH_URL (not user-controlled headers)
        $rpId = \parse_url(TFISH_URL, PHP_URL_HOST);

        if (!$rpId) {
            throw new \RuntimeException(TFISH_WEBAUTHN_ERROR_INVALID_TFISH_URL);
        }

        $service = new \Tfish\WebAuthnService(
            $this->preference->siteName(),
            $rpId
        );

        $data = $service->verifyRegistration($clientDataJSON, $attestationObject, $challenge);

        // Validate library response data types (defense-in-depth)
        if (!isset($data->credentialId) || !\is_string($data->credentialId)) {
            throw new \RuntimeException(TFISH_WEBAUTHN_ERROR_INVALID_CREDENTIAL_ID);
        }

        if (!isset($data->credentialPublicKey) || !\is_string($data->credentialPublicKey)) {
            throw new \RuntimeException(TFISH_WEBAUTHN_ERROR_INVALID_PUBLIC_KEY);
        }

        // Signature counter can be int, numeric string, null, or undefined (all treated as 0)
        if (isset($data->signatureCounter) && !\is_int($data->signatureCounter) && !\is_numeric($data->signatureCounter)) {
            throw new \RuntimeException(TFISH_WEBAUTHN_ERROR_INVALID_SIGNATURE_COUNTER);
        }

        // Validate transports is array or null
        if (isset($data->transports) && $data->transports !== null && !\is_array($data->transports)) {
            throw new \RuntimeException(TFISH_WEBAUTHN_ERROR_INVALID_TRANSPORTS);
        }

        // Validate transports array contents
        if (isset($data->transports) && $data->transports !== null && !$this->validateTransports($data->transports)) {
            throw new \RuntimeException(TFISH_WEBAUTHN_ERROR_INVALID_TRANSPORTS);
        }

        // Validate AAGUID is string or null
        if (isset($data->AAGUID) && $data->AAGUID !== null && !\is_string($data->AAGUID)) {
            throw new \RuntimeException(TFISH_WEBAUTHN_ERROR_INVALID_AAGUID);
        }

        // Store credential
        // AAGUID may be null in 'none' attestation mode or for some authenticators
        $aaguid = isset($data->AAGUID) && $data->AAGUID !== null ? \bin2hex($data->AAGUID) : '';

        // Validate AAGUID format
        if (!$this->validateAaguid($aaguid)) {
            throw new \RuntimeException(TFISH_WEBAUTHN_ERROR_INVALID_AAGUID);
        }

        // Encode transports to JSON with error checking
        $transportsJson = \json_encode($data->transports ?? []);
        if ($transportsJson === false) {
            throw new \RuntimeException(TFISH_WEBAUTHN_ERROR_INVALID_TRANSPORTS);
        }

        $result = $this->store(
            $userId,
            \base64_encode($data->credentialId),
            \base64_encode($data->credentialPublicKey),
            (int)$data->signatureCounter,
            $transportsJson,
            $aaguid,
            $credentialName
        );

        if (!$result) {
            throw new \RuntimeException("Database storage failed");
        }

        $challengeModel->clear();
        return true;
    }
}
