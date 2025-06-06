<?php

declare(strict_types=1);

namespace Tfish\Controller;

/**
 * \Tfish\Controller\BlockEdit class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Controller for editing block objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @var         object $model Classname of the model used to display this page.
 * @var         object $viewModel Classname of the viewModel used to display this page.
 */

class BlockEdit
{
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
     * Cancel the add/edit block action.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function cancel(): array
    {
        $this->viewModel->displayCancel();

        return [];
    }

    /**
     * Display the block entry form.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function display(): array
    {
        $this->viewModel->displayAdd();

        return [];
    }

    /**
     * Display the edit block form.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function edit(): array
    {
        $this->viewModel->displayEdit();

        return [];
    }

    /**
     * Save the new/edited block object.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function save(): array
    {
        $this->viewModel->displaySave();

        return [];
    }
}
