<?php

namespace Tfish\Traits;

/**
 * \Tfish\Traits\Viewable trait file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Provides standard implementation for the Viewable interface.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @var         string $pageTitle Title of this page, for display.
 * @var         string $template Name of template file to display this page (alphanumeric and underscore characters, only).
 * @var         string $layout Name of layout file to display this page (alphanumeric and underscore characters, only).
 * @var         string $theme Name of the theme (directory) for this page.
 * @var         array $metadata Meta tags to be overridden with custom values.
 */
trait Viewable
{
    private string $pageTitle = '';
    private string $template = '';
    private string $layout = 'layout';
    private string $theme = '';
    private array $metadata = [];

    /**
     * Return title of this page.
     *
     * @return  string
     */
    public function pageTitle(): string
    {
        return $this->pageTitle;
    }

    /**
     * Set the title of this page.
     *
     * @param   string $pageTitle Title of this page.
     */
    public function setPageTitle(string $pageTitle)
    {
        $this->pageTitle = $this->trimString($pageTitle);
    }

    /**
     * Return the template object required by this page.
     *
     * @return  \Tfish\Entity\Template
     */
    public function template(): \Tfish\Entity\Template
    {
        return new \Tfish\Entity\Template($this->template, $this->theme);
    }

    /**
     * Set the template used by this page.
     *
     * @param   string $template Name of template without extension (alphanumeric and underscore characters only).
     */
    public function setTemplate(string $template)
    {
        $template = $this->trimString($template);

        if (!$this->isAlnumUnderscore($template)) {
            \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
            exit;
        }

        $this->template = $template;
    }

    /**
     * Return the layout file name for this page view, without the extension.
     *
     * @return  string
     */
    public function layout(): string
    {
        return $this->layout;
    }

    /**
     * Set an alternative layout file for this page view.
     *
     * @param   string $layout Name of HTML template, without the file extension, (alphanumeric and underscore characters only).
     */
    public function setLayout(string $layout)
    {
        $layout = $this->trimString($layout);

        if (!$this->isAlnumUnderscore($layout)) {
            \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
            exit;
        }

        $this->layout = $layout;
    }

    /**
     * Return the theme used by this page.
     *
     * @return  string
     */
    public function theme(): string
    {
        return $this->theme;
    }

    /**
     * Set (change) the theme.
     *
     * You must ensure that the new theme directory contains the HTML template files that you need.
     *
     * @param   string $theme Name of theme directory (alphanumeric and underscores only).
     */
    public function setTheme(string $theme)
    {
        $theme = $this->trimString($theme);

        if (!$this->isAlnumUnderscore($theme)) {
            \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
            exit;
        }

        $this->theme = $theme;
    }

    /**
     * Return page-specific metadata overrides.
     *
     * @return  array
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set page-specific overrides of the site metadata.
     *
     * @param   array $metadata Array of metadata as key => value pairs.
     */
    public function setMetadata(array $metadata)
    {
        $this->metadata = $metadata;
    }
}
