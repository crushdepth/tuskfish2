<?php

declare(strict_types=1);

namespace Tfish\Model;

/**
 * \Tfish\Model\Block class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Model for block admin interface operations.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 * @var         \Tfish\Cache Instance of the Tfish cache class.
 */

class Block
{
    use \Tfish\Traits\ValidateString;

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
        \Tfish\Cache $cache)
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
        $this->preference = $preference;
        $this->cache = $cache;
    }

    /** Actions. */

    /**
     * Delete block object.
     *
     * @param   int $id ID of block object.
     * @return  bool True on success, false on failure.
     */
    public function delete(int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        $row = $this->getRow($id);

        if (!$row) {
            return false;
        }

        // Need to delete blockRoute entries for this block ID
        // Delete outbound taglinks owned by this content.
        //if ($row['type'] !== 'TfTag' && !$this->deleteTaglinks($id, 'block')) {
        //    return false;
        //}

        // Flush cache.
        if (!$this->cache->flush()) {
            return false;
        }

        // Finally, delete the object.
        return $this->database->delete('block', $id);
    }

    /**
     * Get block objects.
     *
     * @param   array $params Filter criteria.
     * @return  array Array of block objects.
     */
    public function getObjects(array $params): array
    {
        $cleanParams = $this->validateParams($params);
        $criteria = $this->setCriteria($cleanParams);

        return $this->runQuery($criteria);
    }

    /**
     * Toggle a block object online or offline.
     *
     * @param   int $id ID of content object.
     * @return  bool True on success, false on failure.
     */
    public function toggleOnlineStatus(int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        $result = $this->database->toggleBoolean($id, 'block', 'onlineStatus');
        $this->cache->flush();

        return $result;
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

    // PROBABLY NOT REQUIRED?
    /**
     * Return certain columns from a block object required to aid its deletion.
     *
     * @param   int $id ID of content object.
     * @return  array Associative array containing type, id.
     */
    private function getRow(int $id)
    {
        if ($id < 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_NOTICE);
            return [];
        }

        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('id', $id));

        return $this->database->select('block', $criteria, ['type', 'id'])
            ->fetch(\PDO::FETCH_ASSOC);
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

        $statement = $this->database->select('block', $criteria, ['title']);

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
        return $this->database->selectCount('block', $criteria);
    }

    /**
     * Run the select query.
     *
     * @param   \Tfish\Criteria $criteria Filter criteria.
     * @param   array $columns Columns to select.
     * @return  array Array of block objects.
     */
    private function runQuery(\Tfish\Criteria $criteria, array|null $columns = null): array
    {
        $statement = $this->database->select('block', $criteria, $columns);

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
