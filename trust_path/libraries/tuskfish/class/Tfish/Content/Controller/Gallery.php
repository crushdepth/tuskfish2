<?php

declare(strict_types=1);

namespace Tfish\Content\Controller;

/**
 * \Tfish\Content\Controller\Gallery class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Controller for displaying an image gallery.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @var         object $model Classname of the model used to display this page (unused).
 * @var         object $viewModel Classname of the viewModel used to display this page.
 */

class Gallery
{
    private $viewModel;

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class (unused).
     * @param   object $viewModel Instance of a viewModel class.
     */
    public function __construct(object $model, object $viewModel)
    {
        $this->viewModel = $viewModel;
    }

    /* Actions. */

    /**
     * Display a list of images.
     *
     * @return  array Cache parameters used to locate cached copies of a given page view.
     */
    public function display(): array
    {
        $cacheParams = [];

        // Flag to display 'logout' link instead of 'login' for members.
        if (!empty($_SESSION['id'])) {
            $cacheParams['loggedIn'] = '1';
        }

        $id = (int) ($_GET['id'] ?? 0);

        $this->viewModel->setId($id);
        if (!empty($id)) $cacheParams['id'] = $id;

        $start = (int) ($_GET['start'] ?? 0);

        $this->viewModel->setStart($start);
        if (!empty($start)) $cacheParams['start'] = $start;

        $tag = (int) ($_GET['tag'] ?? 0);

        $this->viewModel->setTag($tag);
        if (!empty($tag)) $cacheParams['tag'] = $tag;

        $type = 'TfImage';

        $this->viewModel->setType($type);
        $cacheParams['type'] = $type;

        $this->viewModel->setSort('date');
        $this->viewModel->setOrder('DESC');
        $this->viewModel->setSecondarySort('submissionTime');
        $this->viewModel->setSecondaryOrder('DESC');

        $this->viewModel->displayList();

        return $cacheParams;
    }
}
