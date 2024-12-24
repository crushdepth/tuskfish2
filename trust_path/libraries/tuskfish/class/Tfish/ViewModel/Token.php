<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModelModel\Token class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * ViewModel for displaying token errors.
 *
 * Token checks are used to validate that a form submission was a deliberate action from the user, and not
 * a forgery that was sent while the user was logged in.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\Interface\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 * @var         string $theme Name of the theme used to display this page.
 * @var         string $template Name of the HTML template used to display this page (without the file extension).
 */

class Token implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private $model;

    /**
     * Constructor
     *
     * @param   object $model Instance of a model class.
     * @param   string $theme Name of the theme to use on this page.
     */
    public function __construct($model)
    {
        $this->pageTitle = TFISH_INVALID_TOKEN;
        $this->model = $model;
        $this->theme = 'default';
        $this->template = 'error';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions. */

    /**
     * Display error message.
     */
    public function displayError()
    {
        return TFISH_SORRY_INVALID_TOKEN;
    }

    public function fetchBlocks(string $path): array { return []; }
}
