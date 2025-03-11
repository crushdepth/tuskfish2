<?php

declare(strict_types=1);

namespace Tfish\Expert\ViewModel;

/**
 * \Tfish\Expert\ViewModel\Admin class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     expert
 */

/**
 * ViewModel for admin interface operations.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     expert
 * @uses        trait \Tfish\Expert\Traits\Options Common traits of expert objects and form controls.
 * @uses        trait \Tfish\Traits\Listable Provides a standard implementation of the \Tfish\View\Listable interface.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\ValidateToken Provides CSRF check functionality.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         string $contentTitle Name of experts to display in confirm delete request.
 * @var         array $contentList An array of experts to be displayed in this page view.
 * @var         int $contentCount The number of experts that match filtering criteria. Used to build pagination control.
 * @var         int $id ID of a single expert to be displayed.
 * @var         int $start Position in result set to retrieve objects from.
 * @var         int $tag Filter search results by tag ID.
 * @var         int $country Filter search results by country ID.
 * @var         int $onlineStatus Filter search results by online (1) or offline (0) status.
 * @var         string $backUrl $backUrl URL to return to if the user cancels the action.
 * @var         string $response Message to display to the user after processing action (success/failure).
 */

class Admin implements \Tfish\Interface\Listable
{
    use \Tfish\Expert\Traits\Options;
    use \Tfish\Traits\Listable;
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;

    private $model;
    private $preference;
    private $contentTitle = '';
    private $contentList = [];
    private $contentCount = 0;
    private $id = 0;
    private $start = 0;
    private $tag = 0;
    private $country = 0;
    private $type = 'TfExpert';
    private $onlineStatus = 2;
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
        $this->sort = 'submissionTime';
        $this->order = 'DESC';
        $this->secondarySort = 'lastName';
        $this->secondaryOrder = 'ASC';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions */

    /**
     * Cancel action and redirect to admin page.
     */
    public function displayCancel()
    {
        \header('Location: ' . TFISH_EXPERTS_ADMIN_URL);
        exit;
    }

    /**
     * Display delete confirmation form.
     */
    public function displayConfirmDelete()
    {
        $this->pageTitle = TFISH_CONFIRM;
        $this->template = 'confirmDeleteExpert';
        $this->action = 'confirm';
        $this->backUrl = TFISH_EXPERTS_ADMIN_URL;
    }

    /**
     * Delete expert object and display result.
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
        $this->backUrl = TFISH_EXPERTS_ADMIN_URL;
    }

    /**
     * Display the admin summary table.
     *
     * Table a list of experts and links to view, edit and delete items.
     */
    public function displayTable()
    {
        $this->pageTitle = TFISH_EXPERTS;
        $this->countContent();
        $this->listContent();
        $this->template = 'expertTable';
    }

    /**
     * Toggle an expert object online or offline.
     */
    public function displayToggle()
    {
        $this->model->toggleOnlineStatus($this->id);
        $this->template = 'expertTable';
        header('Location: ' . TFISH_EXPERTS_ADMIN_URL);
    }

    /** output */

    /**
     * Count expert objects meeting filter criteria.
     */
    public function countContent()
    {
        $this->contentCount = $this->model->getCount(
            [
                'id' => $this->id,
                'start' => $this->start,
                'tag' => $this->tag,
                'country' => $this->country,
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
        if (!empty($this->country)) $extraParams['country'] = $this->country;
        if (isset($this->onlineStatus) && $this->onlineStatus == 0 || $this->onlineStatus == 1)
            $extraParams['onlineStatus'] = $this->onlineStatus;

        return $extraParams;
    }

    /**
     * Get expert objects matching cached filter criteria.
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
                'country' => $this->country,
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
     * Return expert count.
     *
     * @return  int Number of experts that match filtering criteria.
     */
    public function contentCount(): int
    {
        return $this->contentCount;
    }

    /**
     * Return expert list.
     *
     * @return  array Array of expert objects.
     */
    public function contentList(): array
    {
        return $this->contentList;
    }

    /**
     * Return ID.
     *
     * @return  int ID of expert.
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * Set ID.
     *
     * @param   int $id ID of expert object.
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * Return page title.
     */
    public function contentTitle(): string
    {
        return $this->contentTitle;
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
     * Return ID of country filter.
     *
     * @return  int
     */
    public function country(): int
    {
        return $this->country;
    }

    /**
     * Set country ID.
     *
     * @param   int $tag ID of country.
     */
    public function setCountry(int $country)
    {
        $this->country = $country;
    }
}
