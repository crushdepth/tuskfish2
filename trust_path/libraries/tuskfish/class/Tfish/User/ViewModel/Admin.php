<?php

declare(strict_types=1);

namespace Tfish\User\ViewModel;

/**
 * \Tfish\User\ViewModel\Admin class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 */

/**
 * ViewModel for admin interface operations.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 * @uses        trait \Tfish\Traits\Listable Provides a standard implementation of the \Tfish\View\Listable interface.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\ValidateToken Provides CSRF check functionality.
 * @uses        trait \Tfish\User\Traits\UserGroup Whitelist of permitted user groups on system.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         string $userEmail The email address of this user.
 * @var         array $contentList An array of user objects to be displayed in this page view.
 * @var         int $id ID of a single user object to be displayed.
 * @var         int $onlineStatus Filter search results by online (1) or offline (0) status.
 * @var         string $action Action to be embedded in the form and executed after next submission.
 * @var         string $backUrl $backUrl URL to return to if the user cancels the action.
 * @var         string $response Message to display to the user after processing action (success/failure).
 */

class Admin implements \Tfish\ViewModel\Listable
{
    use \Tfish\Traits\Listable;
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;
    use \Tfish\User\Traits\UserGroup;

    private $model;
    private $preference;
    private $userEmail = '';
    private $contentList = [];
    private $id = 0;
    private $onlineStatus = 0;
    private $action = '';
    private $backUrl = '';
    private $response = '';

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
        $this->sort = 'email';
        $this->order = 'ASC';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions */

    /**
     * Cancel action and redirect to admin page.
     */
    public function displayCancel()
    {
        \header('Location: ' . TFISH_ADMIN_USERS_URL);
        exit;
    }

    /**
     * Display delete confirmation form.
     */
    public function displayConfirmDelete()
    {
        $this->pageTitle = TFISH_CONFIRM;
        $this->template = 'confirmDeleteUser';
        $this->action = 'confirm';
        $this->backUrl = TFISH_ADMIN_USERS_URL;
    }

    /**
     * Delete user object and display result.
     */
    public function displayDelete()
    {
        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        if ($this->model->delete($this->id)) {
            $this->pageTitle = TFISH_SUCCESS;
            $this->response = TFISH_OBJECT_WAS_DELETED;
        } else {
            $this->pageTitle = TFISH_FAILED;
            $this->response = TFISH_OBJECT_DELETION_FAILED;
        }

        $this->template ='response';
        $this->backUrl = TFISH_ADMIN_USERS_URL;
    }

    /**
     * Display the admin summary table.
     *
     * Table a list of users and links to view, edit and delete items.
     */
    public function displayTable()
    {
        $this->pageTitle = TFISH_USERS;
        $this->listContent();
        $this->template = 'userTable';
    }

    /**
     * Toggle a user object online or offline.
     */
    public function displayToggle()
    {
        $this->model->toggleOnlineStatus($this->id);
        $this->template = 'userTable';
        header('Location: ' . TFISH_ADMIN_USERS_URL);
    }

    /** output */

    /**
     * Get user objects matching cached filter criteria.
     *
     * Result is cached as $contentList property.
     */
    public function listContent()
    {
        $this->contentList = $this->model->getObjects(
            [
                'id' => $this->id,
                'sort' => $this->sort,
                'order' => $this->order,
            ]
        );
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
     * Return user list.
     *
     * @return  array Array of user objects.
     */
    public function contentList(): array
    {
        return $this->contentList;
    }

    /**
     * Return ID.
     *
     * @return  int ID of user object.
     */
    public function id(): int
    {
        return (int) $this->id;
    }

    /**
     * Set ID.
     *
     * @param   int $id ID of user object.
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * Return user email.
     */
    public function userEmail(): string
    {
        return $this->userEmail;
    }

    /**
     * Set email of user object.
     */
    public function setUserEmail()
    {
        $this->userEmail = $this->model->getEmail($this->id);
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

    /** Unused but required for compliance with Listable interface. **/
    public function contentCount(): int { return 0; }
    public function limit(): int { return 0; }
    public function start(): int { return 0; }
    public function tag(): int { return 0;}
    public function extraParams(): array { return []; }
    public function metadata(): array { return []; }
    public function setMetadata(array $metadata) {}
}
