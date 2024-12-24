<?php

declare(strict_types=1);

namespace Tfish\Content\ViewModel;

/**
 * \Tfish\Content\ViewModel\Admin class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for admin interface operations.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
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
 * @var         int $status The online status of a single content item being toggled on or offline.
 * @var         int $start Position in result set to retrieve objects from.
 * @var         int $tag Filter search results by tag ID.
 * @var         string $type Filter search results by content type.
 * @var         int $onlineStatus Filter search results by online (1) or offline (0) status.
 * @var         string $backUrl $backUrl URL to return to if the user cancels the action.
 * @var         string $response Message to display to the user after processing action (success/failure).
 */

class Admin implements \Tfish\Interface\Listable
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\FetchBlock;
    use \Tfish\Traits\Listable;
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;

    private $model;
    private $preference;
    private $contentTitle = '';
    private $contentList = [];
    private $contentCount = 0;
    private $id = 0;
    private $status = 0;
    private $start = 0;
    private $tag = 0;
    private $type = '';
    private $onlineStatus = 2;
    private $action = '';
    private $backUrl = '';
    private $response = '';

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     * @param   \Tfish\BlockFactory $blockFactory
     */
    public function __construct($model, \Tfish\Entity\Preference $preference, \Tfish\BlockFactory $blockFactory)
    {
        $this->model = $model;
        $this->preference = $preference;
        $this->theme = 'admin';
        $this->sort = 'date';
        $this->order = 'DESC';
        $this->secondarySort = 'submissionTime';
        $this->secondaryOrder = 'DESC';
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
        $this->backUrl = TFISH_ADMIN_URL;
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
        $this->pageTitle = TFISH_ADMIN;
        $this->listContent();
        $this->countContent();
        $this->template = 'contentTable';
    }

    /**
     * Toggle a content object online or offline using htmx.
     */
    public function displayToggle(): string
    {
        $this->model->toggleOnlineStatus($this->id);

        if ($this->status === 1) {
            $this->status = 0;
            echo '<a class="text-danger" hx-post="' . TFISH_ADMIN_URL . '?action=toggle"'
            . ' target="closest td" hx-vals=\'{"id": "' . $this->id . '", "status": "0"}\' '
            . 'hx-swap="outerHTML"><i class="fas fa-times"></i></a>';
        } else {
            $this->status = 1;
            echo '<a class="text-success" hx-post="' . TFISH_ADMIN_URL . '?action=toggle"'
              . ' target="closest td" hx-vals=\'{"id": "' . $this->id . '", "status": "1"}\' '
              . 'hx-swap="outerHTML"><i class="fas fa-check"></i></a>';
        }
        exit; // Prevents proceeding to full page reload.
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
                'start' => $this->start,
                'tag' => $this->tag,
                'type' => $this->type,
                'onlineStatus' => $this->onlineStatus
            ]
        );
    }

    /**
     * Returns the template for formatting the date from preferences.
     */
    public function dateFormat(): string
    {
        return $this->preference->dateFormat();
    }

    /**
     * Return extra parameters to be included in pagination control links.
     *
     * @return  array
     */
    public function extraParams(): array
    {
        $extraParams = [];

        if (!empty($this->tag)) $extraParams['tag'] = $this->tag;
        if (!empty($this->type)) $extraParams['type'] = $this->type;
        if (isset($this->onlineStatus) && $this->onlineStatus == 0 || $this->onlineStatus == 1)
            $extraParams['onlineStatus'] = $this->onlineStatus;

        return $extraParams;
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
                'start' => $this->start,
                'tag' => $this->tag,
                'type' => $this->type,
                'onlineStatus' => $this->onlineStatus,
                'sort' => $this->sort,
                'order' => $this->order,
                'secondarySort' => $this->secondarySort,
                'secondaryOrder' => $this->secondaryOrder
            ]
        );
    }

    /* Utilities. */

    /**
     * Return admin-side pagination limit.
     *
     * @return  int Number of items to display on admin-side pages.
     */
    public function limit(): int
    {
        return $this->preference->adminPagination();
    }

    /**
     * Return options for tag select box control.
     *
     * @param   string $zeroOption Text to display as default select box option.
     * @return  array IDs and titles as key-value pairs.
     */
    public function tagOptions($zeroOption = TFISH_SELECT_TAGS)
    {
        $zeroOption = $this->trimString($zeroOption);
        $params = ['type' => 'TfTag'];
        $columns = ['id', 'title'];

        $rows = $this->model->getOptions($params, $columns);

        $options = [];

        foreach ($rows as $row) {
            $options[$row['id']] = $row['title'];
        }

        \asort($options);

        return [$zeroOption] + $options;
    }

    /**
     * Return options for type select box control.
     *
     * @param   string $zeroOption Text to display as default select box option.
     * @return  array IDs and content types as key-value pairs.
     */
    public function typeOptions($zeroOption = TFISH_SELECT_TYPE)
    {
        $zeroOption = $this->trimString($zeroOption);

        return [$zeroOption] + $this->listTypes();
    }

    /**
     * Return options for tag online status select box control.
     *
     * @param   string $defaultOption Text to display as default select box option.
     * @return  array Online (1), offline (0) or both (2).
     */
    public function statusOptions($defaultOption = TFISH_SELECT_STATUS)
    {
        $defaultOption = $this->trimString($defaultOption);

        return [2 => $defaultOption, 1 => TFISH_ONLINE, 0 => TFISH_OFFLINE];
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

    public function status(): int
    {
        return (int) $this->status;
    }

    public function setStatus(int $status)
    {
        $this->status = $status;
    }

    /**
     * Return online status.
     *
     * @return  int Online (1) or offline (0).
     */
    public function onlineStatus(): int
    {
        return $this->onlineStatus;
    }

    /**
     * Set online status.
     *
     * @param   int $onlineStatus Online (1) or offline (0).
     */
    public function setOnlineStatus(int $onlineStatus)
    {
        $this->onlineStatus = $onlineStatus;
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

    /**
     * Return start.
     *
     * @return int ID of first object to view in the set of available records.
     */
    public function start(): int
    {
        return $this->start;
    }

    /**
     * Set start ID.
     *
     * First record to return from result set.
     *
     * @param int $start ID of first object to return in the set of available records.
     */
    public function setStart(int $start)
    {
        $this->start = $start;
    }

    /**
     * Return ID of tag filter.
     *
     * @return  int
     */
    public function tag(): int
    {
        return $this->tag;
    }

    /**
     * Set tag ID.
     *
     * @param   int $tag ID of tag.
     */
    public function setTag(int $tag)
    {
        $this->tag = $tag;
    }

    /**
     * Return type.
     *
     * @return  string Type of content object.
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Set type.
     *
     * Filter list by content type.
     *
     * @param   string $type Type of content object.
     */
    public function setType(string $type)
    {
        $this->type = $this->trimString($type);
    }
}
