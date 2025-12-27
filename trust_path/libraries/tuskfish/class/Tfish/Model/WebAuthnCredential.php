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

        $credentialId = $this->trimString($credentialId);
        $publicKey = $this->trimString($publicKey);
        $transports = $this->trimString($transports);
        $aaguid = $this->trimString($aaguid);
        $credentialName = $this->trimString($credentialName);

        if (!$this->validateCredentialId($credentialId) || !$this->validatePublicKey($publicKey)) {
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

        $credentials = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $credentials[] = $row;
        }

        return $credentials;
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
        if ($newSignCount <= $currentSignCount && $currentSignCount !== 0) {
            \error_log("SECURITY ALERT: Sign count mismatch for credential {$credentialId}. Expected > {$currentSignCount}, got {$newSignCount}");
            return false;
        }

        $sql = "UPDATE `webauthn_credentials`
                SET `signCount` = :signCount, `lastUsed` = :lastUsed
                WHERE `credentialId` = :credentialId";

        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':signCount', $newSignCount, \PDO::PARAM_INT);
        $statement->bindValue(':lastUsed', \time(), \PDO::PARAM_INT);
        $statement->bindValue(':credentialId', $credentialId, \PDO::PARAM_STR);

        return $this->database->executeTransaction($statement);
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

        // Prevent deleting the last credential.
        $userCredentials = $this->getByUserId($userId);

        if (\count($userCredentials) <= 1) {
            return false;
        }

        // Verify credential belongs to user.
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

        // Base64 characters: A-Z, a-z, 0-9, +, /, =
        return (bool)\preg_match('/^[A-Za-z0-9+\/=]+$/', $credentialId);
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

        // Base64 characters: A-Z, a-z, 0-9, +, /, =
        return (bool)\preg_match('/^[A-Za-z0-9+\/=]+$/', $publicKey);
    }

    /**
     * Generate WebAuthn registration options.
     *
     * @param   int $userId User ID.
     * @param   string $userEmail User email.
     * @return  object Registration options for client.
     */
    public function generateRegistrationOptions(int $userId, string $userEmail): object
    {
        // Get existing credentials to exclude
        $existingCredentials = $this->getByUserId($userId);
        $excludeIds = \array_column($existingCredentials, 'credentialId');

        // Decode base64 credential IDs back to binary for WebAuthn library
        $excludeIdsBinary = \array_map(function($id) {
            return \base64_decode($id);
        }, $excludeIds);

        // Create WebAuthn service
        $service = new \Tfish\WebAuthnService(
            $this->preference->siteName(),
            $_SERVER['SERVER_NAME']
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
        $challengeModel = new WebAuthnChallenge();
        $challenge = $challengeModel->getRegistration();

        if (!$challenge) {
            throw new \RuntimeException("No challenge found in session");
        }

        $service = new \Tfish\WebAuthnService(
            $this->preference->siteName(),
            $_SERVER['SERVER_NAME']
        );

        $data = $service->verifyRegistration($clientDataJSON, $attestationObject, $challenge);

        // Store credential
        // AAGUID may be null in 'none' attestation mode or for some authenticators
        $aaguid = isset($data->aaguid) && $data->aaguid !== null ? \bin2hex($data->aaguid) : '';

        $result = $this->store(
            $userId,
            \base64_encode($data->credentialId),
            \base64_encode($data->credentialPublicKey),
            (int)$data->signatureCounter,
            \json_encode($data->transports ?? []),
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
