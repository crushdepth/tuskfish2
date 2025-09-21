<?php

declare(strict_types=1);

namespace Tfish\Content\ViewModel;

/**
 * \Tfish\Content\ViewModel\Listing class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for displaying a list of content objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\Content\ContentTypes Provides definition of permitted content object types.
 * @uses        trait \Tfish\Traits\Group Whitelist of user groups on system and bitmask authorisation tests.
 * @uses        trait \Tfish\Traits\TagRead Retrieve tag information for display.
 * @uses        trait \Tfish\Traits\Listable Provides a standard implementation of the \Tfish\View\Listable interface.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         \Tfish\Content\Entity\content $content A single content object for display.
 * @var         array $contentTags Array of tag IDs/names associated with a single content object.
 * @var         array $contentList An array of content objects to be displayed in this page view.
 * @var         int $contentCount The number of content objects that match filtering criteria. Used to build pagination control.
 * @var         \Tfish\Content\Entity\Content $parent The parent of this content (the collection to which it belongs).
 * @var         array $children Array of content objects that are members of this collection.
 * @var         string $description Long-form description of this content.
 * @var         string $author Creator of this content.
 * @var         string $backUrl $backUrl URL to return to if the user cancels the action.
 * @var         string $response Message to display to the user after processing action (success/failure).
 * @var         int $id ID of a single content object to be displayed.
 * @var         int $start Position in result set to retrieve objects from.
 * @var         int $tag Filter search results by tag ID.
 * @var         string $type Filter search results by content type.
 * @var         int $onlineStatus Filter search results by online (1) or offline (0) status.
 */
