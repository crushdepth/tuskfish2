<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModel\Login class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for logging in.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\Interface\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         string $theme Name of the theme used to display this page.
 * @var         array $metadata Overrides of site metadata properties, to customise it for this page.
 */

class Login implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private object $model;
    private \Tfish\Entity\Preference $preference;

    /**
     * Constructor
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     */
    public function __construct(object $model, \Tfish\Entity\Preference $preference)
    {
        $this->pageTitle = TFISH_LOGIN;
        $this->model = $model;
        $this->preference = $preference;
        $this->template = 'login';
        $this->theme = $preference->defaultTheme();
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions */

    /**
     * Display the login form.
     */
    public function displayForm(): void {}

    /** Utilities */

    /**
     * Return title for redirect page.
     *
     * @return string|null Title of page.
     */
    public function redirectTitle(): ?string
    {
        return $this->model->redirectTitle();
    }

    /**
     * Set context message for redirect page.
     *
     * @return string|null Context message.
     */
    public function redirectMessage(): ?string
    {
        return $this->model->redirectMessage();
    }

    /**
     * Process login with WebAuthn detection.
     *
     * @param   string $email User email.
     * @param   string $password User password.
     * @return  array Empty array or ['webauthn_required' => true].
     */
    public function login(string $email, string $password): array
    {
        return $this->model->login($email, $password);
    }

    /**
     * Return the next URL (redirect).
     *
     * @return string|null
     */
    public function nextUrl(): ?string
    {
        return $this->model->nextUrl();
    }

    /**
     * Get WebAuthn authentication options for pending login.
     *
     * @return  object|null Authentication options or null on failure.
     */
    public function getWebAuthnAuthenticationOptions(): ?object
    {
        return $this->model->getWebAuthnAuthenticationOptions();
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
        return $this->model->verifyWebAuthnAssertion(
            $clientDataJSON,
            $authenticatorData,
            $signature,
            $credentialId
        );
    }
}
