<?php

declare(strict_types=1);

namespace Tfish\View;

/**
 * \Tfish\View\Listing class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Represents a view of a list of objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @var         object $viewModel Instance of the viewModel required by this route.
 * @var         \Tfish\Pagination Instance of the Tfish pagination class.
 * @var         string $template Name of the template used to generate this view (without file extension).
 */

class Listing
{
    private $viewModel;
    private $pagination;
    private $template;

    /**
     * Constructor
     *
     * @param   \Tfish\Interface\Listable $viewModel Instance of the viewModel required by this route.
     * @param   \Tfish\Pagination Instance of the Tuskfish pagination class.
     */
    public function __construct(\Tfish\Interface\Listable $viewModel, \Tfish\Pagination $pagination)
    {
        $this->viewModel = $viewModel;
        $this->pagination = $pagination;
    }

    /**
     * Render the pagination control.
     *
     * @return string Pagination control output as HTML.
     */
    public function pagination()
    {
        $this->pagination->setCount($this->viewModel->contentCount());
        $this->pagination->setLimit($this->viewModel->limit());
        $this->pagination->setStart($this->viewModel->start());
        $this->pagination->setTag($this->viewModel->tag());
        $this->pagination->setExtraParams($this->viewModel->extraParams());

        return $this->pagination->renderPaginationControl();
    }

    /**
     * Render blocks for display.
     *
     * @param string $path
     * @return array
     */
    public function renderBlocks(string $path): array
    {
        return $this->viewModel->fetchBlocks($path);
    }

    /**
     * Render the template used by this page.
     *
     * @param string $path URL path for this request.
     *
     * @return  string Template output as HTML.
     */
    public function render($path): string
    {
        $this->template = $this->viewModel->template();
        $this->template->assign('viewModel', $this->viewModel);
        $this->template->assign('pagination', $this->pagination());

        return $this->template->render();
    }
}
