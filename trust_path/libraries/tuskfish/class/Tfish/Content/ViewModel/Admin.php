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
    use \Tfish\Traits\Listable;
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;

    private object $model;
    private \Tfish\Entity\Preference $preference;
    private string $contentTitle = '';
    private array $contentList = [];
    private int $contentCount = 0;
    private int $id = 0;
    private int $status = 0;
    private int $start = 0;
    private int $tag = 0;
    private string $type = '';
    private int $inFeed = 2;
    private int $onlineStatus = 2;
    private string $action = '';
    private string $backUrl = '';
    private string $response = '';

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     */
    public function __construct(object $model, \Tfish\Entity\Preference $preference)
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
     *
     * @return void
     */
    public function displayCancel(): void
    {
        \header('Location: ' . TFISH_ADMIN_URL);
        exit;
    }

    /**
     * Display delete confirmation form.
     *
     * @return void
     */
    public function displayConfirmDelete(): void
    {
        $this->pageTitle = TFISH_CONFIRM;
        $this->template = 'confirmDelete';
        $this->action = 'confirm';
        $this->backUrl = TFISH_ADMIN_URL;
    }

    /**
     * Delete content object and display result.
     *
     * @return void
     */
    public function displayDelete(): void
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

        $this->template = 'response';
        $this->backUrl = TFISH_ADMIN_URL;
    }

    /**
     * Display the admin summary table.
     *
     * Table a list of content and links to view, edit and delete items.
     *
     * @return void
     */
    public function displayTable(): void
    {
        $this->pageTitle = TFISH_ADMIN;
        $this->listContent();
        $this->countContent();
        $this->template = 'contentTable';
    }

    /**
     * Toggle a content object online or offline using htmx.
     *
     * @return void
     */
    public function displayToggle(): void
    {
        $this->model->toggleOnlineStatus($this->id);

        if ($this->status === 1) {
            $this->status = 0;
            echo '<a class="text-danger" hx-post="' . TFISH_ADMIN_URL . '?action=toggle"'
            . ' target="closest td" hx-vals=\'{"id": "' . $this->id . '", "status": "0"}\' '
            . 'hx-swap="outerHTML"><i class="icon-xmark"></i></a>';
        } else {
            $this->status = 1;
            echo '<a class="text-success" hx-post="' . TFISH_ADMIN_URL . '?action=toggle"'
              . ' target="closest td" hx-vals=\'{"id": "' . $this->id . '", "status": "1"}\' '
              . 'hx-swap="outerHTML"><i class="icon-check"></i></a>';
        }
        exit; // Prevents proceeding to full page reload.
    }

    /**
     * Toggle a content object inFeed status using htmx.
     */
    public function displayInFeedToggle(): string
    {
        $this->model->toggleInFeedStatus($this->id);

        if ($this->inFeed === 1) {
            $this->inFeed = 0;
            echo '<a class="text-danger" hx-post="' . TFISH_ADMIN_URL . '?action=toggleInFeed"'
            . ' target="closest td" hx-vals=\'{"id": "' . $this->id . '", "inFeed": "0"}\' '
            . 'hx-swap="outerHTML"><i class="icon-xmark"></i></a>';
        } else {
            $this->inFeed = 1;
            echo '<a class="text-success" hx-post="' . TFISH_ADMIN_URL . '?action=toggleInFeed"'
              . ' target="closest td" hx-vals=\'{"id": "' . $this->id . '", "inFeed": "1"}\' '
              . 'hx-swap="outerHTML"><i class="icon-check"></i></a>';
        }
        exit; // Prevents proceeding to full page reload.
    }

    /** output */

    /**
     * Count content objects meeting filter criteria.
     *
     * @return void
     */
    public function countContent(): void
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
        if ($this->onlineStatus === 0 || $this->onlineStatus === 1) {
            $extraParams['onlineStatus'] = $this->onlineStatus;
        }


        return $extraParams;
    }

    /**
     * Get content objects matching cached filter criteria.
     *
     * Result is cached as $contentList property.
     *
     * @return void
     */
    public function listContent(): void
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
    public function tagOptions($zeroOption = TFISH_SELECT_TAGS): array
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
    public function typeOptions($zeroOption = TFISH_SELECT_TYPE): array
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
    public function statusOptions($defaultOption = TFISH_SELECT_STATUS): array
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
     *
     * @return void
     */
    public function setId(int $id): void
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
     *
     * @return void
     */
    public function setContentTitle(): void
    {
        $this->contentTitle = $this->model->getTitle($this->id);
    }

    /**
     * Return status.
     *
     * @return integer
     */
    public function status(): int
    {
        return (int) $this->status;
    }

    /**
     * Set status.
     *
     * @param integer $status
     * @return void
     */
    public function setStatus(int $status): void
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
     * Set onlineStatus.
     *
     * @var int $onlineStatus
     * @return void
     */
    public function setOnlineStatus(int $onlineStatus): void
    {
        $this->onlineStatus = $onlineStatus;
    }

    /**
     * Return inFeed status.
     *
     * @return  int In feed (1) or not (0).
     */
    public function inFeed(): int
    {
        return (int) $this->inFeed;
    }

    /**
     * Set inFeed status.
     *
     * @param   int $onlineStatus Online (1) or offline (0).
     * @return void
     */
    public function setInFeed(int $inFeed): void
    {
        $this->inFeed = $inFeed;
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
     * @return void
     */
    public function setStart(int $start): void
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
     * @return void
     */
    public function setTag(int $tag): void
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
     * @return void
     */
    public function setType(string $type): void
    {
        $this->type = $this->trimString($type);
    }
}
