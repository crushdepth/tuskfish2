<?php

declare(strict_types=1);

namespace Tfish\Expert\Controller;

/**
 * \Tfish\Expert\Controller\Admin class file.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     expert
 */

/**
 * Controller for displaying the Expert administration interface.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     expert
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         object $model Classname of the model used to display this page.
 * @var         object $viewModel Classname of the viewModel used to display this page.
 */

class Admin
{
    use \Tfish\Traits\ValidateString;

    private $model;
    private $viewModel;

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   object $viewModel Instance of a viewModel class.
     */
    public function __construct($model, $viewModel)
    {
        $this->model = $model;
        $this->viewModel = $viewModel;
    }

    /* Actions. */

    /**
     * Cancel the delete expert action.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function cancel(): array
    {
        $this->viewModel->displayCancel();

        return [];
    }

    /**
     * Display the confirm delete expert page.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function confirmDelete(): array
    {
        $id = (int) ($_GET['id'] ?? 0);
        $this->viewModel->setId($id);
        $this->viewModel->displayConfirmDelete();

        return [];
    }

    /**
     * Delete an expert.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function delete(): array
    {
        $id = (int) ($_POST['id'] ?? 0);
        $this->viewModel->setId($id);

        $this->viewModel->displayDelete();

        return [];
    }

    /**
     * Display the summary table of experts.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function display(): array
    {
        $id = (int) ($_GET['id'] ?? 0);
        $this->viewModel->setId($id);

        $start = (int) ($_GET['start'] ?? 0);
        $this->viewModel->setStart($start);

        $tag = (int) ($_REQUEST['tag'] ?? 0);
        $this->viewModel->setTag($tag);

        $onlineStatus = (int) ($_REQUEST['onlineStatus'] ?? 2);
        $this->viewModel->setOnlineStatus($onlineStatus);

        $this->viewModel->setSort('submissionTime');
        $this->viewModel->setOrder('DESC');
        $this->viewModel->setSecondarySort('lastName');
        $this->viewModel->setSecondaryOrder('ASC');

        $this->viewModel->displayTable();

        return [];
    }

    /**
     * Toggle an expert object online or offline.
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
