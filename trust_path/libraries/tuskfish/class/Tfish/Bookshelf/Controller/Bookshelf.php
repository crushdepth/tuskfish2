<?php

declare(strict_types=1);

namespace Tfish\Bookshelf\Controller;

/**
 * \Tfish\Bookshelf\Controller\Bookshelf class file.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     Bookshelf
 */

/**
 * Controller for the Bookshelf module.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     Bookshelf
 * @var         object $model Instance of the model required by this route.
 * @var         object $viewModel Instance of the viewModel required by this route.
 */
class Bookshelf
{
    private object $model;
    private object $viewModel;

    /**
     * Constructor
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
     * Display the bookshelf.
     *
     * @return  array Cache parameters (none: the page is static and cached under its bare route).
     */
    public function display(): array
    {
        $this->viewModel->displayBookshelf();

        return [];
    }
}
