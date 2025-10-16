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
 * @var         \Tfish\Session $session Instance of the session management class.
 */

class Login
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;

    private object $model;
    private object $viewModel;
    private \Tfish\Session $session;

    /**
     * Constructor
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
            $this->model->setSession($this->session);
            $this->model->login($this->trimString($_POST['email']), $_POST['password']);
        }

        $this->viewModel->displayForm();

        return [];
    }
}
