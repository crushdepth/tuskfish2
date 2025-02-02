<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\Pagination class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.1
 * @package     core
 */

/**
 * Generates pagination controls for paging through content.
 *
 * The number of pagination control slots is set in Tuskfish Preferences. Choose an odd number for
 * best results.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 1.0
 * @since       1.1
 * @package     core
 * @uses        trait \Tfish\Traits\Language Whitelist of supported languages on this system.
 * @uses        trait \Tfish\Traits\TraversalCheck	Validates that a filename or path does NOT contain directory traversals in any form.
 * @uses        trait \Tfish\Traits\UrlCheck	Validate that a URL meets the specification.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish site preference class.
 * @var         int $count Number of records in the result set.
 * @var         int $limit Maximum number (subset) of records to retrieve in on page view.
 * @var         int $start ID of first record to retrieve in subset.
 * @var         string $language 2-letter ISO 639-1 language filter.
 * @var         string $url Base URL for constructing pagination links.
 * @var         int $tag ID of tag filter.
 * @var         array $extraParams Extra parameters to be included in pagination control links as key => value pairs.
 * @var         string $extraParamsString Extra parameters converted to a query string fragment.
 */

class Pagination
{
    use \Tfish\Traits\Language;
    use \Tfish\Traits\TraversalCheck;
    use \Tfish\Traits\UrlCheck;
    use \Tfish\Traits\ValidateString;

    private $preference;
    private int $count;
    private int $limit;
    private int $start;
    private string $language;
    private string $url;

    private int $tag;
    private array $extraParams;
    private string $extraParamsString;

    /**
     * Constructor.
     *
     * @param   \Tfish\Entity\Preference $preference An instance of the Tuskfish site preferences class.
     * @param   string $path Base URL for constructing pagination links.
     */
    function __construct(Entity\Preference $preference, string $path, string $language)
    {
        $this->preference = $preference;
        $this->setUrl($path);
        $this->count = 0;
        $this->limit = 0;
        $this->start = 0;
        $this->setLanguage($language);
        $this->tag = 0;
        $this->extraParams = [];
    }

    /**
     * Creates a pagination control designed for use with the Bootstrap framework.
     *
     * $query is an array of arbitrary query string parameters. Note that these need to be passed
     * in as an array of key => value pairs, and you should build this yourself using known and
     * whitelisted values. Do not pass through random query strings someone gave you on the
     * internetz.
     *
     * If you want to create pagination controls for other presentation-side libraries add
     * additional methods to this class.
     *
     * @return string HTML pagination control.
     */
    public function renderPaginationControl()
    {
        // If the count is zero there is no need for a pagination control.
        if ($this->count === 0) {
            return false;
        }

        // 1. Calculate number of pages, page number of start object and adjust for remainders.
        $pageSlots = [];
        $pageCount = (int) (($this->count / $this->limit));
        $remainder = $this->count % $this->limit;

        if ($remainder) {
            $pageCount += 1;
        }

        $pageRange = range(1, $pageCount);

        // No need for pagination control if only one page.
        if ($pageCount === 1) {
            return false;
        }

        // 2. Calculate current page.
        $currentPage = (int) (($this->start / $this->limit) + 1);

        // 3. Calculate length of pagination control (number of slots).
        $elements = ((int) $this->preference->paginationElements() > $pageCount)
                ? $pageCount : (int) $this->preference->paginationElements();

        // 4. Calculate the fore offset and initial (pre-adjustment) starting position.
        $offsetInt = (int) (($elements - 1) / 2);
        $offsetFloat = ($elements - 1) / 2;
        $pageStart = $currentPage - $offsetInt;

        // 5. Check if fore exceeds bounds. If so, set start = 1 and extract the range.
        $foreBoundcheck = $currentPage - $offsetInt;

        // 6. Check if aft exceeds bounds. If so set start = $pageCount - length.
        $aftBoundcheck = ($currentPage + $offsetFloat);

        // This is the tricky bit - slicing a variable region out of the range.
        if ($pageCount === $elements) {
            $pageSlots = $pageRange;
        } elseif ($foreBoundcheck < 1) {
            $pageSlots = array_slice($pageRange, 0, $elements, true);
        } elseif ($aftBoundcheck >= $pageCount) {
            $pageStart = $pageCount - $elements;
            $pageSlots = array_slice($pageRange, $pageStart, $elements, true);
        } else {
            $pageSlots = array_slice($pageRange, ($pageStart - 1), $elements, true);
        }

        // 7. Substitute in the 'first' and 'last' page elements and sort the array back into
        // numerical sort.
        end($pageSlots);
        unset($pageSlots[key($pageSlots)]);
        $pageSlots[($pageCount - 1)] = TFISH_PAGINATION_LAST;
        reset($pageSlots);
        unset($pageSlots[key($pageSlots)]);
        $pageSlots[0] = TFISH_PAGINATION_FIRST;
        ksort($pageSlots);

        return $this->_renderPaginationControl($pageSlots, $currentPage);
    }

