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
     * @return \HtmlPurifier
     */
    public function getHtmlPurifier()
    {
        require_once TFISH_LIBRARIES_PATH . 'htmlpurifier/library/HTMLPurifier.auto.php';

        $config = $this->configureHTMLPurifier([]);
        return new \HTMLPurifier($config);
    }

    /**
     * Configure HTMLPurifier for use with Tuskfish.
     * 
     * Tuskfish requires HTMLPurifier to use UTF-8 encoding; to allow the ID attribute in HTML,
     * which is required to provide CSS selector targets; and support for HTML5 tags.
     * 
     * By default HTMLPurifier removes ID attributes from HTML markup, as duplicate IDs render
     * markup technically invalid. However, it is widely known that IDs are supposed to be unique
     * and not an issue if you are doing things properly. Removing IDs breaks CSS that uses IDs as 
     * selectors, which *is* an issue. 
     * 
     * @param array $configOptions HTMLPurifier configuration options (see HTMLPurifier documentation).
     * @return object HTMLPurifier configuration object.
     */
    private function configureHTMLPurifier(array $configOptions)
    {
        // Set default configuration options.
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Attr.EnableID', true);
        $config->set('Attr.ID.HTML5', true);

        // Set optional configuration options.
        if ($configOptions) {
            foreach ($configOptions as $key => $value) {
                $config->set($key, $value);
            }
        }
        
        return $config;
    }
}
