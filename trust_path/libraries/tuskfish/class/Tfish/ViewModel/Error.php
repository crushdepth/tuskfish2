<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModelModel\Error class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for displaying error messages.
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
 */
class Error implements Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private $model;

    /**
     * Constructor
     *
     * @param   object $model Instance of a model class.
     */
    public function __construct($model)
    {
        $this->model = $model;
        $this->pageTitle = TFISH_ERROR;
        $this->theme = 'default';
        $this->template = 'error';
        $this->setMetadata(['robots' => 'index,follow']);
    }

    /** Actions. */

    /**
     * Display error message.
     */
    public function displayError()
    {
        return TFISH_ERROR_SORRY_PAGE_DOES_NOT_EXIST;
    }
}
