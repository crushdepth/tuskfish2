<?php

declare(strict_types=1);

namespace Tfish\Model;

/**
 * \Tfish\Model\BlockAdmin class file.
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

class BlockAdmin
{
    use \Tfish\Traits\BlockOption;
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

        // Delete related blockRoute entries.
        $sql = "DELETE FROM `blockRoute` WHERE `blockId` = :id";
        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);

        if (!$statement->execute()) {
            return false;
        }

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
    public function getItems(array $params): array
    {
        $cleanParams = $this->validateParams($params);

        return $this->runQuery($cleanParams);
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

    /**
     * Return a unique list of routes to which blocks are currently assigned.
     *
     * @return array
     */
    public function activeBlockRoutes(): array
    {
        $sql = "SELECT DISTINCT `route` FROM `blockRoute` ORDER BY `route` ASC";
        $statement = $this->database->preparedStatement($sql);
        $statement->execute();
        $routes = $statement->fetchAll(\PDO::FETCH_COLUMN);

        return $routes ?: [];
    }

    /**
     * Return a unique list of positions to which blocks are currently assigned.
     *
     * @return array
     */
    public function activeBlockPositions(): array
    {
        $sql = "SELECT DISTINCT `position` FROM `block` ORDER BY `position` ASC";
        $statement = $this->database->preparedStatement($sql);
        $statement->execute();
        $positions = $statement->fetchAll(\PDO::FETCH_COLUMN);

        return $positions ?: [];
    }

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

        return $this->runCount($cleanParams);
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
    private function runCount(array $criteria): int
    {
        // Base SQL query to count rows
        $sql = "SELECT COUNT(DISTINCT `block`.`id`) as count "
        . "FROM `block` "
        . "LEFT JOIN `blockRoute` ON `block`.`id` = `blockRoute`.`blockId` ";

        // Prepare WHERE clauses and bindings
        $queryComponents = $this->prepareQueryComponents((array) $criteria);
        $sql .= $queryComponents['whereClause'];

        // Prepare and execute query
        $statement = $this->database->preparedStatement($sql);

        // Bind parameters
        foreach ($queryComponents['bindings'] as $key => $value) {
            $statement->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }

        // Execute.
        $statement->execute();
        $result = (int) $statement->fetchColumn();

        // Return the count (default to 0 if no result)
        return $result ?? 0;
    }

    /**
     * Run the select query.
     *
     * @param   array $params Filter parameters.
     * @return  array Array of block data.
     */
    private function runQuery(array $params): array
    {
        $blocks = [];

        // Base SQL query.
        $sql = "SELECT `type`, `block`.`id`, `position`, `title`, `weight`, `template`,"
         . "`onlineStatus`, `blockRoute`.`route` "
         . "FROM `block` "
         . "LEFT JOIN `blockRoute` ON `block`.`id` = `blockRoute`.`blockId` ";

        // Prepare WHERE clauses and bindings.
        $queryComponents = $this->prepareQueryComponents($params);
        $sql .= $queryComponents['whereClause'];

        // Sorting.
        if (!empty($params['sort'])) {
            $sql .= " ORDER BY `{$params['sort']}` {$params['order']} ";

            if (!empty($params['secondarySort'])) {
                $sql .= ", `{$params['secondarySort']}` {$params['secondaryOrder']} ";
            }
        }

        // Add LIMIT and OFFSET
        $limit = (int) $this->preference->adminPagination();
        $start = !empty($params['start']) ? (int) $params['start'] : 0;
        $sql .= "LIMIT :start, :limit";

        // Prepare and execute query.
        $statement = $this->database->preparedStatement($sql);

        // Bind parameters.
        foreach ($queryComponents['bindings'] as $key => $value) {
            $statement->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }

        // Bind LIMIT and START.
        $statement->bindValue(':start', $start, \PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);

        // Fetch results.
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();
        $blocks = $statement->fetchAll();

        return $blocks;
    }

    /**
     * Prepare WHERE clauses and bindings.
     *
     * @param   array $criteria Filter criteria or parameters.
     * @return  array An array with 'whereClause' (string) and 'bindings' (array).
     */
    private function prepareQueryComponents(array $criteria): array
    {
        $whereClauses = [];
        $bindings = [];

        if (!empty($criteria['id'])) {
            $whereClauses[] = "`block`.`id` = :id";
            $bindings[':id'] = $criteria['id'];
        }

        $routeWhitelist = $this->blockRoutes();

        if (!empty($criteria['route']) && \in_array($criteria['route'], $routeWhitelist)) {
            $whereClauses[] = "`blockRoute`.`route` = :route";
            $bindings[':route'] = $criteria['route'];
        }

        $positionWhitelist = $this->blockPositions();

        if (!empty($criteria['position']) && \array_key_exists($criteria['position'], $positionWhitelist)) {
            $whereClauses[] = "`block`.`position` = :position";
            $bindings[':position'] = $criteria['position'];
        }

        if (isset($criteria['onlineStatus'])) {
            $whereClauses[] = "`block`.`onlineStatus` = :onlineStatus";
            $bindings[':onlineStatus'] = $criteria['onlineStatus'];
        }

        $whereClause = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) . " " : "";

        return ['whereClause' => $whereClause, 'bindings' => $bindings];
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

        if ($params['route'] ?? '')
            $cleanParams['route'] = $this->trimString($params['route']);

        if ($params['position'] ?? '')
            $cleanParams['position'] = $this->trimString($params['position']);

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
