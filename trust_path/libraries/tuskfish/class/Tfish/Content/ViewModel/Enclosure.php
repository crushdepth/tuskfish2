<?php

declare(strict_types=1);

namespace Tfish\Content\ViewModel;

/**
 * \Tfish\Content\ViewModel\Enclosure class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for streaming file attachments (enclosures).
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
 * @var         int $id ID of the content object whose media attachment will be streamed.
 * @var         string $backUrl $backUrl URL to return to if the user cancels the action.
 */

class Enclosure implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private $model;
    private $id = 0;
    private $backUrl = '';

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     */
    public function __construct($model, \Tfish\Entity\Preference $preference)
    {
        $this->model = $model;
        $this->theme = $preference->defaultTheme();
        $this->setMetadata(['robots' => 'index,follow']);
    }

    /** Actions. */

    /**
     * Stream a file (media attachment) to browser.
     */
    public function streamFile()
    {
        if ($this->id === 0) {
            $this->pageTitle = TFISH_ERROR;
            $this->backUrl = TFISH_URL;
            $this->template = 'response';

            return;
        }

        return $this->model->streamFileToBrowser($this->id);
    }

    /** Utilities */

    /**
     * Return error message if enclosure ID not set or inappropriate.
     *
     * @return string
     */
    public function response(): string
    {
        return TFISH_ERROR_NO_SUCH_CONTENT;
    }

    /* Getters and setters. */

    /**
     * Set ID.
     *
     * @param   int $id ID of content object.
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * Return the backUrl.
     *
     * If the cancel button is clicked, the user will be redirected to the backUrl.
     *
     * @return  string
     */
    public function backUrl(): string
    {
        return $this->backUrl;
    }
}
