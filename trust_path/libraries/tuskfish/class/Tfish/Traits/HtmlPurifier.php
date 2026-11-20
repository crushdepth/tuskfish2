<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\HTMLPurifier trait file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Includes HTMLPurifier library. Use sparingly as it requires significant resources.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait HtmlPurifier
{
    /**
     * Return a configured instance of HTMLPurifier.
     *
     * Cached for speed.
     *
     * @return \HtmlPurifier
     */
    public function getHtmlPurifier(): \HTMLPurifier
    {
        static $purifier = null;

        if ($purifier instanceof \HTMLPurifier) {
            return $purifier;
        }

        require_once TFISH_LIBRARIES_PATH . 'htmlpurifier/library/HTMLPurifier.auto.php';

        $config = $this->configureHTMLPurifier([]);
        $purifier = new \HTMLPurifier($config);

        return $purifier;
    }

    /**
     * Configure HTMLPurifier for use with Tuskfish.
     *
     * Sets UTF-8 encoding, allows the id attribute (needed for CSS selectors and in-page anchor
     * links) with HTML5 id syntax, and forbids the name attribute on img (see F-24).
     * HTMLPurifier itself only supports HTML 4 / XHTML 1.0 elements, not HTML5 ones.
     *
     * By default HTMLPurifier removes id attributes. They are allowed here without a prefix, so
     * an editor can give an element the same id as one used by the theme. Lookups by id return
     * the first match and theme elements come before content, except the map container on
     * track pages (see F-24).
     *
     * @param array $configOptions Extra HTMLPurifier settings (key => value). Currently always [].
     * @return \HTMLPurifier_Config HTMLPurifier configuration object.
     */
    private function configureHTMLPurifier(array $configOptions): \HTMLPurifier_Config
    {
        // Set default configuration options.
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Attr.EnableID', true);
        $config->set('Attr.ID.HTML5', true);
        // <img name> becomes a named property of document and can shadow built-ins (F-24).
        $config->set('HTML.ForbiddenAttributes', ['img@name']);

        // Set optional configuration options.
        if ($configOptions) {
            foreach ($configOptions as $key => $value) {
                $config->set($key, $value);
            }
        }

        return $config;
    }
}
