<?php

declare(strict_types=1);

namespace Tfish\Content\Controller;

/**
 * \Tfish\Content\Controller\Search class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Controller for the search route.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         object $model Classname of the model used to display this page (unused).
 * @var         object $viewModel Classname of the viewModel used to display this page.
 */

class Search
{
    use \Tfish\Traits\ValidateString;

    private object $viewModel;

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

    /**
     * Display the search form.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function display(): array
    {
        $this->viewModel->displayForm();

        return [];
    }

    /**
     * Search and display results.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function search(): array
    {
        $this->viewModel->setAction('search');
        $start = \max(0, (int) ($_GET['start'] ?? 0));
        $this->viewModel->setStart($start);

        $searchType = $this->trimString($_REQUEST['searchType'] ?? '');
        $this->viewModel->setSearchType($searchType);

        // The browser and PHP have already decoded the terms (form submissions and pagination links
        // alike), so they are used as received. Decoding again would alter terms such as '%41'.
        $cleanTerms = $this->trimString($_GET['searchTerms'] ?? $_POST['searchTerms'] ?? '');

        $this->viewModel->setSearchTerms($cleanTerms);
        $this->viewModel->search();

        return [];
    }
}
