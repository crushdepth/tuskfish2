<?php

declare(strict_types=1);

namespace Tfish\Content\ViewModel;

/**
 * \Tfish\Content\ViewModel\Gallery class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for displaying an image gallery.
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
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         array $contentList An array of content objects to be displayed in this page view.
 * @var         int $contentCount The number of content objects that match filtering criteria. Used to build pagination control.
 * @var         int $id ID of a single content object to be displayed.
 * @var         int $start Position in result set to retrieve objects from.
 * @var         int $tag Filter search results by tag ID.
 * @var         string $type Filter search results by content type.
 * @var         int $onlineStatus Filter search results by online (1) or offline (0) status.
 */

class Gallery implements \Tfish\Interface\Listable
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\FetchBlock;
    use \Tfish\Traits\Listable;
    use \Tfish\Traits\ValidateString;

    private $model;
    private $preference;
    private $contentList = [];
    private $contentCount = 0;
    private $id = 0;
    private $start = 0;
    private $tag = 0;
    private $type = '';
    private $onlineStatus = 1;

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     * @param   \Tfish\BlockFactory $blockFactory
     */
    public function __construct($model, \Tfish\Entity\Preference $preference, \Tfish\BlockFactory $blockFactory)
    {
        $this->pageTitle = TFISH_IMAGE_GALLERY;
        $this->model = $model;
        $this->preference = $preference;
        $this->blockFactory = $blockFactory;
        $this->template = 'gallery';
        $this->theme = 'default';
        $this->setMetadata(['canonicalUrl' => $this->canonicalUrl()]);
    }

    /** Actions */

    /**
     * Display a list of images.
     */
    public function displayList()
    {
        $this->listContent();
        $this->countContent();
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
     * Return a collection of tags.
     *
     * Retrieves tags that have been grouped into a collection as ID-title key-value pairs.
     * Used to build select box controls.
     *
     * @param   int $id ID of the collection content object.
     * @param   string $zeroOption Text for the default (unselected) option.
     * @return  array Tag IDs and titles as associative array.
     */
    public function collectionTagOptions(int $id, $zeroOption = TFISH_SELECT_TAGS): array
    {
        $zeroOption = $this->trimString($zeroOption);
        $rows = $this->model->collectionTagOptions($id);

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

        if ($this->id) return $canonicalUrl . '?id=' . $this->id;

        if ($this->start || $this->tag) $canonicalUrl .= '?';
        if ($this->start) $canonicalUrl .= 'start=' . $this->start;
        if ($this->start && $this->tag) $canonicalUrl .= '&amp;';
        if ($this->tag) $canonicalUrl .= 'tag=' . $this->tag;

        return $canonicalUrl;
    }

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
     * Return extra parameters to be included in pagination control links.
     *
     * @return  array
     */
    public function extraParams(): array
    {
        return ($this->tag) ? [$this->tag] : [];
    }

    /**
     * Return gallery pagination limit.
     *
     * @return  int Number of items to display on gallery pages.
     */
    public function limit(): int
    {
        return $this->preference->galleryPagination();
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
                'limit' => $this->limit(),
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

    /** Getters and setters */

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
    public function contentList()
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
     * @param   int $start ID of content object to start on.
     */
    public function setStart(int $start)
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

    /**
     * Return online status.
     *
     * @return  int Online (1) or offline (0).
     */
    public function onlineStatus(): int
    {
        return $this->onlineStatus;
    }
}
