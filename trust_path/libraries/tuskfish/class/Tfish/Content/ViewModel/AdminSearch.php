<?php

declare(strict_types=1);

namespace Tfish\Content\ViewModel;

/**
 * \Tfish\Content\ViewModel\AdminSearch class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for conducting admin-side free text searches on content objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\Content\ContentTypes	Provides definition of permitted content object types.
 * @uses        trait \Tfish\Traits\Listable Provides a standard implementation of the \Tfish\View\Listable interface.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         array $searchResults Array of content objects matching the search criteria.
 * @var         int $contentCount Number of search results matching the search criteria.
 * @var         string $action Action to be embedded in the form and executed after next submission.
 * @var         array $searchTerms Search terms entered by user.
 * @var         array $escapedSearchTerms Search terms entered by user XSS-escaped for display.
 * @var         string $searchType Type of search (AND, OR, exact).
 * @var         int $start Position in result set to retrieve objects from.
 * @var         int $limit Number of search results to actually retrieve for display on this page view.
 * @var         int $tag Tag ID (not in use).
 * @var         int $onlineStatus Filter content by online or offline status.
 */

class AdminSearch implements \Tfish\Interface\Listable
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\Listable;
    use \Tfish\Traits\ValidateString;

    private $model;
    private $preference;

    private $searchResults = [];
    private $contentCount = 0;

    private $action = '';
    private $searchTerms = [];
    private $escapedSearchTerms = [];
    private $searchType = '';
    private $start = 0;
    private $limit = 0;
    private $tag = 0;
    private $onlineStatus = 0;

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     */
    public function __construct($model, \Tfish\Entity\Preference $preference)
    {
        $this->pageTitle = TFISH_ADMIN_SEARCH;
        $this->model = $model;
        $this->preference = $preference;
        $this->template = 'adminSearch';
        $this->theme = 'admin';
        $this->sort = 'date';
        $this->order = 'DESC';
        $this->secondarySort = 'submissionTime';
        $this->secondaryOrder = 'DESC';

        $this->setMetadata([]);
    }

    /** Actions. */

    /**
     * Display the search form.
     */
    public function displayForm() {}

    /**
     * Search.
     *
     * Search results are cached in the property $searchResults.
     */
    public function search()
    {
        $searchResults = $this->model->search([
            'searchTerms' => $this->searchTerms,
            'escapedSearchTerms' => $this->escapedSearchTerms,
            'searchType' => $this->searchType,
            'start' => $this->start,
            'limit' => $this->limit(),
            'onlineStatus' => $this->onlineStatus
        ]);

        $this->contentCount = (int) \array_shift($searchResults);
        $this->searchResults =  $searchResults;
    }

    /** Utilities. */

    /**
     * Returns the template for formatting the date from preferences.
     */
    public function dateFormat(): string
    {
        return $this->preference->dateFormat();
    }

    /**
     * Return admin-side pagination limit.
     *
     * @return  int Number of items to display on admin-side pages.
     */
    public function limit(): int
    {
        return $this->preference->adminPagination();
    }

    /**
     * Return URL to edit a content object.
     *
     * @param   int $id ID of content object.
     * @return  string URL of edit link.
     */
    public function urlEdit(int $id): string
    {
        return TFISH_LINK . '/admin/content/?action=edit&amp;id=' . $id;
    }

    /** Getters and setters. */

    /**
     * Return content count.
     *
     * @return  int The number of objects that meet the search criteria.
     */
    public function contentCount(): int
    {
        return $this->contentCount;
    }

    /**
     * Return search results.
     *
     * @return  array
     */
    public function searchResults(): array
    {
        return $this->searchResults;
    }

    /**
     * Return start.
     *
     * @return int ID of first object to view in the set of available records.
     */
    public function start(): int
    {
        return $this->start;
    }

    /**
     * Set the starting position in the set of available object.
     *
     * @param int $start ID of first object to view in the set of available records.
     */
    public function setStart(int $start)
    {
        $this->start = $start;
    }

    /**
     * Return the action for this page.
     *
     * The action is usually embedded in the form, to control handling on submission (next page load).
     *
     * @return string
     */
    public function action(): string
    {
        return $this->action;
    }

    /**
     * Set action.
     *
     * @param   string $action Action is embedded in the form, to control handling on submission (next page load)
     */
    public function setAction(string $action)
    {
        $this->action = $this->trimString($action);
    }

    /**
     * Return search terms.
     *
     * @return  array
     */
    public function searchTerms(): array
    {
        return $this->searchTerms;
    }

    /**
     * Return search terms for display in form.
     *
     * @return  string
     */
    public function searchTermsForForm(): string
    {
        return \implode(" ", $this->searchTerms);
    }

    /**
     * Set search terms.
     *
     * @param   string $searchTerms Search terms (keywords).
     */
    public function setSearchTerms(string $searchTerms)
    {
        $searchTerms = $this->trimString($searchTerms);

        $cleanSearchTerms = $escapedSearchTerms = $cleanEscapedSearchTerms = [];

        // Create an escaped copy that will be used to search the HTML teaser and description fields.
        $escapedSearchTerms = \htmlspecialchars($searchTerms, ENT_NOQUOTES, "UTF-8");

        if ($this->searchType !== 'exact') {
            $searchTerms = \explode(" ", $searchTerms);
            $escapedSearchTerms = \explode(" ", $escapedSearchTerms);
        } else {
            $searchTerms = [$searchTerms];
            $escapedSearchTerms = [$escapedSearchTerms];
        }

        // Trim search terms and discard any that are less than the minimum search length characters.
        foreach ($searchTerms as $term) {
            $term = $this->trimString($term);

            if (!empty($term) && \mb_strlen($term, 'UTF-8') >= $this->preference->minSearchLength()) {
                $cleanSearchTerms[] = $term;
            }
        }

        $this->searchTerms = $cleanSearchTerms;

        foreach ($escapedSearchTerms as $escapedTerm) {
            $escapedTerm = $this->trimString($escapedTerm);

            if (!empty($escapedTerm) && \mb_strlen($escapedTerm, 'UTF-8')
                    >= $this->preference->minSearchLength()) {
                $cleanEscapedSearchTerms[] = $escapedTerm;
            }
        }

        $this->escapedSearchTerms = $cleanEscapedSearchTerms;
    }

    /**
     * Return search terms XSS escaped for display.
     *
     * @return  array
     */
    public function escapedSearchTerms(): array
    {
        return $this->escapedSearchTerms;
    }

    /**
     * Return search type.
     *
     * @return  string Options are: "AND", "OR", "exact".
     */
    public function searchType(): string
    {
        return $this->searchType;
    }

    /**
     * Set search type.
     *
     * @param   string $searchType Options are: "AND", "OR", "exact".
     */
    public function setSearchType(string $searchType)
    {
        if (!\in_array($searchType, ['AND', 'OR', 'exact'], true)) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

        $this->searchType = $this->trimString($searchType);
    }

    /**
     * Return extra parameters to be included in pagination control links.
     *
     * @return  array
     */
    public function extraParams(): array
    {
        $extraParams = [];

        if (!empty($this->action)) {
            $extraParams['action'] = $this->action();
        }

        if (!empty($this->searchType())) {
            $extraParams['searchType'] = $this->searchType();
        }

        if (!empty($this->searchTerms())) {
            $extraParams['searchTerms'] = \implode(" ", $this->searchTerms());
        }

        return $extraParams;
    }

    /**
     * Return onlineStatus
     *
     * @return  int Both online and offline content (0).
     */
    public function onlineStatus(): int
    {
        return $this->onlineStatus;
    }

    /**
     * Return tag ID.
     *
     * Not in use.
     *
     * @return  int
     */
    public function tag(): int
    {
        return $this->tag;
    }

    /**
     * Set tag ID.
     *
     * Not in use.
     *
     * @param   int $tag ID of tag.
     */
    public function setTag(int $tag)
    {
        $this->tag = $tag;
    }
}
