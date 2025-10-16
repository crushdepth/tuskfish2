<?php

declare(strict_types=1);

namespace Tfish\Content\ViewModel;

/**
 * \Tfish\Content\ViewModel\Rss class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for displaying RSS feeds from content objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\Mimetypes	Provides a list of common (permitted) mimetypes for file uploads.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\Interface\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         int $id ID of a single content object to be displayed.
 * @var         int $tag Filter search results by tag ID.
 * @var         array $items Content objects to include in the feed.
 */

class Rss implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\Mimetypes;
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private object $model;
    private int $id = 0;
    private int $tag = 0;
    private string $title = '';
    private string $description = '';
    private array $items = [];
    private \Tfish\Entity\Preference $preference;

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
        $this->template = 'feed';
        $this->theme = 'rss';
        $this->setMetadata([]);
    }

    /* Output. */

    /**
     * Retrive content objects for the feed.
     *
     * @return  void
     */
    public function listContent(): void
    {
        $this->items = $this->model->getObjects($this->id);
    }

    /**
     * Retrieve content objects for a given tag.
     *
     * @return  void
     */
    public function listContentForTag(): void
    {
        $this->items = $this->model->getObjectsForTag($this->tag);
    }

    /* Utilities. */

    /**
     * Customise RSS feed title description for a specific tag or collection.
     *
     * @param int $id ID of the tag or collection to customise feed for.
     * @return void
     */
    private function customFeed(int $id): void
    {
        $customFeed = $this->model->customFeed($id);

        if (!empty($customFeed)) {
            $this->title = $this->trimString($customFeed['title']);
            $this->description = $this->trimString($customFeed['description']);
        }
    }

    /* Getters and setters. */

    /**
     * Return site (feed) copyright.
     *
     * @return  string
     */
    public function copyright(): string
    {
        return $this->preference->siteCopyright();
    }

    /**
     * Return site (feed) description.
     */
    public function description(): string
    {
        if (!empty($this->description)) {
            return strip_tags($this->description);
        }

        return $this->preference->siteDescription();
    }

    /**
     * Return items for the feed.
     *
     * @return  array Array of content objects.
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Return link to RSS feed.
     *
     * @return  string URL.
     */
    public function link(): string
    {
        $url = TFISH_RSS_URL;

        if (!empty($this->id)) {
            $url .= '?id=' . $this->id;
        } elseif (!empty($this->tag)) {
            $url .= '?tag=' . $this->tag;
        }

        return $url;
    }

    /**
     * Set collection ID.
     *
     * @param   int $id ID of collection.
     * @return void
     */
    public function setCollection(int $id): void
    {
        $this->id = $id;
        $this->customFeed($id);
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
        $this->customFeed($tag);
    }

    /**
     * Return site administrative email address.
     *
     * @return  string Email address.
     */
    public function siteEmail(): string
    {
        return $this->preference->siteEmail();
    }

    /**
     * Return site (feed) title.
     *
     * @return  string Title, as set in site preferences.
     */
    public function title(): string
    {
        if (!empty($this->title)) {
            return $this->title;
        }

        return $this->preference->siteName();
    }

    /**
     * Return site webmaster's email address.
     *
     * @return  string Email address.
     */
    public function webMaster(): string
    {
        return $this->preference->siteEmail();
    }
}
