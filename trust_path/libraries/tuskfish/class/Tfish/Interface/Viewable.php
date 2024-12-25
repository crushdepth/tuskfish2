<?php

declare(strict_types=1);

namespace Tfish\Interface;

/**
 * \Tfish\Interface\Viewable class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Interface that ensures compliance with \Tfish\View\Single.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

interface Viewable
{
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
     * @return  string
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

    /** Utilities. */
}
