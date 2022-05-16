<?php

declare(strict_types=1);

namespace Tfish\Expert\Controller;

/**
 * \Tfish\Expert\Controller\Search class file.
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
 * @var         object $model Classname of the model used to display this page.
 * @var         object $viewModel Classname of the viewModel used to display this page.
 */

class Search
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

    /**
     * Display the search form.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function display(): array
    {
        $cacheParams = ['page' => 'experts'];
        $id = (int) ($_GET['id'] ?? 0);
        $tag = (int) ($_REQUEST['tag'] ?? 0);
        $country = (int) ($_REQUEST['country'] ?? 0);
        $start = (int) ($_GET['start'] ?? 0);
        $cleanTerms = '';

        if ($id > 0) {
            $cacheParams['id'] = $id;
            $this->viewModel->setId($id);
            $this->viewModel->displayObject();

            return $cacheParams;
        }

        if ($tag > 0 || $country > 0) {
            if ($start > 0) $cacheParams['start'] = $start;
            $cacheParams['tag'] = $tag;
            $cacheParams['country'] = $country;
            $this->viewModel->setStart($start);
            $this->viewModel->setTag($tag);
            $this->viewModel->setCountry($country);
            $this->viewModel->displayFilter();

            return $cacheParams;
        }

        if (isset($_GET['searchTerms'])) {
            $terms = $this->trimString($_REQUEST['searchTerms']);
            $terms = \rawurldecode($terms);
            $cleanTerms = \htmlspecialchars_decode($terms, ENT_QUOTES|ENT_HTML5);
        } else {
            $cleanTerms = $this->trimString($_POST['searchTerms'] ?? '');
        }

        if (isset($_REQUEST['searchTerms'])) {
            if ($start > 0) $this->viewModel->setStart($start);
            $this->viewModel->setSearchTerms($cleanTerms);
            $this->viewModel->search();

            return [];
        }

        $this->viewModel->displayForm();

        return $cacheParams;
    }

    /**
     * Browse experts by lastname.
     *
     * @return array
     */
    public function name(): array
    {
        $cacheParams = ['page' => 'experts'];

        $start = (int) ($_GET['start'] ?? 0);
        if ($start > 0) $cacheParams['start'] = $start;
        $this->viewModel->setStart($start);

        $alpha = $this->trimString($_GET['alpha'] ?? '');

        if (!empty($alpha)) {
            $cacheParams['alpha'] = $alpha;
            $this->viewModel->setAlpha($alpha);
        }

        $this->viewModel->searchAlpha();

        return $cacheParams;
    }

    /**
     * Search and display results.
     *
     * @return  array Empty array (the output of this action is not cached).
     */
    public function search(): array
    {
        // Pagination.
        $start = (int) ($_GET['start'] ?? 0);
        $this->viewModel->setStart($start);

        // Browser directory by lastname.
        $alpha = $this->trimString($_GET['alpha'] ?? '');

        if (!empty($alpha)) {
            $this->viewModel->setAlpha($alpha);
            $this->viewModel->searchAlpha();
            return [];
        }

        // Search terms passed in from a pagination control link have been i) encoded and ii) escaped.
        // Search terms entered directly into the search form can be used directly.
        $cleanTerms = '';

        if (isset($_GET['searchTerms'])) {
            $terms = $this->trimString($_REQUEST['searchTerms']);
            $terms = \rawurldecode($terms);
            $cleanTerms = \htmlspecialchars_decode($terms, ENT_QUOTES|ENT_HTML5);
        } else {
            $cleanTerms = $this->trimString($_POST['searchTerms'] ?? '');
        }

        $this->viewModel->setSearchTerms($cleanTerms);
        $this->viewModel->setAction('search');
        //$this->viewModel->search();

        return [];
    }
}
