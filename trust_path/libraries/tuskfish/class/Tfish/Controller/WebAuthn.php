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
            echo \json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $token = $_POST['token'] ?? '';
        $this->validateToken($token);

        if (!$this->session->isLoggedIn()) {
            \http_response_code(401);
            echo \json_encode(['error' => 'Not authenticated']);
            exit;
        }

        try {
            $userId = $this->session->userId();
            $userEmail = $this->session->userEmail();

            if (!$userId || !$userEmail) {
                \http_response_code(401);
                echo \json_encode(['error' => 'Session data unavailable']);
                exit;
            }

            $options = $this->viewModel->generateRegistrationOptions($userId, $userEmail);

            \http_response_code(200);
            \header('Content-Type: application/json');
            echo \json_encode($options);
        } catch (\Exception $e) {
            \http_response_code(500);
            echo \json_encode(['error' => $e->getMessage()]);
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
            echo \json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $token = $_POST['token'] ?? '';
        $this->validateToken($token);

        if (!$this->session->isLoggedIn()) {
            \http_response_code(401);
            echo \json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $clientDataJSON = $_POST['clientDataJSON'] ?? '';
        $attestationObject = $_POST['attestationObject'] ?? '';
        $credentialName = $this->trimString($_POST['credentialName'] ?? '');

        if (empty($clientDataJSON) || empty($attestationObject)) {
            \http_response_code(400);
            echo \json_encode(['error' => 'Missing parameters']);
            exit;
        }

        try {
            $userId = $this->session->userId();

            if (!$userId) {
                \http_response_code(401);
                echo \json_encode(['error' => 'Session data unavailable']);
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
                echo \json_encode(['error' => 'Verification failed - check server error log for details']);
            }
        } catch (\Exception $e) {
            \http_response_code(500);
            echo \json_encode(['error' => $e->getMessage()]);
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
            $this->viewModel->setResponse('Not authenticated');
            $this->viewModel->setBackUrl(TFISH_URL . 'login/');
            $this->viewModel->displayRevoke();
            return [];
        }

        $id = (int) ($_POST['id'] ?? 0);
        $userId = $this->session->userId();

        if (!$userId) {
            $this->viewModel->setResponse('Not authenticated');
            $this->viewModel->setBackUrl(TFISH_URL . 'login/');
            $this->viewModel->displayRevoke();
            return [];
        }

        $result = $this->viewModel->deleteCredential($id, $userId);

        if ($result) {
            $this->viewModel->setResponse('Credential was revoked');
        } else {
            $this->viewModel->setResponse('Failed to revoke credential');
        }

        $this->viewModel->setBackUrl(TFISH_URL . 'register/');
        $this->viewModel->displayRevoke();

        return [];
    }
}
