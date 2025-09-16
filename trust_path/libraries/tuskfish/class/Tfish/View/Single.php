<?php

declare(strict_types=1);

namespace Tfish\View;

/**
 * \Tfish\View\Single class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Represents a view of a single object or static page.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @var         object $viewModel Instance of the viewModel required by this route.
 * @var         string $template Name of the template used to generate this view (without file extension).
 */

class Single
{
    private \Tfish\Interface\Viewable $viewModel;
    private $template;

    /**
     * Constructor
     *
     * @param   object $viewModel Instance of the viewModel required by this route.
     */
    public function __construct(\Tfish\Interface\Viewable $viewModel)
    {
        $this->viewModel = $viewModel;
    }

    /**
     * Render the template used by this page.
     *
     * @return  string Template output as HTML.
     */
    public function render(): string
    {
        $this->template = $this->viewModel->template();
        $this->template->assign('viewModel', $this->viewModel);

        return $this->template->render();
    }
}
