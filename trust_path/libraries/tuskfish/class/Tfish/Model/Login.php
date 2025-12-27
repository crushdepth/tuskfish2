<?php

declare(strict_types=1);

namespace Tfish\Model;

/**
 * \Tfish\Model\Login class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Model for logging in.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Session $session Instance of the session management class.
 */

class Login
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\EmailCheck;

    private \Tfish\Session $session;
    private \Tfish\Database $database;
    private \Tfish\Entity\Preference $preference;

    /**
     * Constructor.
     *
     * @param   \Tfish\Session $session Instance of the Tuskfish session manager class.
     * @param   \Tfish\Database $database Instance of the database class.
     * @param   \Tfish\Entity\Preference $preference Instance of the preference class.
     */
    public function __construct(\Tfish\Session $session, \Tfish\Database $database, \Tfish\Entity\Preference $preference)
    {
        $this->session = $session;
        $this->database = $database;
        $this->preference = $preference;
    }

    /**
     * Process admin login with WebAuthn detection.
     *
     * Checks if user requires WebAuthn second factor after password validation.
     *
     * @param   string $email Email address.
     * @param   string $password Password.
     * @return  array Empty array for normal login, or associative array with 'webauthn_required' => true
     */
    public function login(string $email, string $password): array
    {
        $email = $this->trimString($email);

        if (!$this->isEmail($email)) {
            $this->session->logout(TFISH_URL . "login/");
            return [];
        }

        // Get user by email
        $user = $this->getUserByEmail($email);

        if (!$user) {
            $this->session->logout(TFISH_URL . "login/");
            return [];
        }

        // If user has previous failed login attempts, sleep to frustrate brute force attacks
        if ((int)$user['loginErrors'] > 0) {
            \sleep((int)$user['loginErrors']);
        }

        // If user is suspended, do not proceed
        if ((int)$user['onlineStatus'] !== 1) {
            $this->session->logout(TFISH_URL . "login/");
            return [];
        }

        // Check password
        if (!\password_verify($password, $user['passwordHash'])) {
            // Increment failed login counter
            if ((int)$user['loginErrors'] < 15) {
                $this->database->updateCounter((int)$user['id'], 'user', 'loginErrors');
            }
            $this->session->logout(TFISH_URL . "login/");
            return [];
        }

        // Password verified - reset login error counter
        $this->database->update('user', (int)$user['id'], ['loginErrors' => 0]);

        // Password verified - check if second factor authentication is required
        $webauthnLogin = new WebAuthnLogin($this->database);
        $secondFactorType = $webauthnLogin->requiresSecondFactor((int)$user['id']);

        if ($secondFactorType === 'webauthn') {
            // Store pending user ID for WebAuthn verification
            $challengeModel = new WebAuthnChallenge();
            $challengeModel->storePendingUserId((int)$user['id']);
            return ['webauthn_required' => true];
        }

        if ($secondFactorType === 'otp') {
            // User requires Yubikey OTP - fail closed (deny access)
            // OTP users should use the alternative /login/ route configured for Yubikey
            $this->session->logout(TFISH_URL . "login/");
            return [];
        }

        // No second factor required - proceed with normal login
        $this->session->login($email, $password);
        return [];
    }

    /**
     * Get user by email.
     *
     * @param   string $email Email address.
     * @return  array|null User row or null if not found.
     */
    private function getUserByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM `user` WHERE `adminEmail` = :email LIMIT 1";
        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':email', $email, \PDO::PARAM_STR);
        $statement->execute();

        $user = $statement->fetch(\PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /**
     * Logout the user and redirect to the login form.
     */
    public function logout(): void
    {
        $this->session->logout(TFISH_URL);
    }

    /** Utilities */

    /**
     * Set title for redirect page.
     *
     * @return string|null Title of page.
     */
    public function redirectTitle(): ?string
    {
        return $this->session->redirectTitle();
    }

    /**
     * Set context message for redirect page.
     *
     * @return string|null Context message.
     */
    public function redirectMessage(): ?string
    {
        return $this->session->redirectMessage();
    }

    /**
     * Get WebAuthn authentication options for pending login.
     *
     * @return  object|null Authentication options or null on failure.
     */
    public function getWebAuthnAuthenticationOptions(): ?object
    {
        $challengeModel = new \Tfish\Model\WebAuthnChallenge();
        $userId = $challengeModel->getPendingUserId();

        if (!$userId) {
            return null;
        }

        // Query credentials directly
        $sql = "SELECT `credentialId` FROM `webauthn_credentials` WHERE `userId` = :userId";
        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->execute();
        $credentials = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($credentials)) {
            return null;
        }

        $credentialIds = \array_column($credentials, 'credentialId');

        $service = new \Tfish\WebAuthnService($this->preference->siteName(), $_SERVER['SERVER_NAME']);
        $options = $service->getAuthenticationOptions($credentialIds);

        // Store authentication challenge
        $challengeModel->storeAuthentication($service->getChallenge());
        $challengeModel->storePendingUserId($userId);

        return $options;
    }

    /**
     * Verify WebAuthn authentication assertion.
     *
     * @param   string $clientDataJSON Client data from authenticator.
     * @param   string $authenticatorData Authenticator data.
     * @param   string $signature Signature from authenticator.
     * @param   string $credentialId Credential ID used.
     * @return  bool True on successful authentication.
     */
    public function verifyWebAuthnAssertion(
        string $clientDataJSON,
        string $authenticatorData,
        string $signature,
        string $credentialId
    ): bool
    {
        $challengeModel = new \Tfish\Model\WebAuthnChallenge();
        $challenge = $challengeModel->getAuthentication();
        $userId = $challengeModel->getPendingUserId();

        if (!$challenge || !$userId) {
            return false;
        }

        // Get credential from database
        $sql = "SELECT * FROM `webauthn_credentials` WHERE `credentialId` = :credentialId LIMIT 1";
        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':credentialId', $credentialId, \PDO::PARAM_STR);
        $statement->execute();
        $credential = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!$credential || (int)$credential['userId'] !== $userId) {
            return false;
        }

        $service = new \Tfish\WebAuthnService($this->preference->siteName(), $_SERVER['SERVER_NAME']);

        $verified = $service->verifyAuthentication(
            $clientDataJSON,
            $authenticatorData,
            $signature,
            $credential['publicKey'],
            $challenge,
            (int)$credential['signCount']
        );

        if ($verified) {
            // Update signature counter
            $newSignCount = $service->getSignatureCounter();
            $sql = "UPDATE `webauthn_credentials` SET `signCount` = :signCount WHERE `credentialId` = :credentialId";
            $statement = $this->database->preparedStatement($sql);
            $statement->bindValue(':signCount', $newSignCount, \PDO::PARAM_INT);
            $statement->bindValue(':credentialId', $credentialId, \PDO::PARAM_STR);
            $updated = $this->database->executeTransaction($statement);

            if (!$updated) {
                return false;
            }

            // Complete login
            if ($this->session->loginWithWebAuthn($userId)) {
                $challengeModel->clear();
                return true;
            }
        }

        return false;
    }
}
