<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModelModel\Cache class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * ViewModel for handling cache operations.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\ValidateToken Provides CSRF check functionality.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\Interface\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 * @var         string $action Name of the action that should be embedded in the form, which will execute on submission.
 * @var         string $backUrl URL to return to if the user cancels the action.
 * @var         string $response Message to display to the user after processing action (success/failure).
 */

class Cache implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;
    use \Tfish\Traits\Viewable;

    private $model;
    private $action = '';
    private $backUrl = '';
    private $response = '';

    /**
     * Constructor
     *
     * @param   object $model Instance of a model class.
     */
    public function __construct($model)
    {
        $this->model = $model;
        $this->theme = 'admin';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions */

    /**
     * Display flush cache confirmation page.
     */
    public function displayConfirm()
    {
        $this->pageTitle = TFISH_CONFIRM;
        $this->template = 'confirmFlush';
        $this->action = 'confirm';
        $this->backUrl = TFISH_ADMIN_URL;
    }

    /**
     * Display flush cache result page (success or failure).
     */
    public function displayFlush()
    {
        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        if ($this->model->flush()) {
            $this->pageTitle = TFISH_SUCCESS;
            $this->response = TFISH_CACHE_WAS_FLUSHED;
        } else {
            $this->pageTitle = TFISH_FAILED;
            $this->response = TFISH_CACHE_FLUSH_FAILED;
        }

        $this->template ='response';
        $this->action = 'flush';
        $this->backUrl = TFISH_ADMIN_URL;
    }

    /**
     * Display cancel page for flush cache operation.
     */
    public function displayCancel()
    {
        \header('Location: ' . TFISH_ADMIN_URL);
        exit;
    }

    /* Getters and setters. */

    /**
     * Return the action for this page.
     *
     * The action is usually embedded in the form, to control handling on submission (next page load).
     *
     * @return string
     */
    public function action(): string
    {
        return $this->action;
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

    /**
     * Return the response message (success or failure) for an action.
     *
     * @return  string
     */
    public function response(): string
    {
        return $this->response;
    }

    public function fetchBlocks(string $path): array { return []; }
}
