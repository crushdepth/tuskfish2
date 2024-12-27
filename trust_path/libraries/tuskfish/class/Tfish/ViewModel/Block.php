<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModel\Block class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for block admin interface operations.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\BlockOption Provides block route and position whitelists.
 * @uses        trait \Tfish\Traits\Listable Provides a standard implementation of the \Tfish\View\Listable interface.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\ValidateToken Provides CSRF check functionality.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         string $contentTitle Name of block object to display in confirm delete request.
 * @var         array $contentList An array of block objects to be displayed in this page view.
 * @var         int $contentCount The number of block objects that match filtering criteria. Used to build pagination control.
 * @var         int $id ID of a single block object to be displayed.
 * @var         int $status The online status of a single block being toggled on or offline.
 * @var         int $start Position in result set to retrieve objects from.
 * @var         string $route Filter search results by route.
 * @var         int $onlineStatus Filter search results by online (1) or offline (0) status.
 * @var         string $backUrl $backUrl URL to return to if the user cancels the action.
 * @var         string $response Message to display to the user after processing action (success/failure).
 */

class Block implements \Tfish\Interface\Listable
{
    use \Tfish\Traits\BlockOption;
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
    private $route = '';
    private $position = '';
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
    public function __construct($model, \Tfish\Entity\Preference $preference)
    {
        $this->model = $model;
        $this->preference = $preference;
        $this->theme = 'admin';
        $this->sort = 'weight';
        $this->order = 'ASC';
        $this->secondarySort = 'type';
        $this->secondaryOrder = 'ASC';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions */

    /**
     * Cancel action and redirect to admin page.
     */
    public function displayCancel()
    {
        \header('Location: ' . TFISH_ADMIN_URL . 'blocks/');
        exit;
    }

    /**
     * Display delete confirmation form.
     */
    public function displayConfirmDelete()
    {
        $this->pageTitle = TFISH_CONFIRM;
        $this->template = 'confirmDeleteBlock';
        $this->action = 'confirm';
        $this->backUrl = TFISH_ADMIN_URL . 'blocks/';
    }

    /**
     * Delete block object and display result.
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
        $this->backUrl = TFISH_ADMIN_URL . 'blocks/';
    }

    /**
     * Display the admin summary table.
     *
     * Table a list of blocks and links to view, edit and delete items.
     */
    public function displayTable()
    {
        $this->pageTitle = TFISH_ADMIN_BLOCKS;
        $this->listItems();
        $this->countItems();
        $this->template = 'blockTable';
    }

    /**
     * Toggle a block object online or offline using htmx.
     */
    public function displayToggle(): string
    {
        $this->model->toggleOnlineStatus($this->id);

        if ($this->status === 1) {
            $this->status = 0;
            echo '<a class="text-danger" hx-post="' . TFISH_ADMIN_URL . 'blocks/?action=toggle"'
            . ' target="closest td" hx-vals=\'{"id": "' . $this->id . '", "status": "0"}\' '
            . 'hx-swap="outerHTML"><i class="fas fa-times"></i></a>';
        } else {
            $this->status = 1;
            echo '<a class="text-success" hx-post="' . TFISH_ADMIN_URL . 'blocks/?action=toggle"'
              . ' target="closest td" hx-vals=\'{"id": "' . $this->id . '", "status": "1"}\' '
              . 'hx-swap="outerHTML"><i class="fas fa-check"></i></a>';
        }
        exit; // Prevents proceeding to full page reload.
    }

    /** output */

    /**
     * Count block objects meeting filter criteria.
     */
    public function countItems()
    {
        $this->contentCount = $this->model->getCount(
            [
                'id' => $this->id,
                'start' => $this->start,
                'route' => $this->route,
                'position' => $this->position,
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

        if (!empty($this->route)) $extraParams['route'] = $this->route;
        if (!empty($this->position)) $extraParams['position'] = $this->position;
        if (isset($this->onlineStatus) && $this->onlineStatus == 0 || $this->onlineStatus == 1)
            $extraParams['onlineStatus'] = $this->onlineStatus;

        return $extraParams;
    }

    /**
     * Get data for blocks adminstration page (DB rows).
     *
     * Result is cached as $contentList property.
     */
    public function listItems()
    {
        $this->contentList = $this->model->getItems(
            [
                'id' => $this->id,
                'start' => $this->start,
                'route' => $this->route,
                'position' => $this->position,
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
     * Return options for route select box control.
     *
     * @param   string $zeroOption Text to display as default select box option.
     * @return  array IDs and block types as key-value pairs.
     */
    public function routeOptions($zeroOption = TFISH_SELECT_ROUTE): array
    {
        $zeroOption = $this->trimString($zeroOption);

        return [$zeroOption] + $this->model->activeBlockRoutes();
    }

    /**
     * Return options for position select box control.
     *
     * @param   string $zeroOption Text to display as default select box option.
     * @return  array IDs and block types as key-value pairs.
     */
    public function positionOptions($zeroOption = TFISH_SELECT_POSITION): array
    {
        $zeroOption = $this->trimString($zeroOption);

        return [$this->trimString($zeroOption)] + $this->model->activeBlockPositions();
    }

    /**
     * Return options for online status select box control.
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
     * @return  array Array of block objects.
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
        return (int) $this->onlineStatus;
    }

    /**
     * Set online status.
     *
     * @param   int $onlineStatus Online (1) or offline (0).
     */
    public function setOnlineStatus(int $onlineStatus)
    {
        $this->onlineStatus = (int) $onlineStatus;
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
     * Return route.
     *
     * @return string Route filter.
     */
    public function route(): string
    {
        return $this->route;
    }

    /**
     * Set route.
     *
     * Filter block list by route.
     *
     * @param string $route Route.
     */
    public function setRoute(string $route)
    {
        $this->route = $this->trimString($route);
    }

    /**
     * Return position.
     *
     * @return string Position filter.
     */
    public function position(): string
    {
        return $this->position;
    }

    /**
     * Set position.
     *
     * Filter block list by position.
     *
     * @param string $position Position.
     */
    public function setPosition(string $position)
    {
        $this->position = $this->trimString($position);
    }

    /** Required to satisfy Listable interface. */
    public function tag(): int { return 0; }
}