    /** @internal */
    private function _renderPaginationControl(array $pageSlots, int $currentPage)
    {
        $control = '<nav aria-label="Page navigation"><ul class="pagination">';

        $query = '';

        foreach ($pageSlots as $key => $slot) {
            $this->start = (int) ($key * $this->limit);

            if ($this->start || $this->tag || $this->language || $this->extraParamsString) {
                $args = [];

                if (!empty($this->extraParamsString)) {
                    $args[] = $this->extraParamsString;
                }

                if (!empty($this->language)) {
                    $args[] = 'lang=' . $this->language;
                }

                if (!empty($this->tag)) {
                    $args[] = 'tag=' . $this->tag;
                }

                if (!empty($this->start)) {
                    $args[] = 'start=' . $this->start;
                }

                $query = '?' . implode('&amp;', $args);
            } else {
                $query = '';
            }

            if (($key + 1) === $currentPage) {
                $control .= '<li class="page-item active"><a class="page-link" href="' . $this->url
                        . $query . '">' . $slot . '</a></li>';
            } else {
                $control .= '<li class="page-item"><a class="page-link" href="' . $this->url
                        . $query . '">' . $slot . '</a></li>';
            }
            unset($query, $key, $slot);
        }

        $control .= '</ul></nav>';

        return $control;
    }

    /**
     * Set the count property, which represents the number of objects matching the page parameters.
     *
     * @param int $count
     */
    public function setCount(int $count)
    {
        $this->count = $count >= 0 ? $count : 0;
    }

    /**
     * Set extra parameters to be included in pagination control links.
     *
     * $extraParams is a potential XSS attack vector; only use known and whitelisted keys.
     *
     * The key => value pairs are i) rawurlencoded and ii) entity escaped. However, in sort to
     * avoid messing up the query and avoid unnecessary decoding it is possible to maintain
     * manual control over the operators. (Basically, input requiring encoding or escaping is
     * absolutely not wanted here, it is just being conducted to mitigate XSS attacks). If you
     * actually *want* to use such input (check your sanity), you will need to decode it prior to
     * use on the landing page.
     *
     * @param array $extraParams Query string to be appended to the URLs (control script params)
     * @return boolean Returns false on failure.
     */
    public function setExtraParams(array $extraParams)
    {
        $clean_extraParams = [];

        foreach ($extraParams as $key => $value) {
            if ($this->hasTraversalorNullByte((string) $key) || $this->hasTraversalorNullByte((string) $value)) {
                \trigger_error(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE, E_USER_ERROR);
                exit; // Hard stop due to high probability of abuse.
            }

            // URL encode and \htmlspecialchars() the key/value pairs.
            $clean_extraParams[] = $this->encodeEscapeUrl((string) $key) . '=' . $this->encodeEscapeUrl((string) $value);
            unset($key, $value);
        }

        if (empty($clean_extraParams)) {
            $this->extraParamsString = '';
        } else {
            $this->extraParamsString = \implode("&", $clean_extraParams);
        }
    }

    public function setLanguage(string $lang)
    {
        $language = $this->trimString($lang);

        if (!\array_key_exists($language, $this->listLanguages())) {
            $this->language = $this->preference->defaultLanguage();
        }

        $this->language = $language;
    }

    /**
     * Set the pagination limit for gallery views.
     */
    public function setGallerySideLimit()
    {
        $this->setLimit($this->preference->galleryPagination());
    }

    /**
     * Sets the limit property, which controls the number of objects to be retrieved in a single
     * page view.
     *
     * @param int $limit Number of content objects to retrieve in current view.
     */
    public function setLimit(int $limit)
    {
        $this->limit = $limit >= 0 ? $limit : 0;
    }

    /**
     * Set the pagination limit for search views.
     */
    public function setSearchSideLimit()
    {
        $this->setLimit($this->preference->searchPagination());
    }

    /**
     * Set the starting position in the set of available object.
     *
     * @param int $start ID of first object to view in the set of available records.
     */
    public function setStart(int $start)
    {
        $this->start = $start >= 0 ? $start : 0;
    }

    /**
     * Set the ID of a tag used to filter content.
     *
     * @param int $tag ID of tag used to filter content.
     */
    public function setTag(int $tag)
    {
        $this->tag = $tag >= 0 ? $tag : 0;
    }

    /**
     * Set the base URL for pagination control links.
     *
     * @param string $path Base file name for constructing URLs, without the extension.
     */
    public function setUrl(string $path)
    {
        $url = $this->trimString($path);
        $testUrl = TFISH_URL . \ltrim($url, '/\\');

        $this->url = ($this->isUrl($testUrl)) ? $testUrl : TFISH_URL;
    }

    /**
     * Set the pagination limit for user-side views (other than search or gallery).
     */
    public function setUserSideLimit()
    {
        $this->setLimit($this->preference->userPagination());
    }
}
