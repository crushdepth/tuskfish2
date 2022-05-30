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
 * @uses        trait \Tfish\Traits\Content\ContentTypes	Provides definition of permitted content object types.
 * @uses        trait \Tfish\Traits\Listable Provides a standard implementation of the \Tfish\View\Listable interface.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\ValidateToken Provides CSRF check functionality.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         string $contentTitle Name of content object to display in confirm delete request.
 * @var         array $contentList An array of content objects to be displayed in this page view.
 * @var         int $contentCount The number of content objects that match filtering criteria. Used to build pagination control.
 * @var         int $id ID of a single content object to be displayed.
 * @var         int $start Position in result set to retrieve objects from.
 * @var         int $tag Filter search results by tag ID.
 * @var         string $type Filter search results by content type.
 * @var         int $onlineStatus Filter search results by online (1) or offline (0) status.
 * @var         string $backUrl $backUrl URL to return to if the user cancels the action.
 * @var         string $response Message to display to the user after processing action (success/failure).
 */

class Admin implements \Tfish\ViewModel\Listable
{
    use \Tfish\Traits\Listable;
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;

    private $model;
    private $preference;
    private $contentTitle = '';
    private $contentList = [];
    private $contentCount = 0;
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
        \header('Location: ' . TFISH_ADMIN_URL);
        exit;
    }

    /**
     * Display delete confirmation form.
     */
    public function displayConfirmDelete()
    {
        $this->pageTitle = TFISH_CONFIRM;
        $this->template = 'confirmDelete';
        $this->action = 'confirm';
        $this->backUrl = TFISH_ADMIN_USERS_URL;
    }

    /**
     * Delete content object and display result.
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
        $this->backUrl = TFISH_ADMIN_URL;
    }

    /**
     * Display the admin summary table.
     *
     * Table a list of content and links to view, edit and delete items.
     */
    public function displayTable()
    {
        $this->pageTitle = TFISH_USERS;
        $this->listContent();
        $this->countContent();
        $this->template = 'userTable';
    }

    /**
     * Toggle a content object online or offline.
     */
    public function displayToggle()
    {
        $this->model->toggleOnlineStatus($this->id);
        $this->template = 'userTable';
        header('Location: ' . TFISH_ADMIN_USERS_URL);
    }

    /** output */

    /**
     * Count content objects meeting filter criteria.
     */
    public function countContent()
    {
        $this->contentCount = $this->model->getCount(
            [
                'id' => $this->id,
                'onlineStatus' => $this->onlineStatus
            ]
        );
    }

    /**
     * Get content objects matching cached filter criteria.
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
     * Return content count.
     *
     * @return  int Number of content objects that match filtering criteria.
     */
    public function contentCount(): int
    {
        return $this->contentCount;
    }

    /**
     * Return content list.
     *
     * @return  array Array of content objects.
     */
    public function contentList(): array
    {
        return $this->contentList;
    }

    /**
     * Return ID.
     *
     * @return  int ID of content object.
     */
    public function id(): int
    {
        return $this->id;
    }

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
     * Return content title.
     */
    public function contentTitle(): string
    {
        return $this->contentTitle;
    }

    /**
     * Set title of content object.
     */
    public function setContentTitle()
    {
        $this->contentTitle = $this->model->getTitle($this->id);
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
    public function limit(): int { return 0; }
    public function start(): int { return 0; }
    public function tag(): int { return 0;}
    public function extraParams(): array { return []; }
    public function metadata(): array { return []; }
    public function setMetadata(array $metadata) {}
}
