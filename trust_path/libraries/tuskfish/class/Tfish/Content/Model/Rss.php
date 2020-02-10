<?php

declare(strict_types=1);

namespace Tfish\Content\Model;


/**
 * \Tfish\Content\Model\Rss class file.
 * 
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Model for generating RSS feeds from content objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 * @var         \Tfish\Cache Instance of the Tfish cache class.
 */

class Rss
{    
    private $database;
    private $criteriaFactory;
    private $preference;
    private $cache;

    /**
     * Constructor.
     * 
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish site preferences class.
     * @param   \Tfish\Cache Instance of the Tuskfish cache class.
     */
    public function __construct(
        \Tfish\Database $database,
        \Tfish\CriteriaFactory $criteriaFactory,
        \Tfish\Entity\Preference $preference,
        \Tfish\Cache $cache
        )
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
        $this->preference = $preference;
        $this->cache = $cache;
    }

    /* Getters and setters. */

    /**
     * Customise RSS feed title and description for a specific tag or collection.
     * 
     * @param   int $id ID of a target tag or collection object.
     * @return array Array containing title and description of custom feed.
     */
    public function customFeed(int $id)
    {
        if ($id < 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('id', $id));
        $criteria->add($this->criteriaFactory->item('type', 'TfStatic', '!='));
        $criteria->add($this->criteriaFactory->item('type', 'TfTag', '!='));
        $criteria->add($this->criteriaFactory->item('type', 'TfBlock', '!='));
        $criteria->add($this->criteriaFactory->item('onlineStatus', 1));
        $statement = $this->database->select('content', $criteria, ['title', 'description']);

        return $statement->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Return content objects for the feed.
     * 
     * @param   int $parentId ID of the parent collection, if any.
     * @return  array Array of content objects.
     */
    public function getObjects(int $parentId = 0): array
    {
        $criteria = $this->criteriaFactory->criteria();
        $criteria->setOffset(0);
        $criteria->setLimit($this->preference->rssPosts());
        $criteria->setOrder('submissionTime');
        $criteria->setOrderType("DESC");
        $criteria->add($this->criteriaFactory->item('type', 'TfStatic', '!='));
        $criteria->add($this->criteriaFactory->item('type', 'TfTag', '!='));
        $criteria->add($this->criteriaFactory->item('type', 'TfBlock', '!='));
        $criteria->add($this->criteriaFactory->item('onlineStatus', 1));

        if ($parentId > 0) {
            $criteria->add($this->criteriaFactory->item('parent', $parentId));
        }

        $statement = $this->database->select('content', $criteria);

        return $statement->fetchAll(\PDO::FETCH_CLASS, '\Tfish\Content\Entity\Content');
    }

    /**
     * Return content objects for a given tag.
     * 
     * @param   int $tagId ID of the tag.
     * @return  array Array of content objects.
     */
    public function getObjectsforTag(int $tagId): array
    {
        if ($tagId < 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }
        
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('module', 'content'));
        $criteria->add($this->criteriaFactory->item('tagId', $tagId));
        $criteria->add($this->criteriaFactory->item('contentType', 'TfBlock', '!='));

        $statement = $this->database->select('taglink', $criteria, ['contentId']);

        $params = $this->database->selectDistinct('taglink', $criteria, ['contentId'])
            ->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($params)) {
            return [];
        }

        $sql = "SELECT * FROM `content` WHERE `id` IN (";
        
        foreach ($params as $id) {
            $sql .= "?,";
        }

        $sql = rtrim($sql, ",");
        $sql .= ") ";
        $sql .= "AND `onlineStatus` = '1' ";
        $sql .= "ORDER BY `date` DESC, `submissionTime` DESC ";

        $sql .= "LIMIT ? OFFSET 0";
        $params[] = $this->preference->rssPosts();
        $statement = $this->database->preparedStatement($sql);

        $result = $statement->execute($params);

        if (!$result) {
            \trigger_error(TFISH_ERROR_INSERTION_FAILED, E_USER_ERROR);
            return false;
        }

        return $statement->fetchAll(\PDO::FETCH_CLASS, '\Tfish\Content\Entity\Content');
        
    }
}
