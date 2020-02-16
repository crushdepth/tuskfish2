<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModelModel\Login class file.
 * 
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for logging in.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\View\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 * @var         string $theme Name of the theme used to display this page.
 * @var         string $pageTitle Title of this page.
 * @var         array $metadata Overrides of site metadata properties, to customise it for this page.
 */

class Login implements Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;
    
    private $model;

    /**
     * Constructor
     * 
     * @param   object $model Instance of a model class.
     * @param   string $pageTitle Title of this page.
     * @param   array $metadata Page-specific metadata overrides.
     */
    public function __construct($model)
    {
        $this->pageTitle = TFISH_LOGIN;
        $this->model = $model;
        $this->template = 'login';
        $this->theme = 'signin';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions */

    /**
     * Display the login form.
     */
    public function displayForm() {}
}
