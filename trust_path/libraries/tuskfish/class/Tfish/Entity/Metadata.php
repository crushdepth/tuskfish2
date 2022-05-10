<?php

declare(strict_types=1);

namespace Tfish\Entity;

/**
 * \Tfish\Entity\Metadata class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     core
 */

/**
 * Provides site-level metadata that can be overriden by a particular page.
 *
 * Metadata is a subset of the preference values. Eventually it would be best to split them.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         string $siteName Name of this website.
 * @var         string $title Meta title of this website.
 * @var         string $description Meta description of this website.
 * @var         string $author Author of this website.
 * @var         string $copyright Copyright notice.
 * @var         string $seo SEO optimisation string to append to page URL.
 * @var         string $robots Meta instructions to robots.
 * @var         string $canonicalUrl The canonical URL for this page.
 * @var         string $language Default language preference.
 */
class Metadata
{
    use \Tfish\Traits\ValidateString;

    public $siteName = '';
    public $title = '';
    public $description = '';
    public $author = '';
    public $copyright = '';
    public $seo = '';
    public $robots = '';
    public $canonicalUrl = '';
    public $language = '';
    /**
     * Constructor.
     *
     * @param Preference $preference Instance of Preference, holding site preferences.
     */
    function __construct(Preference $preference)
    {
        $this->setSiteName($preference->siteName());
        $this->setTitle($preference->siteName()); // Can be overridden on any page, this is just the default.
        $this->setDescription($preference->siteDescription());
        $this->setAuthor($preference->siteAuthor());
        $this->setCopyright($preference->siteCopyright());
        $this->setSeo('');
        $this->setRobots('index,follow');
        $this->setLanguage($preference->defaultLanguage());
    }

    /**
     * Sets the site title property.
     *
     * @param string $value Site name.
     */
    public function setSiteName(string $value)
    {
        $this->siteName = $this->trimString($value);
    }

    /**
     * Sets the page meta title property.
     *
     * @param string $value Page title.
     */
    public function setTitle(string $value)
    {
        $this->title = $this->trimString($value);
    }

    /**
     * Sets the meta description property.
     *
     * @param string $value Page description.
     */
    public function setDescription(string $value)
    {
        $this->description = $this->trimString($value);
    }

    /**
     * Sets the page meta author property.
     *
     * @param string $value Page author.
     */
    public function setAuthor(string $value)
    {
        $this->author = $this->trimString($value);
    }

    /**
     * Sets the page meta copyright property.
     *
     * @param string $value Page copyright.
     */
    public function setCopyright(string $value)
    {
        $this->copyright = $this->trimString($value);
    }

    /**
     * Sets the SEO-friendly URL string for this page.
     *
     * @param string $value SEO string.
     */
    public function setSeo(string $value)
    {
        $this->seo = $this->trimString($value);
    }

    /**
     * Sets the meta robots directive for this page.
     *
     * @param string $value Robots directive.
     */
    public function setRobots(string $value)
    {
        $this->robots = $this->trimString($value);
    }

    /**
     * Set query string parameters for the canonical URL tag in theme.html files.
     *
     * Do not pass in the base URL (domain) of the site, only the query string.
     *
     * @param string $value Query string parameters for canonical URL of relevant page.
     */
    public function setCanonicalUrl(string $value)
    {
        $this->canonicalUrl = $this->trimString($value);
    }

    /**
     * Set the language meta tag for this page.
     *
     * @param   string $language 2-letter ISO language code.
     */
    public function setLanguage(string $language)
    {
        $this->language = $this->trimString($language);
    }

    /**
     * Update metadata properties to page-specific values.
     *
     * @param   array $override Array of key-value pairs of properties that should be updated.
     */
    public function update(array $override)
    {
        foreach ($override as $key => $value) {
            $this->{'set' . $key}($value);
        }
    }
}
