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
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\View\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 * @var         int $id ID of the content object whose media attachment will be streamed.
 */

class Enclosure implements \Tfish\ViewModel\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private $model;
    private $id = 0;

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     */
    public function __construct($model)
    {
        $this->model = $model;
        $this->theme = 'default';
        $this->setMetadata(['robots' => 'index,follow']);
    }

    /** Actions. */

    /**
     * Stream a file (media attachment) to browser.
     */
    public function streamFile()
    {
        return $this->model->streamFileToBrowser($this->id);
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
}
