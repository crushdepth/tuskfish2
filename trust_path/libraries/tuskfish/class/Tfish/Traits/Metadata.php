<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\Metadata trait file.
 *
 * Adds HTML meta properties to object.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       1.0
 * @package     core
 */

/**
 * Adds support for HTML meta properties to object.
 *
 * Note that the client object must also use \Tfish\Traits\ValidateString.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait Metadata
{
    private string $metaTitle = '';
    private string $metaDescription = '';
    private string $metaSeo = '';

    /**
     * Return meta title.
     *
     * Used to set the <title> meta tag.
     *
     * @return string
     */
    public function metaTitle(): string
    {
        return $this->metaTitle;
    }

    /**
     * Set meta title.
     *
     * @param   string $metaTitle Populates the <title> tag.
     */
    public function setMetaTitle(string $metaTitle)
    {
        $this->metaTitle = $this->trimString($metaTitle);
    }

    /**
     * Return meta description.
     *
     * Used to set the <meta name="description"> tag.
     *
     * @return string
     */
    public function metaDescription(): string
    {
        return $this->metaDescription;
    }

    /**
     * Set meta description.
     *
     * @param   string $metaDescription HTML description of this content.
     */
    public function setMetaDescription(string $metaDescription)
    {
        $this->metaDescription = $this->trimString($metaDescription);
    }

    /**
     * Return SEO-friendly string.
     *
     * This is an SEO-friendly text fragment that can be appended to URLs. It does not
     * affct URL function in any way, since Tuskfish never uses it as a parameter.
     *
     * @return string
     */
    public function metaSeo(): string
    {
        return $this->metaSeo;
    }

    /**
     * Set SEO-friendly string
     *
     * @param   string $metaSeo SEO text to append to URL.
     */
    public function setMetaSeo(string $metaSeo)
    {
        $this->metaSeo = $this->trimString($metaSeo);
    }
}