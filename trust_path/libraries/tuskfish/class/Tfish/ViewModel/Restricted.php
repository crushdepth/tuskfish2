<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModel\Restricted class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for displaying restricted page notice.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\Interface\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         string $theme Name of the theme used to display this page.
 * @var         array $metadata Overrides of site metadata properties, to customise it for this page.
 */

class Restricted implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private $model;
    private $preference;

    /**
     * Constructor
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     * @param   array $metadata Page-specific metadata overrides.
     */
    public function __construct($model, \Tfish\Entity\Preference $preference)
    {
        $this->pageTitle = FISH_RESTRICTED_ACCESS;
        $this->model = $model;
        $this->template = 'restricted';
        $this->theme = $preference->defaultTheme();
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions */

    /**
     * Display the login form.
     */
    public function displayForm() {}

    /** Utilities */

    /**
     * Set title for redirect page.
     * 
     * @return string|null Title of page.
     */
    public function redirectTitle(): ?string
    {
        return $this->model->redirectTitle();
    }

    /**
     * Set context message for redirect page.
     * 
     * @return string|null Context message.
     */
    public function redirectMessage(): ?string
    {
        return $this->model->redirectMessage();
    }
}
