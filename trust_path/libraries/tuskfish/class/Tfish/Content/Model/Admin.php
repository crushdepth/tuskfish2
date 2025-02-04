<?php

declare(strict_types=1);

namespace Tfish\Content\Model;

/**
 * \Tfish\Content\Model\Admin class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Model for admin interface operations.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\Content\ContentTypes	Provides definition of permitted content object types.
 * @uses        trait \Tfish\Traits\Taglink Manage object-tag associations via taglinks.
 * @uses        trait \Tfish\Traits\TagRead Retrieve tag information for display.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 * @var         \Tfish\Cache Instance of the Tfish cache class.
 * @var         \Tfish\FileHandler Instance of the Tfish filehandler class.
 */

class Admin
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\Taglink;
    use \Tfish\Traits\TagRead;
    use \Tfish\Traits\ValidateString;

    private $database;
    private $criteriaFactory;
    private $preference;
    private $cache;
    private $fileHandler;

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish site preferences class.
     * @param   \Tfish\FileHandler $fileHandler Instance of the Tuskfish filehandler class.
     * @param   \Tfish\Cache Instance of the Tuskfish cache class.
     */
    public function __construct(
        \Tfish\Database $database,
        \Tfish\CriteriaFactory $criteriaFactory,
        \Tfish\Entity\Preference $preference,
        \Tfish\FileHandler $fileHandler,
        \Tfish\Cache $cache)
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
        $this->preference = $preference;
        $this->cache = $cache;
        $this->fileHandler = $fileHandler;
    }

    /** Actions. */

    /**
     * Delete content object.
     *
     * @param   int $id ID of content object.
     * @param   string $lang 2-letter ISO 639-1 language code.
     * @return  bool True on success, false on failure.
     */
    public function delete(int $id, string $lang): bool
    {
        if ($id < 1) {
            return false;
        }

        if (!\array_key_exists($lang, $this->preference->listLanguages())) {
            return false;
        }

        $row = $this->getRow($id, $lang);

        if (!$row) {
            return false;
        }

        // Delete associated image.
        if ($row['image'] && !$this->deleteImage($row['image'])) {
            return false;
        }

        // Delete associated media.
        if ($row['type'] !== 'TfVideo' && $row['media'] && !$this->deleteMedia($row['media'])) {
            return false;
        }

        // Delete outbound taglinks owned by this content.
        if ($row['type'] !== 'TfTag' && !$this->deleteTaglinks($id, 'content')) {
            return false;
        }

        // If object is a tag, delete inbound taglinks referring to it.
        if ($row['type'] === 'TfTag' && !$this->deleteReferencesToTag($id)) {
            return false;
        }

        // If object is a collection delete related parent references in child content.
        if ($row['type'] === 'TfCollection') {
            if (!$this->deleteReferencesToParent($row['uid'])) {
                return false;
            }
        }

        // Flush cache.
        if (!$this->cache->flush()) {
            return false;
        }

        // Finally, delete the object.
        return $this->database->delete('content', $id, $lang);
    }

    /**
     * Get content objects.
     *
     * @param   array $params Filter criteria.
     * @return  array Array of content objects.
     */
    public function getObjects(array $params): array
    {
        $cleanParams = $this->validateParams($params);
        $criteria = $this->setCriteria($cleanParams);

        return $this->runQuery($criteria);
    }

    /**
     * Toggle a content object online or offline.
     *
     * @param   int $id ID of content object.
     * @param   string $lang 2-letter ISO 639-1 language code.
     * @return  bool True on success, false on failure.
     */
    public function toggleOnlineStatus(int $id, string $lang): bool
    {
        if ($id < 1) {
            return false;
        }

        $lang = $this->trimString($lang);

        if (!\array_key_exists($lang, $this->preference->listLanguages())) {
            return false;
        }

        $result = $this->database->toggleBoolean($id, 'content', 'onlineStatus', $lang);
        $this->clearExpiresOn($id);
        $this->cache->flush();

        return $result;
    }

    /**
     * Clear the expiry date, if set, when an offline object is toggled online.
     *
     * @param integer $id
     */
    private function clearExpiresOn(int $id)
    {
        $sql = "UPDATE `content` set `expiresOn` = '' WHERE `id` = :id AND `onlineStatus` = '1';";
        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $this->database->executeTransaction($statement);
    }

    /** Utilities. */

    /**
     * Count the number of content objects that match the filter criteria.
     *
     * @param   array $params Filter criteria.
     * @return  int Count.
     */
    public function getCount(array $params): int
    {
        unset(
            $params['start'],
            $params['limit'],
            $params['sort'],
            $params['order'],
            $params['secondarySort'],
            $params['secondaryOrder']
        );

        $cleanParams = $this->validateParams($params);
        $criteria = $this->setCriteria($cleanParams);

        return $this->runCount($criteria);
    }

    /**
     * Return a list of options to build a select box.
     *
     * @param   array $params Filter criteria.
     * @param   array $columns Columns to select to build the options.
     * @return  array
     */
    public function getOptions(array $params, array $columns = [])
    {
        $cleanParams = $this->validateParams($params);
        $criteria = $this->setCriteria($cleanParams);

        $cleanColumns = [];

        foreach ($columns as $key => $value) {
            $cleanKey = (int) $key;
            $cleanValue = $this->trimString($value);

            if ($this->isAlnumUnderscore($cleanValue)) {
                $cleanColumns[$cleanKey] = $cleanValue;
            }
        }

        return $this->runQuery($criteria, $cleanColumns);
    }

    /**
     * Return certain columns from a content object required to aid its deletion.
     *
     * @param   int $id ID of content object.
     * @param   string $lang 2-letter ISO 639-1 language code.
     * @return  array Associative array containing type, id, image and media values.
     */
    private function getRow(int $id, string $lang)
    {
        if ($id < 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_NOTICE);
            return [];
        }

        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('id', $id));
        $criteria->add($this->criteriaFactory->item('language', $lang));

        return $this->database->select('content', $criteria, ['type', 'id', 'language', 'image', 'media'])
            ->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Deletes an uploaded image file associated with a content object.
     *
     * @param string $filename Name of file.
     * @return bool True on success, false on failure.
     */
    private function deleteImage(string $filename): bool
    {
        if ($filename) {
            return $this->fileHandler->deleteFile('image/' . $filename);
        }
    }

    /**
     * Deletes an uploaded media file associated with a content object.
     *
     * @param string $filename Name of file.
     * @return bool True on success, false on failure.
     */
    private function deleteMedia(string $filename): bool
    {
        if ($filename) {
            return $this->fileHandler->deleteFile('media/' . $filename);
        }
    }

    /**
     * Removes references to a collection when it is deleted or changed to another type.
     *
     * @param int uid UID of the parent collection.
     * @return boolean True on success, false on failure.
     */
    private function deleteReferencesToParent(int $uid)
    {
        if ($uid < 1) return false;

        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('parent', $uid));

        return $this->database->updateAll('content', ['parent' => 0], $criteria);
    }

    /**
     * Return the title of a given content object.
     *
     * @param   int $id ID of content object.
     * @return  string Title of content object.
     */
    public function getTitle(int $id)
    {
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('id', $id));

        $statement = $this->database->select('content', $criteria, ['title']);

        return $statement->fetch(\PDO::FETCH_COLUMN);
    }

    /**
     * Run the count query.
     *
     * @param   \Tfish\Criteria $criteria Filter criteria.
     * @return  int Count.
     */
    private function runCount(\Tfish\Criteria $criteria): int
    {
        return $this->database->selectCount('content', $criteria);
    }

    /**
     * Run the select query.
     *
     * @param   \Tfish\Criteria $criteria Filter criteria.
     * @param   array $columns Columns to select.
     * @return  array Array of content objects.
     */
    private function runQuery(\Tfish\Criteria $criteria, array|null $columns = null): array
    {
        $statement = $this->database->select('content', $criteria, $columns);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Set filter criteria on queries.
     *
     * @param   array $cleanParams Parameters to filter the query.
     * @return  \Tfish\Criteria
     */
    private function setCriteria(array $cleanParams): \Tfish\Criteria
    {
        $criteria = $this->criteriaFactory->criteria();

        if (isset($cleanParams['onlineStatus']))
            $criteria->add($this->criteriaFactory->item('onlineStatus', $cleanParams['onlineStatus']));

        // If ID is set, retrieve a single object.
        if (!empty($cleanParams['id'])) {
            $criteria->add($this->criteriaFactory->item('id', $cleanParams['id']));

            return $criteria;
        }

        if (!empty($cleanParams['type']))
            $criteria->add($this->criteriaFactory->item('type', $cleanParams['type']));

        if (!empty($cleanParams['tag']))
            $criteria->setTag([$cleanParams['tag']]);

        if (!empty($cleanParams['start']))
            $criteria->setOffset($cleanParams['start']);

        if (!empty($cleanParams['sort']))
            $criteria->setSort($cleanParams['sort']);

        if (!empty($cleanParams['order']))
            $criteria->setOrder($cleanParams['order']);

        if (!empty($cleanParams['secondarySort'])) {
            $criteria->setSecondarySort($cleanParams['secondarySort']);
        }

        if (!empty($cleanParams['secondaryOrder']))
            $criteria->setSecondaryOrder($cleanParams['secondaryOrder']);

        $criteria->setLimit($this->preference->adminPagination());

        return $criteria;
    }

    /**
     * Validate criteria used to filter query.
     *
     * @param   array $params Filter criteria.
     * @return  array Validated filter criteria.
     */
    private function validateParams(array $params): array
    {
        $cleanParams = [];

        if ($params['id'] ?? 0)
            $cleanParams['id'] = (int) $params['id'];

        if ($params['start'] ?? 0)
            $cleanParams['start'] = (int) $params['start'];

        if ($params['tag'] ?? 0)
            $cleanParams['tag'] = (int) ($params['tag']);

        if (isset($params['type']) && \array_key_exists($params['type'], $this->listTypes())) {
            $cleanParams['type'] = $this->trimString($params['type']);
        }

        if (isset($params['onlineStatus'])) {
            $onlineStatus = (int) $params['onlineStatus'];

            if ($onlineStatus == 0 || $onlineStatus == 1) {
                $cleanParams['onlineStatus'] = $onlineStatus;
            }
        }

        if (isset($params['sort']) && $this->isAlnumUnderscore($params['sort'])) {
            $cleanParams['sort'] = $this->trimString($params['sort']);
        }

        if (isset($params['order'])) {

            if ($params['order'] === 'ASC') {
                $cleanParams['order'] = 'ASC';
            } else {
                $cleanParams['order'] = 'DESC';
            }
        }

        if (isset($params['secondarySort']) && $this->isAlnumUnderscore($params['secondarySort'])) {
            $cleanParams['secondarySort'] = $this->trimString($params['secondarySort']);
        }

        if (isset($params['secondaryOrder'])) {

            if ($params['secondaryOrder'] === 'ASC') {
                $cleanParams['secondaryOrder'] = 'ASC';
            } else {
                $cleanParams['secondaryOrder'] = 'DESC';
            }
        }

        return $cleanParams;
    }
}
