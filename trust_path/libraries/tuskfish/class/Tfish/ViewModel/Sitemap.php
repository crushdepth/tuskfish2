<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModel\Sitemap class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for generating a sitemap.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\ValidateToken Provides CSRF check functionality.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\Interface\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 */
class Sitemap implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;
    use \Tfish\Traits\Viewable;

    private object $model;
    private string $response = '';
    private string $action = '';
    private string $backUrl = '';

    /**
     * Constructor
     *
     * @param   object $model Instance of a model class.
     */
    public function __construct(object $model)
    {
        $this->model = $model;
        $this->pageTitle = TFISH_UPDATE_SITEMAP;
        $this->theme = 'admin';
        $this->template = 'sitemap';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions. */

    /**
     * Display sitemap update confirmation page.
     */
    public function displayConfirm(): void
    {
        $this->pageTitle = TFISH_CONFIRM;
        $this->template = 'confirmSitemap';
        $this->action = 'confirm';
        $this->backUrl = TFISH_ADMIN_URL;
    }

    /**
     * Display sitemap update result page (success or failure).
     */
    public function displayGenerate(): void
    {
        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        if ($this->model->generate()) {
            $this->pageTitle = TFISH_SUCCESS;
            $this->response = TFISH_SITEMAP_UPDATED;
        } else {
            $this->pageTitle = TFISH_FAILED;
            $this->response = TFISH_SITEMAP_UPDATE_FAILED;
        }

        $this->template = 'response';
        $this->action = 'generate';
        $this->backUrl = TFISH_ADMIN_URL;
    }

    /**
     * Display cancel page for sitemap update operation.
     */
    public function displayCancel(): void
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
}
