<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModelModel\Listable class file.
 * 
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Interface that ensures compliance with \Tfish\View\Listing.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

interface Listable
{
    /* Sorting support. */

    /**
     * Set primary field to sort query results by.
     * 
     * @param   string $field Name of field.
     */
    public function setSort(string $field);

    /**
     * Set primary sort sort.
     * 
     * @param   string $sort Sort (ASC or DESC).
     */
    public function setOrder(string $sort);

    /**
     * Set secondary field to sort query results by.
     * 
     * @param   string $field Name of field.
     */
    public function setSecondarySort(string $field);

    /**
     * Set secondary sort sort.
     * 
     * @param   string $secondarySort Sort (ASC or DESC).
     */
    public function setSecondaryOrder(string $secondarySort);
    
    /* Pagination support. */

    /**
     * Count results matching query conditions.
     * 
     * @return  int
     */
    public function contentCount(): int;

    /**
     * Return the query limit (number of results to retrieve).
     * 
     * @return  int
     */
    public function limit(): int;

    /**
     * Return the query offset (starting position to retrieve results from result set).
     * 
     * @return  int
     */
    public function start(): int;

    /**
     * Return tag filter.
     * 
     * The ID of the tag being used to filter the result set, if any.
     * 
     * @return  int
     */
    public function tag(): int;

    /**
     * Returns extra parameters used to filter queries.
     * 
     * Also used to build pagination controls.
     */
    public function extraParams(): array;

    /* View support. */
    
    /**
     * Return the page title.
     * 
     * @return  string
     */
    public function pageTitle(): string;

    /**
     * Set the page title. 
     * 
     * @param   string $pageTitle Title of the page.
     */
    public function setPageTitle(string $pageTitle);

    /**
     * Return the template name for this page view.
     * 
     * @return  \Tfish\Entity\Template
     */
    public function template(): \Tfish\Entity\Template;

    /**
     * Set the template for this page view.
     * 
     * @param   string $template Name of HTML template, without the file extension, eg. 'article'.
     */
    public function setTemplate(string $template);

    /**
     * Return the layout file name for this page view.
     * 
     * @return  string $template Name of alternative HTML layout file, without the extension.
     */
    public function layout(): string;

    /**
     * Set an alternative layout file for this page view.
     * 
     * @param   string $layout Name of HTML template, without the file extension, eg. 'layout_splash'.
     */
    public function setLayout(string $layout);

    /**
     * Return the active theme for this page view.
     * 
     * @return  string
     */
    public function theme(): string;

    /**
     * Set the theme for this page view.
     * 
     * @param   string $theme Name of the theme directory.
     */
    public function setTheme(string $theme);

    /**
     * Return metadata overrides for this page view.
     * 
     * @return  array
     */
    public function metadata(): array;

    /**
     * Set metadata overrides for this page view.
     * 
     * @param   array $metadata Overriden metadata properties.
     */
    public function setMetadata(array $metadata);
}
