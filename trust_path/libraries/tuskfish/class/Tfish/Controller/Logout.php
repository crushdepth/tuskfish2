<?php

declare(strict_types=1);

namespace Tfish\Controller;

/**
 * \Tfish\Controller\Logout class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Controller for logging out.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         object $model Instance of the model required by this route.
 * @var         object $viewModel Instance of the viewModel required by this route.
 * @var         \Tfish\Session $session Instance of the session management class.
 */

class Logout
{
    use \Tfish\Traits\ValidateString;

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
     * Process logout action.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function display(): array
    {
        // Logout is a plain GET link, so refuse requests that browsers flag as coming from another
        // site (prevents forced logout). Browsers that don't send Sec-Fetch-Site fall through.
        if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
            \header('Location: ' . TFISH_URL, true, 303);
            exit;
        }

        $this->model->logout();

        return [];
    }
}
