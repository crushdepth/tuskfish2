<?php

declare(strict_types=1);

namespace Tfish\User\Model;

/**
 * \Tfish\User\Model\Admin class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 */

/**
 * Model for admin interface operations.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 */

class Admin
{
    use \Tfish\Traits\ValidateString;

    private $database;
    private $criteriaFactory;
    private $preference;

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
        \Tfish\Entity\Preference $preference)
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
        $this->preference = $preference;
    }

    /** Actions. */

    /**
     * Delete user.
     *
     * The admin (user group 1) may not be deleted.
     *
     * @param   int $id ID of user.
     * @return  bool True on success, false on failure.
     */
    public function delete(int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        $row = $this->getRow($id);

        if (empty($row) || $row['userGroup'] === '1') {
            return false;
        }

        return $this->database->delete('user', $id);
    }

    /**
     * Get users.
     *
     * @param   array $params Filter criteria.
     * @return  array Array of users as associative arrays.
     */
    public function getObjects(array $params): array
    {
        $cleanParams = $this->validateParams($params);
        $criteria = $this->setCriteria($cleanParams);

        return $this->runQuery($criteria);
    }

    /**
     * Toggle a user online or offline.
     *
     * The administrator (userGroup 1) may not be set offline.
     *
     * @param   int $id ID of user object.
     * @return  bool True on success, false on failure.
     */
    public function toggleOnlineStatus(int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        $user = $this->getRow($id);

        if ($user['userGroup'] === '1') return true;

        return $this->database->toggleBoolean($id, 'user', 'onlineStatus');
    }

    /** Utilities. */

    /**
     * Count users matching criteria (locked at zero as pagination is not in use).
     *
     * @param   array $params Filter criteria.
     * @return  int Count.
     */
    public function getCount(array $params): int { return 0; }

    /**
     * Return old user object state from database to aid in update/deletion.
     *
     * @param   int $id ID of content object.
     * @return  array Associative array containing type, id, image and media values.
     */
    private function getRow(int $id)
    {
        if ($id < 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_NOTICE);
            return [];
        }

        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('id', $id));

        return $this->database->select('user', $criteria)->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Return the adminEmail address of a given user.
     *
     * @param   int $id ID of user.
     * @return  string Title of user.
     */
    public function getEmail(int $id)
    {
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('id', $id));

        $statement = $this->database->select('user', $criteria, ['adminEmail']);

        return $statement->fetch(\PDO::FETCH_COLUMN);
    }

    /**
     * Run the select query.
     *
     * @param   \Tfish\Criteria $criteria Filter criteria.
     * @param   array $columns Columns to select.
     * @return  array Array of user objects.
     */
    private function runQuery(\Tfish\Criteria $criteria, array $columns = null): array
    {
        $statement = $this->database->select('user', $criteria, $columns);

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

        if (!empty($cleanParams['sort']))
            $criteria->setSort($cleanParams['sort']);

        if (!empty($cleanParams['order']))
            $criteria->setOrder($cleanParams['order']);

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

        return $cleanParams;
    }
}
