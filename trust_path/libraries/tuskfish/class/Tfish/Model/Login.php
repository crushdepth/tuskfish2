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

    private \Tfish\Session $session;

    /**
     * Constructor.
     *
     * @param   \Tfish\Session $session Instance of the Tuskfish session manager class.
     */
    public function __construct(\Tfish\Session $session)
    {
        $this->session = $session;
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
        return $this->session->login($email, $password);
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
        return $this->session->getWebAuthnAuthenticationOptions();
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
        return $this->session->verifyWebAuthnAssertion(
            $clientDataJSON,
            $authenticatorData,
            $signature,
            $credentialId
        );
    }
}
