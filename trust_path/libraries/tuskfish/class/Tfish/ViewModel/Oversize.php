<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModel\Oversize class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * ViewModel for displaying oversized request errors.
 *
 * A form submission that exceeds the post_max_size directive is discarded by PHP before any script
 * runs: $_POST and $_FILES arrive empty, so the request cannot be processed and cannot even be
 * authenticated, as the CSRF token was discarded with everything else. The front controller detects
 * this and redirects here, so that the user is told what happened instead of being returned to an
 * apparently unchanged form.
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

class Oversize implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private object $model;

    /**
     * Constructor
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     */
    public function __construct(object $model, \Tfish\Entity\Preference $preference)
    {
        $this->pageTitle = TFISH_REQUEST_TOO_LARGE;
        $this->model = $model;
        $this->theme = $preference->defaultTheme();
        $this->template = 'error';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions. */

    /**
     * Display error message.
     */
    public function displayError(): string
    {
        if (!\headers_sent()) {
            \http_response_code(413);
            \header('X-Robots-Tag: noindex, nofollow');
        }

        return TFISH_SORRY_REQUEST_TOO_LARGE;
    }
}
