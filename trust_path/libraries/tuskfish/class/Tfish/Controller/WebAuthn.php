<?php

declare(strict_types=1);

namespace Tfish\Controller;

/**
 * \Tfish\Controller\WebAuthn class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 */

/**
 * Controller for WebAuthn/FIDO2 authentication.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\ValidateToken   Provides CSRF check functionality.
 * @var         object $model Instance of the model required by this route.
 * @var         object $viewModel Instance of the viewModel required by this route.
 * @var         \Tfish\Session $session Instance of the session management class.
 * @var         \Tfish\Database $database Instance of the database class.
 * @var         \Tfish\Entity\Preference $preference Instance of the preference class.
 */

class WebAuthn
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;

    private object $model;
    private object $viewModel;
    private \Tfish\Session $session;

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   object $viewModel Instance of a viewModel class.
     * @param   \Tfish\Session $session Instance of the session management class.
     */
    public function __construct(object $model, object $viewModel, \Tfish\Session $session)
    {
        $this->model = $model;
        $this->viewModel = $viewModel;
        $this->session = $session;
    }

    /* Actions. */

    /**
     * Display the registration and management page.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function display(): array
    {
        // Load user's existing credentials
        if ($this->session->isLoggedIn()) {
            $userId = $this->session->userId();

            if ($userId) {
                $credentials = $this->viewModel->getCredentials($userId);
                $this->viewModel->setCredentials($credentials);
            }
        }

        $this->viewModel->displayRegister();

        return [];
    }

    /**
     * Generate registration options (JSON endpoint).
     *
     * Returns challenge and options for navigator.credentials.create().
     *
     * @return  array Empty array (not used - exits before return).
     */
    public function registerOptions(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            \http_response_code(405);
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_METHOD_NOT_ALLOWED]);
            exit;
        }

        $token = $_POST['token'] ?? '';
        $this->validateToken($token);

        if (!$this->session->isLoggedIn()) {
            \http_response_code(401);
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_NOT_AUTHENTICATED]);
            exit;
        }

        try {
            $userId = $this->session->userId();
            $userEmail = $this->session->userEmail();

            if (!$userId || !$userEmail) {
                \http_response_code(401);
                echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_SESSION_UNAVAILABLE]);
                exit;
            }

            $options = $this->viewModel->generateRegistrationOptions($userId, $userEmail);

            \http_response_code(200);
            \header('Content-Type: application/json');
            echo \json_encode($options);
        } catch (\Exception $e) {
            \error_log("WebAuthn registration options error: " . $e->getMessage());
            \http_response_code(500);
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_PROCESSING_REQUEST]);
        }

        exit;
    }

    /**
     * Verify registration response (JSON endpoint).
     *
     * Processes attestation from navigator.credentials.create().
     *
     * @return  array Empty array (not used - exits before return).
     */
    public function registerVerify(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            \http_response_code(405);
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_METHOD_NOT_ALLOWED]);
            exit;
        }

        $token = $_POST['token'] ?? '';
        $this->validateToken($token);

        if (!$this->session->isLoggedIn()) {
            \http_response_code(401);
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_NOT_AUTHENTICATED]);
            exit;
        }

        $clientDataJSON = $_POST['clientDataJSON'] ?? '';
        $attestationObject = $_POST['attestationObject'] ?? '';
        $credentialName = $this->trimString($_POST['credentialName'] ?? '');

        if (empty($clientDataJSON) || empty($attestationObject)) {
            \http_response_code(400);
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_MISSING_PARAMETERS]);
            exit;
        }

        try {
            $userId = $this->session->userId();

            if (!$userId) {
                \http_response_code(401);
                echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_SESSION_UNAVAILABLE]);
                exit;
            }

            $result = $this->viewModel->verifyRegistration(
                $clientDataJSON,
                $attestationObject,
                $credentialName,
                $userId
            );

            if ($result) {
                \http_response_code(200);
                echo \json_encode(['success' => true]);
            } else {
                \http_response_code(400);
                echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_VERIFICATION_FAILED]);
            }
        } catch (\Exception $e) {
            \error_log("WebAuthn registration verification error: " . $e->getMessage());
            \http_response_code(500);
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_PROCESSING_REQUEST]);
        }

        exit;
    }

    /**
     * Revoke a credential.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function revoke(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->viewModel->displayRegister();
            return [];
        }

        $token = $_POST['token'] ?? '';
        $this->validateToken($token);

        if (!$this->session->isLoggedIn()) {
            \header('Location: ' . TFISH_URL . 'login/', true, 303);
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $userId = $this->session->userId();

        if (!$userId) {
            \header('Location: ' . TFISH_URL . 'login/', true, 303);
            exit;
        }

        $result = $this->viewModel->deleteCredential($id, $userId);

        if ($result) {
            $this->viewModel->setResponse(TFISH_WEBAUTHN_CREDENTIAL_REVOKED);
        } else {
            $this->viewModel->setResponse(TFISH_WEBAUTHN_CREDENTIAL_REVOKE_FAILED);
        }

        $this->viewModel->setBackUrl(TFISH_URL . 'register/');
        $this->viewModel->displayRevoke();

        return [];
    }
}
