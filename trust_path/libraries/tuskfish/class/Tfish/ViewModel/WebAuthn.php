<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModel\WebAuthn class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 */

/**
 * ViewModel for WebAuthn/FIDO2 authentication.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\Interface\Viewable interface.
 * @var         object $model Instance of the model required by this route.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 */

class WebAuthn implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private object $model;
    private \Tfish\Entity\Preference $preference;
    private array $credentials = [];
    private string $response = '';
    private string $backUrl = '';

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     */
    public function __construct(object $model, \Tfish\Entity\Preference $preference)
    {
        $this->model = $model;
        $this->preference = $preference;
        $this->pageTitle = TFISH_WEBAUTHN_PAGE_TITLE;
        $this->theme = 'admin';
        $this->template = 'register';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions */

    /**
     * Display the registration and management page.
     */
    public function displayRegister(): void
    {
        // Controller will load credentials via model and pass to this ViewModel
        $this->template = 'register';
    }

    /**
     * Display revocation confirmation/response.
     */
    public function displayRevoke(): void
    {
        $this->template = 'response';
    }

    /** Getters and setters */

    /**
     * Set credentials for display.
     *
     * @param   array $credentials Array of credential records.
     */
    public function setCredentials(array $credentials): void
    {
        $this->credentials = $credentials;
    }

    /**
     * Get user credentials for template.
     *
     * @return  array Array of credentials.
     */
    public function credentials(): array
    {
        return $this->credentials;
    }

    /**
     * Check if user has credentials.
     *
     * @return  bool True if user has credentials.
     */
    public function hasCredentials(): bool
    {
        return !empty($this->credentials);
    }

    /**
     * Set response message.
     *
     * @param   string $response Response message.
     */
    public function setResponse(string $response): void
    {
        $this->response = $this->trimString($response);
    }

    /**
     * Get response message.
     *
     * @return  string Response message.
     */
    public function response(): string
    {
        return $this->response;
    }

    /**
     * Set back URL.
     *
     * @param   string $backUrl URL to return to.
     */
    public function setBackUrl(string $backUrl): void
    {
        $this->backUrl = $this->trimString($backUrl);
    }

    /**
     * Get back URL.
     *
     * @return  string Back URL.
     */
    public function backUrl(): string
    {
        return $this->backUrl;
    }

    /**
     * Get preference entity.
     *
     * @return  \Tfish\Entity\Preference Preference instance.
     */
    public function preference(): \Tfish\Entity\Preference
    {
        return $this->preference;
    }

    /**
     * Generate registration options for WebAuthn credential registration.
     *
     * @param   int $userId User ID.
     * @param   string $userEmail User email.
     * @return  object Registration options.
     */
    public function generateRegistrationOptions(int $userId, string $userEmail): object
    {
        return $this->model->generateRegistrationOptions($userId, $userEmail);
    }

    /**
     * Verify WebAuthn registration response.
     *
     * @param   string $clientDataJSON Client data from browser.
     * @param   string $attestationObject Attestation object from browser.
     * @param   string $credentialName User-provided credential name.
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
        return $this->model->verifyRegistration(
            $clientDataJSON,
            $attestationObject,
            $credentialName,
            $userId
        );
    }

    /**
     * Delete a credential.
     *
     * @param   int $id Credential ID.
     * @param   int $userId User ID (security check).
     * @return  bool True on success, false on failure.
     */
    public function deleteCredential(int $id, int $userId): bool
    {
        return $this->model->delete($id, $userId);
    }

    /**
     * Get credentials for a user.
     *
     * @param   int $userId User ID.
     * @return  array Array of credential records.
     */
    public function getCredentials(int $userId): array
    {
        return $this->model->getByUserId($userId);
    }
}
