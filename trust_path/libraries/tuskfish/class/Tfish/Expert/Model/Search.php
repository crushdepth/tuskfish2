<?php

declare(strict_types=1);

namespace Tfish\Expert\Model;

/**
 * \Tfish\Expert\Model\Search class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     expert
 */

/**
 * Model for searching expert objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     expert
 * @uses        trait \Tfish\Traits\TagRead Retrieve tag information for display.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 * @var         int $onlineStatus Switch to filter online/offline content.
 * @var         \Tfish\Session $session Instance of the Tuskfish session manager class.
 */

class Search
{
    use \Tfish\Traits\TagRead;
    use \Tfish\Traits\ValidateString;

    private $database;
    private $criteriaFactory;
    private $preference;
    private $onlineStatus = 1; // Default to online content only.
    private $session;
    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish site preferences class.
     * @param   \Tfish\Session $session Instance of the Tuskfish session manager class.
     */
    public function __construct(
        \Tfish\Database $database,
        \Tfish\CriteriaFactory $criteriaFactory,
        \Tfish\Entity\Preference $preference,
        \Tfish\Session $session
        )
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
        $this->preference = $preference;
        $this->session = $session;
    }

    /** Actions. */

    /**
     * Get a single expert object.
     *
     * @param   int $id ID of the expert object to retrieve.
     * @return  Mixed \Tfish\Expert\Entity\Expert on success, false on failure.
     */
    public function getObject(int $id)
    {
        $params = [];

        $id = (int) $id;

        if ($id < 1) {
            return false;
        }

        $params['id'] = $id;

        $cleanParams = $this->validateParams($params);
        $criteria = $this->setCriteria($cleanParams);
        $statement = $this->database->select('expert', $criteria);
        $expert = $statement->fetchObject('\Tfish\Expert\Entity\Expert');
        $statement->closeCursor();

        return $expert;
    }

    /**
     * Search content objects.
     *
     * @param   array $params Filtering criteria (keywords, limit, offset etc).
     */
    public function search(array $params): array
    {
        $cleanParams = $this->validateParams($params);

        return $this->searchText($cleanParams);
    }

    /**
     * Search expert objects.
     *
     * The first element of the returned results is a count of the total number of objects matching the
     * search criteria. This is a bit of a hack that should probably be done away with in due course.
     *
     * @param   array $params Search criteria.
     * @return  array Array of expert objects matching search criteria, with count as first element.
     */
    private function searchText(array $params): array
    {
        $sql = $count = $contentCount = '';
        $searchTermPlaceholders = $escapedTermPlaceholders = [];
        $result = [];

        $sqlCount = "SELECT count(*) ";
        $sqlSearch = "SELECT * ";
        $sql = "FROM `expert` ";
        $count = !empty($params['searchTerms']) ? \count($params['searchTerms']) : 0; // change 29 May 2023

        // If there are no legal search terms, return nil result.
        if ($count === 0) {
            return [];
        }

        $sql .= "WHERE ";

        for ($i = 0; $i < $count; $i++) {
            $searchTermPlaceholders[$i] = ':searchTerm' . (string) $i;
            $escapedTermPlaceholders[$i] = ':escapedSearchTerm' . (string) $i;
            $sql .= "(";
            $sql .= "`firstName` LIKE " . $searchTermPlaceholders[$i] . " OR ";
            $sql .= "`midName` LIKE " . $searchTermPlaceholders[$i] . " OR ";
            $sql .= "`lastName` LIKE " . $searchTermPlaceholders[$i] . " OR ";
            $sql .= "`job` LIKE " . $searchTermPlaceholders[$i] . " OR ";
            $sql .= "`experience` LIKE " . $escapedTermPlaceholders[$i] . " OR ";
            $sql .= "`projects` LIKE " . $escapedTermPlaceholders[$i] . " OR ";
            $sql .= "`publications` LIKE " . $escapedTermPlaceholders[$i] . " OR ";
            $sql .= "`businessUnit` LIKE " . $searchTermPlaceholders[$i] . " OR ";
            $sql .= "`organisation` LIKE " . $searchTermPlaceholders[$i];
            $sql .= ")";

            if ($i != ($count - 1)) {
               $sql .= " AND ";
            }
        }

        if ($this->onlineStatus !== 0) {
            $sql .= " AND `onlineStatus` = :onlineStatus ";
        }

        $sqlCount .= $sql;
        $sql .= "ORDER BY `lastName` ASC, `firstName` ASC ";

        // Bind the search term values and execute the statement.
        $statement = $this->database->preparedStatement($sqlCount);

        if ($statement) {

            for ($i = 0; $i < $count; $i++) {
                $statement->bindValue($searchTermPlaceholders[$i], "%" . $params['searchTerms'][$i] . "%", \PDO::PARAM_STR);
                $statement->bindValue($escapedTermPlaceholders[$i], "%" . $params['escapedSearchTerms'][$i] . "%", \PDO::PARAM_STR);
            }

            if ($this->onlineStatus !== 0) {
                $statement->bindValue(":onlineStatus", $this->onlineStatus, \PDO::PARAM_INT);
            }

        } else {
            return false;
        }

        // Execute the statement.
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_NUM);
        $contentCount = \reset($row);
        unset($statement, $row);

        // Retrieve the subset of objects actually required.
        $sql .= "LIMIT :limit ";

        if (isset($params['start'])) {
          $sql .= "OFFSET :offset ";
        }

        $sqlSearch .= $sql;

        $statement = $this->database->preparedStatement($sqlSearch);

        if ($statement) {

            for ($i = 0; $i < $count; $i++) {
                $statement->bindValue($searchTermPlaceholders[$i], "%" . $params['searchTerms'][$i] . "%", \PDO::PARAM_STR);
                $statement->bindValue($escapedTermPlaceholders[$i], "%" . $params['escapedSearchTerms'][$i] . "%", \PDO::PARAM_STR);
                $statement->bindValue(":limit", (int) $params['limit'], \PDO::PARAM_INT);

                if (isset($params['start'])) {
                    $statement->bindValue(":offset", (int) $params['start'], \PDO::PARAM_INT);
                }
            }

            if ($this->onlineStatus !== 0) {
                $statement->bindValue(":onlineStatus", $this->onlineStatus, \PDO::PARAM_INT);
            }

        } else {
            return false;
        }

        $statement->execute();

        // need the content to be indexed by id, otherwise element 0 gets overwritten.
        $result = $statement->fetchAll(\PDO::FETCH_CLASS, '\Tfish\Expert\Entity\Expert');
        \array_unshift($result, $contentCount);

        return $result;
    }

    /**
     * Search database by lastname.
     *
     * @param array $params Filter criteria.
     * @return array
     */
    public function searchAlphabetically(array $params): array
    {
        $cleanParams = $this->validateParams($params);

        return $this->searchAlpha($cleanParams);
    }

    /**
     * Search experts alphabetically.
     *
     * The first element of the returned results is a count of the total number of objects matching the
     * search criteria. This is a bit of a hack that should probably be done away with in due course.
     *
     * @param   array $params Search criteria.
     * @return  array Array of expert objects matching search criteria, with count as first element.
     */
    private function searchAlpha(array $params): array
    {
        $sql = $count = '';
        $result = [];

        $sqlCount = "SELECT count(*) ";
        $sqlSearch = "SELECT * ";
        $sql = "FROM `expert` WHERE (`lastName` LIKE :lastName AND `onlineStatus` = :onlineStatus)  ";
        $sql .= "ORDER BY `lastName` ASC, `firstName` ASC ";
        $sqlCount .= $sql;

        // Bind the search term values and execute the statement.
        $statement = $this->database->preparedStatement($sqlCount);

        if ($statement) {
            $statement->bindValue(':lastName', $params['alpha'] . "%", \PDO::PARAM_STR);
            $statement->bindValue(":onlineStatus", $this->onlineStatus, \PDO::PARAM_INT);
        } else {
            return false;
        }

        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_NUM);
        $result[0] = reset($row);
        unset($statement, $row);

        // Retrieve the subset of objects actually required.
        if (!empty($params['limit'])) {
            $sql .= "LIMIT :limit ";
        }

        if (!empty($params['start'])) {
            $sql .= "OFFSET :offset ";
        }

        $sqlSearch .= $sql;
        $statement = $this->database->preparedStatement($sqlSearch);

        if ($statement) {
            $statement->bindValue(':lastName', $params['alpha'] . "%", \PDO::PARAM_STR);
            $statement->bindValue(":onlineStatus", $this->onlineStatus, \PDO::PARAM_INT);
            $statement->bindValue(":limit", (int) $params['limit'], \PDO::PARAM_INT);

            if ($params['start']) {
                $statement->bindValue(":offset", $params['start'], \PDO::PARAM_INT);
            }
        } else {
            return false;
        }

        $statement->execute();
        $rows = $statement->fetchAll(\PDO::FETCH_CLASS, '\Tfish\Expert\Entity\Expert');

        return \array_merge($result, $rows);
    }

    /**
     * Returns experts filtered by tag and/or country.
     *
     * @param array $params
     * @return array Array of expert objects with count as first element.
     */
    public function searchTagCountry(array $params): array
    {
        $cleanParams = $this->validateParams($params);
        $criteria = $this->setCriteria($cleanParams);
        $statement = $this->database->select('expert', $criteria);
        $count = $this->database->selectCount('expert', $criteria);
        $result = $statement->fetchAll(\PDO::FETCH_CLASS, '\Tfish\Expert\Entity\Expert');
        $statement->closeCursor();

        return \array_merge([$count], $result);
    }

    /** Utilities. */

    /**
     * Set filter criteria for listing content.
     *
     * @param   array $params Filter criteria.
     * @return   \Tfish\Criteria Query composer.
     */
    private function setCriteria(array $cleanParams): \Tfish\Criteria
    {
        $criteria = $this->criteriaFactory->criteria();

        if (!empty($cleanParams['id'])) {
            $criteria->add($this->criteriaFactory->item('id', $cleanParams['id']));

            return $criteria;
        }

        if (!empty($cleanParams['tag']))
            $criteria->setTag([$cleanParams['tag']]);

        if (!empty($cleanParams['country']))
            $criteria->add($this->criteriaFactory->item('country', $cleanParams['country']));

        if (!empty($cleanParams['start']))
            $criteria->setOffset($cleanParams['start']);

        if (!empty($cleanParams['sort'])) {
            $criteria->setSort($cleanParams['sort']);
            $criteria->setOrder($cleanParams['order']);
        }

        if (!empty($cleanParams['secondarySort'])) {
            $criteria->setSecondarySort($cleanParams['secondarySort']);
            $criteria->setSecondaryOrder($cleanParams['secondaryOrder']);
        }

        if (!empty($cleanParams['limit'])) {
            $criteria->setLimit($cleanParams['limit']);
        }

        return $criteria;
    }

    /**
     * Validate search parameters.
     *
     * @param   array $params Search parameters.
     * @return  array Validated search parameters.
     */
    private function validateParams(array $params): array
    {
        $cleanParams = [];

        $id = (int) ($params['id'] ?? 0);

        if ($id > 0) {
            $cleanParams['id'] = $id;
        }

        $alpha = $this->trimString($params['alpha'] ?? '');

        if (!empty($alpha) && $this->isAlpha($alpha) && \mb_strlen($params['alpha'], 'UTF-8') === 1) {
                $cleanParams['alpha'] = $alpha;
        }

        $tag = (int) ($params['tag'] ?? 0);

        if ($tag > 0) {
            $cleanParams['tag'] = $tag;
        }

        $country = (int) ($params['country'] ?? 0);

        if ($country > 0) {
            $cleanParams['country'] = $country;
        }

        if (!empty($params['searchTerms']) && \is_array($params['searchTerms'])) {

            $cleanParams['searchTerms'] = [];

            foreach($params['searchTerms'] as $value) {
                $cleanParams['searchTerms'][] = $this->trimString($value);
            }
        }

        if (!empty($params['escapedSearchTerms']) && \is_array($params['escapedSearchTerms'])) {

            $cleanParams['escapedSearchTerms'] = [];

            foreach($params['escapedSearchTerms'] as $value) {
                $cleanParams['escapedSearchTerms'][] = $this->trimString($value);
            }
        }

        $start = (int) ($params['start'] ?? 0);

        if ($start >= 0) {
            $cleanParams['start'] = $start;
        }

        $limit = (int) ($params['limit'] ?? 0);

        if ($limit > 0) {
            $cleanParams['limit'] = $limit;
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

    /** Getters and setters **/

    /**
     * Return onlineStatus.
     *
     * @return  int Retrieve all experts (0) or only online experts (1).
     */
    public function onlineStatus(): int
    {
        return (int) $this->onlineStatus;
    }

    /**
     * Set onlineStatus.
     *
     * @param   int $onlineStatus Retrieve all experts (0) or only online experts (1).
     */
    public function setOnlineStatus(int $onlineStatus)
    {
        if ($onlineStatus == 0 || $onlineStatus == 1) {
            $this->onlineStatus = $onlineStatus;
        }
    }
}
