<?php

declare(strict_types=1);

namespace Tfish\Content\Controller;

/**
 * \Tfish\Content\Controller\Admin class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Controller for displaying the content administration interface.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
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
     * Cancel the delete content action.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function cancel(): array
    {
        $this->viewModel->displayCancel();

        return [];
    }

    /**
     * Display the confirm delete content page.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function confirmDelete(): array
    {
        $id = (int) ($_GET['id'] ?? 0);
        $lang = $this->trimString($_GET['lang']);
        $this->viewModel->setId($id);
        $this->viewModel->setLanguage($lang);
        $this->viewModel->setContentTitle();

        $this->viewModel->displayConfirmDelete();

        return [];
    }

    /**
     * Delete a content object.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function delete(): array
    {
        $id = (int) ($_POST['id'] ?? 0);
        $lang = $this->trimString($_POST['language']);
        $this->viewModel->setId($id);
        $this->viewModel->setLanguage($lang);

        $this->viewModel->displayDelete();

        return [];
    }

    /**
     * Display the summary table of content objects.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function display(): array
    {
        $id = (int) ($_GET['id'] ?? 0);
        $this->viewModel->setId($id);

        // Controls interface language.
        $this->viewModel->setLanguage($_SESSION['lang']);

        // Controls language filter.
        $this->viewModel->setLanguageFilter($_REQUEST['language'] ?? '');

        $start = (int) ($_GET['start'] ?? 0);
        $this->viewModel->setStart($start);

        $tag = (int) ($_REQUEST['tag'] ?? 0);
        $this->viewModel->setTag($tag);

        $type = $this->trimString($_REQUEST['type'] ?? '');
        $this->viewModel->setType($type);

        $onlineStatus = (int) ($_REQUEST['onlineStatus'] ?? 2);
        $this->viewModel->setOnlineStatus($onlineStatus);

        $this->viewModel->setSort('date');
        $this->viewModel->setOrder('DESC');
        $this->viewModel->setSecondarySort('submissionTime');
        $this->viewModel->setSecondaryOrder('DESC');

        $this->viewModel->displayTable();

        return [];
    }

    /**
     * Toggle individual content objects on or offline.
     *
     * Uses AJAX call (htmx) to avoid page reload. Implemented post v2.0.6.
     */

    public function toggle()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $lang = $this->trimString($_GET['lang']);
        $status = (int) ($_POST['status'] ?? 0); // online status of individual content item.

        $this->viewModel->setLanguage($lang);
        $this->viewModel->setId($id);
        $this->viewModel->setStatus($status);
        $this->viewModel->displayToggle();
        exit;
    }
}
