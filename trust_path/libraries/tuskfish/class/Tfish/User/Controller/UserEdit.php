<?php

declare(strict_types=1);

namespace Tfish\User\Controller;

/**
 * \Tfish\User\Controller\UserEdit class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 */

/**
 * Controller for editing user objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 * @uses        trait \Tfish\Trait\ValidateToken Methods for CSRF protection.
 * @var         object $model Classname of the model used to display this page (unused).
 * @var         object $viewModel Classname of the viewModel used to display this page.
 */

class UserEdit
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;

    private object $viewModel;

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   object $viewModel Instance of a viewModel class.
     */
    public function __construct(object $model, object $viewModel)
    {
        $this->viewModel = $viewModel;
    }

    /* Actions. */

    /**
     * Cancel the add/edit user action.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function cancel(): array
    {
        $this->viewModel->displayCancel();

        return [];
    }

    /**
     * Display the user entry form.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function display(): array
    {
        $this->viewModel->displayAdd();

        return [];
    }

    /**
     * Display the edit user form.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function edit(): array
    {
        $this->viewModel->displayEdit();

        return [];
    }

    /**
     * Save the new/edited user object.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function save(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->viewModel->displayCancel();
            return [];
        }

        $token = isset($_POST['token']) ? (string) $_POST['token'] : '';
        $this->validateToken($token);
        $this->viewModel->displaySave();

        return [];
    }
}
