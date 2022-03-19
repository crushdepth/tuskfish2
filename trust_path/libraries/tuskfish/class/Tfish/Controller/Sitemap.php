<?php

declare(strict_types=1);

namespace Tfish\Controller;

/**
 * \Tfish\Controller\Sitemap class file.
 * 
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Controller for generating a sitemap.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @var         object $model Instance of the model required by this route.
 * @var         object $viewModel Instance of the viewModel required by this route.
 */

class Sitemap
{
    private $model;
    private $viewModel;

    /**
     * Constructor
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
     * Cancel sitemap generation.
     * 
     * @return  array Empty array (the output of this action is not cached).
     */
    public function cancel(): array
    {
        $this->viewModel->displayCancel();

        return [];
    }

    /**
     * Display confirmatation message.
     * 
     * @return  array Empty array (the output of this action is not cached).
     */
    public function display(): array
    {
        $this->viewModel->displayConfirm();

        return [];
    }

    /**
     * Generate sitemap.
     * 
     * @return  array Empty array (the output of this action is not cached).
     */
    public function generate(): array
    {
        $this->viewModel->displayGenerate();

        return [];
    }
}
