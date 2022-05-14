<?php

declare(strict_types=1);

namespace Tfish\Expert\ViewModel;

/**
 * \Tfish\Expert\ViewModel\Model\Search class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for free text searches of expert objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\Listable Provides a standard implementation of the \Tfish\View\Listable interface.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         array $searchResults Array of content objects matching the search criteria.
 * @var         int $resultCount Number of search results matching the search criteria.
 * @var         string $action Action to be embedded in the form and executed after next submission.
 * @var         array $searchTerms Search terms entered by user.
 * @var         array $escapedSearchTerms Search terms entered by user XSS-escaped for display.
 * @var         int $start Position in result set to retrieve objects from.
 * @var         int $tag Tag ID (not in use).
 * @var         int $onlineStatus Display online content only (1).
 */

class Search implements \Tfish\ViewModel\Listable
{
    use \Tfish\Expert\Traits\Options;
    use \Tfish\Traits\Listable;
    use \Tfish\Traits\ValidateString;

    private $model;
    private $preference;
    private $expert = '';
    private $searchResults = [];
    private $contentCount = 0;

    private $action = '';
    private $id = 0;
    private $alpha = '';
    private $searchTerms = [];
    private $escapedSearchTerms = [];
    private $start = 0;
    private $tag = 0;
    private $country = 0;
    private $onlineStatus = 1; // Lock to online only.

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     */
    public function __construct($model, \Tfish\Entity\Preference $preference)
    {
        $this->pageTitle = TFISH_EXPERTS;
        $this->model = $model;
        $this->preference = $preference;
        $this->template = 'expertListView';
        $this->theme = 'default';
        $this->sort = 'date';
        $this->order = 'DESC';
        $this->secondarySort = 'submissionTime';
        $this->secondaryOrder = 'DESC';

        $this->setMetadata([]);
    }

    /** Actions. **/

    /**
     * Display the search form.
     */
    public function displayForm() {}

    /**
     * Display a single expert object.
     */
    public function displayObject()
    {
        $this->expert = $this->model->getObject($this->id);

        if ($this->expert) {
            $this->pageTitle = $this->expert->metaTitle();
            $this->description = $this->expert->metaDescription();
            $this->template = 'expert';
            $this->setMetadata();
        } else {
            $this->pageTitle = TFISH_ERROR;
            $this->response = TFISH_ERROR_NO_SUCH_EXPERT;
            $this->backUrl = TFISH_URL;
            $this->template = 'response';
        }
    }

    public function searchAlpha()
    {
        $this->action = 'name';
        $searchResults = $this->model->searchAlphabetically([
            'alpha' => $this->alpha,
            'onlineStatus' => $this->onlineStatus,
            'start' => $this->start,
            'limit' => $this->limit(),
        ]);

        $this->contentCount = (int) \array_shift($searchResults);
        $this->searchResults = $searchResults;
    }

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
            'start' => $this->start,
            'limit' => $this->limit(),
        ]);

        $this->expertCount = (int) \array_shift($searchResults);
        $this->searchResults = $searchResults;
    }

    /** Utilities. */

    /**
     * Return IDs and titles of tags that are actually in use with content objects.
     *
     * @param   string $zeroOption Text for the default (unselected) option.
     * @return  array IDs and titles as key-value pairs.
     */
    public function activeTagOptions(string $zeroOption = TFISH_SELECT_TAGS): array
    {
        $zeroOption = $this->trimString($zeroOption);
        $rows = $this->model->activeTagOptions('expert');

        return $this->selectBoxOptions($zeroOption, $rows);
    }

    /**
     * Return limit.
     *
     * @return  int The number of  search results to retrieve.
     */
    public function limit(): int
    {
        return $this->preference->searchPagination();
    }

    /** Getters and setters. */

    /**
     * Return alphabetical filter criteria.
     *
     * @return string
     */
    public function alpha(): string
    {
        return $this->alpha;
    }

    /**
     * Set alphabetical filter criteria.
     *
     * @param string $letter
     * @return void
     */
    public function setAlpha(string $letter)
    {
        $letter = $this->trimString($letter);

        if (!$this->isAlpha($letter) || \mb_strlen($letter, 'UTF-8') > 1) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

        $this->alpha = $letter;
    }

    /**
     * Return content count.
     *
     * @return  int The number of objects that meet the search criteria.
     */
    public function contentCount(): int
    {
        return $this->contentCount;
    }

    public function expert(): \Tfish\Expert\Entity\Expert
    {
        return $this->expert;
    }

    /**
     * Returns the expert ID query parameter.
     *
     * @return integer
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * Set the expert ID of the query parameter.
     *
     * @param integer $id
     * @return void
     */
    public function setId(int $id)
    {
        $this->id = (int) $id;
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

        $searchTerms = \explode(" ", $searchTerms);
        $escapedSearchTerms = \explode(" ", $escapedSearchTerms);

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
                $cleanEscapedSearchTerms[] = (string) $escapedTerm;
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

        if (!empty($this->searchTerms())) {
            $extraParams['searchTerms'] = \implode(" ", $this->searchTerms());
        }

        return $extraParams;
    }

    /**
     * Return onlineStatus
     *
     * @return  int Online content only (1).
     */
    public function onlineStatus(): int
    {
        return $this->onlineStatus;
    }

    /**
     * Return tag ID.
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
     * @param   int $tag ID of tag.
     */
    public function setTag(int $tag)
    {
        $this->tag = $tag;
    }

    /**
     * Return tags associated with an expert object.
     *
     * @return  array Array of tags as id/title key-value pairs.
     */
    public function expertTags()
    {
        return $this->model->getTagsForObject($this->id, 'expert', 'expert');
    }

    /**
     * Return canonical URL for this page view.
     *
     * Used to populate the canonical link tag in theme files.
     *
     * @return  string
     */
    public function canonicalUrl(): string
    {
        $canonicalUrl = TFISH_EXPERTS_URL;

        if ($this->id) return $canonicalUrl . '?id=' . $this->id;

        if ($this->start || $this->tag || $this->country) $canonicalUrl .= '?';

        $setParams = [];

        foreach (['start', 'tag', 'country'] as $param) {
            if (!empty($this->$param)) {
                $setParams[$param] = $param . '=' . $this->param;
            }
        }

        $queryString = '?' . \implode('&amp;', $setParams);
        $canonicalUrl .= $queryString;

        return $canonicalUrl;
    }

    /**
     * Set page-specific overrides of the site metadata.
     *
     * Overrides generic site metadata.
     *
     * @param   array $metadata Metadata overrides as key-value pairs.
     */
    public function setMetadata(array $metadata = [])
    {
        if (!empty($this->pageTitle)) $metadata['title'] = $this->pageTitle;
        if (!empty($this->description)) $metadata['description'] = $this->description;
        if (!empty($this->author)) $metadata['author'] = $this->author;
        if (!empty($this->robots)) $metadata['robots'] = $this->robots;

        $metadata['canonicalUrl'] = $this->canonicalUrl();

        $this->metadata = $metadata;
    }
}
