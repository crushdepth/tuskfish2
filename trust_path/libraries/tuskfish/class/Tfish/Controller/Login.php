<?php

declare(strict_types=1);

namespace Tfish\Controller;

/**
 * \Tfish\Controller\Login class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Controller for logging in.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\ValidateToken Provides CSRF check functionality.
 * @var         object $model Instance of the model required by this route.
 * @var         object $viewModel Instance of the viewModel required by this route.
 * @var         \Tfish\Entity\Preference $preference Instance of the preference class.
 */

class Login
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;

    private object $model;
    private object $viewModel;

    /**
     * Constructor
     *
     * @param   object $model Instance of a model class.
     * @param   object $viewModel Instance of a viewModel class.
     */
    public function __construct(object $model, object $viewModel, \Tfish\Session $session)
    {
        $this->model = $model;
        $this->viewModel = $viewModel;
    }

    /* Actions. */

    /**
     * Display the login form.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function display(): array
    {
        $this->viewModel->displayForm();

        return [];
    }

    /**
     * Process login submission.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function login(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->viewModel->displayForm();
            return [];
        }

        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        if (isset($_POST['email']) && isset($_POST['password'])) {
            $result = $this->viewModel->login($this->trimString($_POST['email']), $_POST['password']);

            // Check if WebAuthn second factor is required
            if (!empty($result['webauthn_required'])) {
                \header('Content-Type: application/json');
                echo \json_encode(['webauthn_required' => true]);
                exit;
            }
        }

        $this->viewModel->displayForm();

        return [];
    }

    /**
     * Generate WebAuthn authentication options (JSON endpoint).
     *
     * Returns challenge and credential IDs for navigator.credentials.get().
     *
     * @return  array Empty array (not used - exits before return).
     */
    public function authenticateOptions(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            \http_response_code(405);
            \header('Content-Type: application/json');
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_METHOD_NOT_ALLOWED]);
            exit;
        }

        // Validate CSRF token - return JSON error for API endpoint
        $token = $this->trimString($_POST['token'] ?? '');
        if (empty($_SESSION['token']) || !\hash_equals($_SESSION['token'], $token)) {
            \http_response_code(403);
            \header('Content-Type: application/json');
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_AUTHENTICATION_FAILED]);
            exit;
        }

        try {
            $options = $this->viewModel->getWebAuthnAuthenticationOptions();

            if ($options === null) {
                \http_response_code(400);
                \header('Content-Type: application/json');
                echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_NO_PENDING_LOGIN]);
                exit;
            }

            \http_response_code(200);
            \header('Content-Type: application/json');
            echo \json_encode($options);
        } catch (\Exception $e) {
            \error_log("WebAuthn authentication options error: " . $e->getMessage());
            \http_response_code(500);
            \header('Content-Type: application/json');
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_PROCESSING_REQUEST]);
        }

        exit;
    }

    /**
     * Verify WebAuthn authentication response (JSON endpoint).
     *
     * Processes assertion from navigator.credentials.get().
     *
     * @return  array Empty array (not used - exits before return).
     */
    public function authenticateVerify(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            \http_response_code(405);
            \header('Content-Type: application/json');
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_METHOD_NOT_ALLOWED]);
            exit;
        }

        // Validate CSRF token - return JSON error for API endpoint
        $token = $this->trimString($_POST['token'] ?? '');
        if (empty($_SESSION['token']) || !\hash_equals($_SESSION['token'], $token)) {
            \http_response_code(403);
            \header('Content-Type: application/json');
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_AUTHENTICATION_FAILED]);
            exit;
        }

        $clientDataJSON = $_POST['clientDataJSON'] ?? '';
        $authenticatorData = $_POST['authenticatorData'] ?? '';
        $signature = $_POST['signature'] ?? '';
        $credentialId = $_POST['credentialId'] ?? '';

        if (empty($clientDataJSON) || empty($authenticatorData) || empty($signature) || empty($credentialId)) {
            \http_response_code(400);
            \header('Content-Type: application/json');
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_MISSING_PARAMETERS]);
            exit;
        }

        try {
            $verified = $this->viewModel->verifyWebAuthnAssertion(
                $clientDataJSON,
                $authenticatorData,
                $signature,
                $credentialId
            );

            if ($verified) {
                $next = $this->viewModel->nextUrl();
                $redirect = $next ?: TFISH_ADMIN_URL;

                \http_response_code(200);
                \header('Content-Type: application/json');
                echo \json_encode(['success' => true, 'redirect' => $redirect]);
            } else {
                \http_response_code(401);
                \header('Content-Type: application/json');
                echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_AUTHENTICATION_FAILED]);
            }
        } catch (\Exception $e) {
            \error_log("WebAuthn authentication verification error: " . $e->getMessage());
            \http_response_code(500);
            \header('Content-Type: application/json');
            echo \json_encode(['error' => TFISH_WEBAUTHN_ERROR_PROCESSING_REQUEST]);
        }

        exit;
    }
}
