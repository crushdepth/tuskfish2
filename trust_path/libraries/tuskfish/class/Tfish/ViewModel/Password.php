<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModel\Password class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * ViewModel for changing the administrative password.
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
 * @var         string $confirm Confirmation message to display to the user (do they want to proceed).
 * @var         string $backUrl URL to return to if the user cancels the action.
 * @var         string $response Message to display to the user after processing action (success/failure).
 */

class Password implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;
    use \Tfish\Traits\Viewable;

    private $model;
    private $preference;
    private $password = '';
    private $confirm = '';
    private $backUrl = '';
    private $response = '';

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     */
    public function __construct($model)
    {
        $this->pageTitle = TFISH_CHANGE_PASSWORD;
        $this->model = $model;
        $this->theme = 'admin';

        $this->setMetadata([
            'description' => TFISH_CHANGE_PASSWORD_EXPLANATION,
            'robots' => 'noindex,nofollow'
            ]);
    }

    /** Actions */

    /**
     * Display the change password form.
     */
    public function displayForm()
    {
        $this->template = 'changePassword';
    }

    /**
     * Display change password confirmation message (success or failure).
     */
    public function displaySetPassword()
    {
        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        if ($this->model->changePassword($this->password, $this->confirm)) {
            $this->response = TFISH_PASSWORD_CHANGED_SUCCESSFULLY;
        } else {
            $this->response =  TFISH_PASSWORD_CHANGE_FAILED;
        }

        $this->template = 'response';
        $this->backUrl = TFISH_ADMIN_URL;
    }

    /* Getters and setters. */

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
     * Set password confirmation.
     *
     * @param   string $confirm Second entry of the new password to validate first was correct.
     */
    public function setConfirm(string $confirm)
    {
        $this->confirm = $confirm;
    }

    /**
     * Set password.
     *
     * @param   string $password The new password.
     */
    public function setPassword(string $password)
    {
        $this->password = $password;
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
