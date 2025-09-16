<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModel\PreferenceList class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * ViewModel for displaying a list of site preferences.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\Language	Returns a list of languages in use by the system.
 * @uses        trait \Tfish\Traits\Timezones	Provides an array of time zones.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\Interface\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 * @var         string $theme Name of the theme used to display this page.
 * @var         string $pageTitle Title of this page.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preference class.
 * @var         array $metadata Overrides of site metadata properties, to customise it for this page.
 */

class PreferenceList implements \Tfish\Interface\Viewable
{
    Use \Tfish\Traits\Language;
    Use \Tfish\Traits\Timezones;
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private $model;
    private $preference;

    /**
     * Constructor
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tfish site preference class.
     */
    public function __construct($model, \Tfish\Entity\Preference $preference)
    {
        $this->pageTitle = TFISH_PREFERENCES;
        $this->model = $model;
        $this->preference = $preference;
        $this->template = 'preferenceTable';
        $this->theme = 'admin';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions */

    /**
     * Display the table of preference values.
     */
    public function displayForm() {}

    /**
     * Return an instance of the site preferences class.
     *
     * @return  \Tfish\Entity\Preference
     */
    public function preference(): \Tfish\Entity\Preference
    {
        return $this->preference;
    }
}