class Listing implements \Tfish\Interface\Listable
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\Group;
    use \Tfish\Traits\Listable;
    use \Tfish\Traits\TagRead;
    use \Tfish\Traits\ValidateString;

    private object $model;
    private \Tfish\Entity\Preference $preference;
    private mixed $content = '';
    private array $contentList = [];
    private int $contentCount = 0;
    private mixed $parent = '';
    private array $children = [];
    private string $description = '';
    private string $author = '';
    private string $backUrl = '';
    private string $response = '';
    private int $id = 0;
    private int $start = 0;
    private int $tag = 0;
    private string $type = '';
    private int $inFeed = 1;
    private int $onlineStatus = 1;

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
        $this->theme = $preference->defaultTheme();
        $this->pageTitle = TFISH_LATEST_POSTS;
    }

    /** Actions */

    /**
     * Display list of content in short (teaser) form.
     *
     * @return void
     */
    public function displayList(): void
    {
        $this->template = 'listView';
        $this->listContent();
        $this->countContent();
        $this->setMetadata();
    }

    /**
     * Display a single content object.
     *
     * @return void
     */
    public function displayObject(): void
    {
        $this->content = $this->getObject($this->id);

        if ($this->content) {

            // Authorisation check.
            $contentMask = (int) $this->content->accessGroups();
            $userMask = (int) $this->model->currentUserMask();

            if (!$this->canAccess($userMask, $contentMask)) {
                if ($userMask === 0) {
                    $this->model->setNextUrl($_SERVER['REQUEST_URI'] ?? '/');
                    $this->model->setRedirectTitle(TFISH_MEMBER_CONTENT);
                    $this->model->setRedirectMessage(TFISH_PLEASE_LOGIN);
                    \header('Location: ' . TFISH_URL . 'login/', true, 303);
                    exit;
                }

                $this->model->setRedirectTitle(TFISH_RESTRICTED_ACCESS);
                $this->model->setRedirectMessage(TFISH_RESTRICTED_ACCESS_MESSAGE);
                \header('Location: ' . TFISH_URL . 'restricted/', true, 303);
                exit;
            }

            $this->pageTitle = $this->content->metaTitle();
            $this->description = $this->content->metaDescription();
            $this->author = $this->content->creator();
            $this->parent = $this->getObject($this->content->parent());

            if ($this->content->type() === 'TfCollection' || $this->content->type() === 'TfTag') {
                $this->listChildren();
                $this->countContent();
            }

            $this->template = !empty($this->template) ? $this->template : $this->content->template();
            $this->setMetadata();

            if ($this->content->accessGroups() !== 0) {
                $this->doNotCache = true;
            }
        } else {
            $this->pageTitle = TFISH_ERROR;
            $this->response = TFISH_ERROR_NO_SUCH_CONTENT;
            $this->backUrl = TFISH_URL;
            $this->template = 'response';
        }
    }

    /** Utilities. */

    /**
     * Return IDs and titles of tags that are actually in use with content objects.
     *
     * @param   string $zeroOption Text for the default (unselected) option.
     * @return  array IDs and titles as key-value pairs.
     */
    public function activeTagOptions(string $zeroOption = TFISH_SELECT_TAGS): array
    {
        $zeroOption = $this->trimString($zeroOption);
        $rows = $this->model->activeTagOptions('content');

        return $this->selectBoxOptions($zeroOption, $rows);
    }

    /**
     * Return canonical URL for this page view.
     *
     * Used to populate the canonical link tag in theme files.
     *
     * @return  string
     */
    public function canonicalUrl(): string
    {
        $canonicalUrl = TFISH_URL;

        if ($this->id || $this->start || $this->tag) $canonicalUrl .= '?';

        $params = [];
        $params['id'] = $this->id;
        $params['tag'] = $this->tag;
        $params['start'] = $this->start;

        // Discard empty parameters.
        $setParams = \array_filter($params);

        // Append parameters separated with '&amp;'.
        $canonicalUrl .= \http_build_query($setParams, '', '&', PHP_QUERY_RFC3986);

        return $canonicalUrl;
    }

    /**
     * Count content objects meeting filter criteria.
     *
     * @return void
     */
    public function countContent(): void
    {
        $params = [
            'tag' => $this->tag,
            'type' => $this->type,
            'inFeed' => $this->inFeed,
            'onlineStatus' => $this->onlineStatus
        ];

        if (!empty($this->content) && $this->content->type() === 'TfTag') {
            $params['tag'] = $this->content->id();
        }

        if (!empty($this->content) && $this->content->type() === 'TfCollection') {
            $params['parent'] = $this->content->id();
        }

        $this->contentCount = $this->model->getCount($params);
    }

    /**
     * Return tags associated with a content object.
     *
     * @return  array Array of tags as id/title key-value pairs.
     */
    public function contentTags(): array
    {
        $tags = $this->model->getTagsForObject($this->id, 'content', 'content');

        return $tags;
    }

    /**
     * Return extra parameters to be included in pagination control links.
     *
     * @return  array
     */
    public function extraParams(): array
    {
        if (!empty($this->id)) {
            return ['id' => $this->id];
        }

        // tag, country, status
        $extraParams = [];

        if (!empty($this->type)) {
            $extraParams['type'] = $this->trimString($this->type);
        }

        return $extraParams;

    }

    /**
     * Get a content object.
     *
     * @param   int $id ID of content object.
     * @return \Tfish\Content\Entity\Content $content
     */
    private function getObject(int $id): mixed
    {
        return $this->model->getObject($id);
    }

    /**
     * Return user-side pagination limit.
     *
     * @return  int Number of items to display on user-side pages.
     */
    public function limit(): int
    {
        return $this->preference->userPagination();
    }

    /**
     * Return child (collection or tag) pagination limit.
     *
     * @return  int Number of items to display on collection / tag pages.
     */
    public function childLimit(): int
    {
        return $this->preference->collectionPagination();
    }

    /**
     * Retrieve children of a content object (collection or tag).
     *
     * @return  void
     */
    public function listChildren(): void
    {
        $params = [
            'start' => $this->start,
            'limit' => $this->childLimit(),
            'type' => $this->type,
            'onlineStatus' => $this->onlineStatus,
            'sort' => $this->sort,
            'order' => $this->order,
            'secondarySort' => $this->secondarySort,
            'secondaryOrder' => $this->secondaryOrder
        ];

        if ($this->content->type() === 'TfTag') $params['tag'] = $this->content->id();
        if ($this->content->type() === 'TfCollection') $params['parent'] = $this->content->id();

        $this->children = $this->model->getObjects($params);
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
                'limit' => $this->limit(),
                'tag' => $this->tag,
                'type' => $this->type,
                'inFeed' => $this->inFeed,
                'onlineStatus' => $this->onlineStatus,
                'sort' => $this->sort,
                'order' => $this->order,
                'secondarySort' => $this->secondarySort,
                'secondaryOrder' => $this->secondaryOrder
            ]
        );
    }

    /** Getters and setters */

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
     * Return children.
     *
     * @return  array Array of content objects.
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * Return content object.
     *
     * @return  \Tfish\Content\Entity\Content
     */
    public function content(): ?\Tfish\Content\Entity\Content
    {
        return $this->content;
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
     * Returns the template for formatting the date from preferences.
     */
    public function dateFormat(): string
    {
        return $this->preference->dateFormat();
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
     * @return void
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * Returns the Google Maps API key (if set) from preferences.
     *
     * @return  string Google Maps API key.
     */
    public function mapsApiKey(): string
    {
        return $this->preference->mapsApiKey();
    }

    /**
     * Return parent of a content object.
     *
     * @return  \Tfish\Content\Entity\Content Parent content object.
     */
    public function parent(): mixed
    {
        return $this->parent;
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
     * Set start.
     *
     * @param   int $start of first object to view in the set of available records.
     * @return void
     */
    public function setStart(int $start): void
    {
        $this->start = $start;
    }

    /**
     * Return tag ID.
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
        if (!empty($type) && !\array_key_exists($type, $this->listTypes())) {
           throw new \InvalidArgumentException(TFISH_ERROR_ILLEGAL_TYPE);
        }

        $this->type = $this->trimString($type);
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
     * Return the response message (success or failure) for an action.
     *
     * @return  string
     */
    public function response(): string
    {
        return $this->response;
    }

    /**
     * Set page-specific overrides of the site metadata.
     *
     * Overrides trait setMetadata().
     *
     * @param   array $metadata Metadata overrides as key-value pairs.
     * @return void
     */
    public function setMetadata(array $metadata = []): void
    {
        if (!empty($this->pageTitle)) $metadata['title'] = $this->pageTitle;
        if (!empty($this->description)) $metadata['description'] = $this->description;
        if (!empty($this->author)) $metadata['author'] = $this->author;
        if (!empty($this->robots)) $metadata['robots'] = $this->robots;

        $metadata['canonicalUrl'] = $this->canonicalUrl();

        $this->metadata = $metadata;
    }
}
