<?php

declare(strict_types=1);

namespace Tfish\User\Controller;

/**
 * \Tfish\User\Controller\Admin class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 */

/**
 * Controller for displaying the user administration interface.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Trait\ValidateToken Methods for CSRF protection.
 * @var         object $model Classname of the model used to display this page.
 * @var         object $viewModel Classname of the viewModel used to display this page.
 */

class Admin
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;

    private object $model;
    private object $viewModel;

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   object $viewModel Instance of a viewModel class.
     */
    public function __construct(object $model, object $viewModel)
    {
        $this->model = $model;
        $this->viewModel = $viewModel;
    }

    /* Actions. */

    /**
     * Cancel the delete user action.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function cancel(): array
    {
        $this->viewModel->displayCancel();

        return [];
    }

    /**
     * Display the confirm delete user page.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function confirmDelete(): array
    {
        $id = (int) ($_GET['id'] ?? 0);
        $this->viewModel->setId($id);
        $this->viewModel->setUserEmail();

        $this->viewModel->displayConfirmDelete();

        return [];
    }

    /**
     * Delete a user.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function delete(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->viewModel->displayCancel();
            return [];
        }

        $token = isset($_POST['token']) ? (string) $_POST['token'] : '';
        $this->validateToken($token);

        $id = (int) ($_POST['id'] ?? 0);
        $this->viewModel->setId($id);
        $this->viewModel->displayDelete();

        return [];
    }

    /**
     * Display the summary table of users.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function display(): array
    {
        $id = (int) ($_GET['id'] ?? 0);
        $this->viewModel->setId($id);
        $this->viewModel->setSort('id');
        $this->viewModel->setOrder('ASC');

        $this->viewModel->displayTable();

        return [];
    }

    /**
     * Toggle a user online or offline.
     *
     * Toggling a user offline suspends their editorial privileges.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function toggle(): array
    {
        $id = (int) ($_GET['id'] ?? 0);
        $this->viewModel->setId($id);

        $this->viewModel->displayToggle();

        return [];
    }
}
