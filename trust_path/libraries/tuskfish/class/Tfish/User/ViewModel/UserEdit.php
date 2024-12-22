<?php

declare(strict_types=1);

namespace Tfish\User\ViewModel;

/**
 * \Tfish\User\ViewModel\UserEdit class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 */

/**
 * ViewModel for editing user objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\ValidateToken Provides CSRF check functionality.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\Interface\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         int $id ID of a single user object to be displayed.
 * @var         \Tfish\User\Entity\User $content User object to be edited.
 * @var         string $action Action to be embedded in the form and executed after next submission.
 * @var         string $response Message to display to the user after processing action (success/failure).
 * @var         string $backUrl $backUrl URL to return to if the user cancels the action.
 */
class UserEdit implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;
    use \Tfish\Traits\Viewable;

    private $model;
    private $id = 0;
    private $content = '';
    private $action = '';
    private $response = '';
    private $backUrl = '';
    private $preference;

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     */
    public function __construct($model, \Tfish\Entity\Preference $preference)
    {
        $this->model = $model;
        $this->preference = $preference;
        $this->theme = 'admin';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions */

    /**
     * Display Add user form.
     */
    public function displayAdd()
    {
        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        $this->pageTitle = TFISH_USER_ADD;
        $this->content = new \Tfish\User\Entity\User;
        $this->template = 'userEntry';
    }

    /**
     * Cancel action and redirect to admin page.
     */
    public function displayCancel()
    {
        \header('Location: ' . TFISH_ADMIN_USER_URL);
        exit;
    }

    /**
     * Display edit user form.
     */
    public function displayEdit()
    {
        $id = (int) ($_GET['id'] ?? 0);

        $this->pageTitle = TFISH_EDIT_USER;
        $content = new \Tfish\User\Entity\User;

        if ($data = $this->model->edit($id)) {
            $content->load($data, false);
            $this->setContent($content);
            $this->action = 'update';
            $this->template = 'userEdit';
        } else {
            $this->pageTitle = TFISH_FAILED;
            $this->response = TFISH_ERROR_NO_SUCH_OBJECT;
            $this->backUrl = TFISH_ADMIN_USER_URL;
            $this->template = 'response';
        }
    }

    /**
     * Save user object (new or updated).
     */
    public function displaySave()
    {
        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        $id = (int) ($_POST['content']['id'] ?? 0);

        if (empty($id)) {

            if ($this->model->insert()) {
                $this->pageTitle = TFISH_SUCCESS;
                $this->response = TFISH_OBJECT_WAS_INSERTED;
            } else {
                $this->pageTitle = TFISH_FAILED;
                $this->response = TFISH_OBJECT_INSERTION_FAILED;
            }
        }

        if (!empty($id)) {

            if ($this->model->update()) {
                $this->pageTitle = TFISH_SUCCESS;
                $this->response = TFISH_OBJECT_WAS_UPDATED;
            } else {
                $this->pageTitle = TFISH_FAILED;
                $this->response = TFISH_OBJECT_UPDATE_FAILED;
            }
        }

        $this->template = 'response';
        $this->backUrl = TFISH_ADMIN_USER_URL;
    }

    /** Utilities */

    /**
     * Return the site author preference.
     *
     * @return  string
     */
    public function siteAuthor(): string
    {
        return $this->preference->siteAuthor();
    }

    /** Getters and setters */

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
     * Return a user object.
     *
     * @return \Tfish\User\Entity\User
     */
    public function content(): \Tfish\User\Entity\User
    {
        return $this->content;
    }

    /**
     * Set user object.
     *
     * @param   \Tfish\User\Entity\User $content User object to be edited.
     */
    public function setContent(\Tfish\User\Entity\User $content)
    {
        $this->content = $content;
    }

    /**
     * Return ID of user object.
     *
     * @return  int ID of user object.
     */
    public function id(): int
    {
        return $this->id;
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
