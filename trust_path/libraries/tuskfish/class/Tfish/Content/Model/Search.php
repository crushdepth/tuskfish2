<?php

declare(strict_types=1);

namespace Tfish\Content\Model;

/**
 * \Tfish\Content\Model\Search class file.
 * 
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Model for searching content objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 * @var         int $onlineStatus Switch to filter online/offline content.
 */

class Search
{
    use \Tfish\Traits\ValidateString;

    private $database;
    private $criteriaFactory;
    private $preference;
    private $onlineStatus;

    /**
     * Constructor.
     * 
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish site preferences class.
     */
    public function __construct(
        \Tfish\Database $database,
        \Tfish\CriteriaFactory $criteriaFactory,
        \Tfish\Entity\Preference $preference
        )
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
        $this->preference = $preference;
        $this->onlineStatus = 1; // Default to online content only.
    }

    /** Actions. */

    /**
     * Search content objects.
     * 
     * @param   array $params Filtering criteria (keywords, limit, offset etc).
     */
    public function search(array $params): array
    {
        $cleanParams = $this->validateParams($params);

        return $this->searchContent($cleanParams);
    }

    /** Utilities. */

    /**
     * Get objects matching filtering criteria.
     * 
     * @param   array $params Filtering criteria such as keywords, limit, offset etc.
     */
    public function getObjects(array $params): array
    {
        $cleanParams = $this->validateParams($params);
        $criteria = $this->setCriteria($cleanParams);

        return $this->runQuery($criteria);        
    }

    /**
     * Return onlineStatus.
     * 
     * @return  int Retrieve all content (0) or only online content (1).
     */
    public function onlineStatus(): int
    {
        return (int) $this->onlineStatus;
    }

    /**
     * Set onlineStatus.
     * 
     * @param   int $onlineStatus Retrieve all content (0) or only online content (1).
     */
    public function setOnlineStatus(int $onlineStatus)
    {
        if ($onlineStatus == 0 || $onlineStatus == 1) {
            $this->onlineStatus = $onlineStatus;
        }
    }

    /**
     * Run search query.
     * 
     * @param   \Tfish\Criteria $criteria Filtering criteria.
     * @return  array Array of content objects.
     */
    private function runQuery(\Tfish\Criteria $criteria): array
    {
        $statement = $this->database->select('content', $criteria);

        return $statement->fetchAll(\PDO::FETCH_CLASS, '\Tfish\Content\Entity\Content');
    }

    /**
     * Search content objects.
     * 
     * The first element of the returned results is a count of the total number of objects matching the
     * search criteria. This is a bit of a hack that should probably be done away with in due course.
     * 
     * @param   array $params Search criteria.
     * @return  array Array of content objects matching search criteria, with content count as first element.
     */
    private function searchContent(array $params): array
    {
        $sql = $count = $contentCount = '';
        $searchTermPlaceholders = $escapedTermPlaceholders = [];
        $result = [];

        $searchType = $this->trimString(($params['searchType'] ?? ''));
        $whitelist = ["AND", "OR", "exact"];
        $position = \array_search($searchType, $whitelist, true);
        
        if ($position === false) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
            exit;
        }

        $cleanSearchType = $whitelist[$position];
        
        $sqlCount = "SELECT count(*) ";
        $sqlSearch = "SELECT * ";
        $sql = "FROM `content` ";
        $count = \count($params['searchTerms']);
        
        // If there are no legal search terms, return nil result.
        if ($count === 0) {
            return [];
        }

        $sql .= "WHERE ";
        
        for ($i = 0; $i < $count; $i++) {
            $searchTermPlaceholders[$i] = ':searchTerm' . (string) $i;
            $escapedTermPlaceholders[$i] = ':escapedSearchTerm' . (string) $i;
            $sql .= "(";
            $sql .= "`title` LIKE " . $searchTermPlaceholders[$i] . " OR ";
            $sql .= "`teaser` LIKE " . $escapedTermPlaceholders[$i] . " OR ";
            $sql .= "`description` LIKE " . $escapedTermPlaceholders[$i] . " OR ";
            $sql .= "`caption` LIKE " . $searchTermPlaceholders[$i] . " OR ";
            $sql .= "`creator` LIKE " . $searchTermPlaceholders[$i] . " OR ";
            $sql .= "`publisher` LIKE " . $searchTermPlaceholders[$i];
            $sql .= ")";
            
            if ($i != ($count - 1)) {
               $sql .= " " . $cleanSearchType . " ";
            }
        }
        
        if ($params['onlineStatus'] !== 0) {
            $sql .= " AND `onlineStatus` = :onlineStatus";
        }

        $sql .= " AND `type` != 'TfBlock' ";
        $sqlCount .= $sql;
        $sql .= "ORDER BY `date` DESC, `submissionTime` DESC ";
        
        // Bind the search term values and execute the statement.
        $statement = $this->database->preparedStatement($sqlCount);

        if ($statement) {
            
            for ($i = 0; $i < $count; $i++) {
                $statement->bindValue($searchTermPlaceholders[$i], "%" . $params['searchTerms'][$i] . "%", \PDO::PARAM_STR);
                $statement->bindValue($escapedTermPlaceholders[$i], "%" . $params['escapedSearchTerms'][$i] . "%", \PDO::PARAM_STR);
            }

            if ($params['onlineStatus'] !== 0) {
                $statement->bindValue(":onlineStatus", $params['onlineStatus'], \PDO::PARAM_INT);
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

            if ($params['onlineStatus'] !== 0) {
                $statement->bindValue(":onlineStatus", $params['onlineStatus'], \PDO::PARAM_INT);
            }
            
        } else {
            return false;
        }

        $statement->execute();

        // need the content to be indexed by id, otherwise element 0 gets overwritten.
        $result = $statement->fetchAll(\PDO::FETCH_CLASS, '\Tfish\Content\Entity\Content');
        \array_unshift($result, $contentCount);

        return $result;
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

        if (\is_array($params['searchTerms'])) {

            $cleanParams['searchTerms'] = [];

            foreach($params['searchTerms'] as $value) {
                $cleanParams['searchTerms'][] = $this->trimString($value);
            }
        }

        if (\is_array($params['escapedSearchTerms'])) {

            $cleanParams['escapedSearchTerms'] = [];

            foreach($params['escapedSearchTerms'] as $value) {
                $cleanParams['escapedSearchTerms'][] = $this->trimString($value);
            }
        }

        if ($params['searchType'] !== 'AND' && $params['searchType'] !== 'OR' && $params['searchType'] !== 'exact') {
            \trigger_error(TFISH_ERROR_ILLEGAL_TYPE, E_USER_NOTICE);
            $cleanParams['searchType'] = 'AND';
        } else {
            $cleanParams['searchType'] = $this->trimString($params['searchType']);
        }

        $start = (int) ($params['start'] ?? 0);

        if ($start >= 0) {
            $cleanParams['start'] = $start;
        }

        $limit = (int) ($params['limit'] ?? 0);

        if ($limit > 0) {
            $cleanParams['limit'] = $limit;
        }

        if (isset($params['order']) && $this->isAlnumUnderscore($params['order'])) {
            $cleanParams['order'] = $this->trimString($params['order']);
        }

        if (isset($params['orderType'])) {
            
            if ($params['order'] === 'ASC') {
                $cleanParams['orderType'] = 'ASC';
            } else {
                $cleanParams['orderType'] = 'DESC';
            }
        }

        if (isset($params['secondaryOrder']) && $this->isAlnumUnderscore($params['secondaryOrder'])) {
            $cleanParams['secondaryOrder'] = $this->trimString($params['secondaryOrder']);
        }

        if (isset($params['secondaryOrderType'])) {
            
            if ($params['secondaryOrder'] === 'ASC') {
                $cleanParams['secondaryOrderType'] = 'ASC';
            } else {
                $cleanParams['secondaryOrderType'] = 'DESC';
            }
        }

        if (isset($params['onlineStatus']) && $params['onlineStatus'] == 0) {
            $cleanParams['onlineStatus'] = 0;
        } else {
            $cleanParams['onlineStatus'] = 1;
        }

        return $cleanParams;
    }
}
