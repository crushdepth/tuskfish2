<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\Criteria class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     database
 */

/**
 * Sets conditions on database queries, used to compose a query.
 *
 * Use this class to set parameters on database-related actions. Individual conditions are held
 * within the item property, as CriteriaItem objects. Criteria holds the basic query parameters
 * and controls how CriteriaItem are chained together (eg. with "AND", "OR").
 *
 * See the Tuskfish Developer Guide for a full explanation and examples.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     database
 * @uses        trait \Tfish\Traits\IntegerCheck	Validate and range check integers.
 * @uses        trait \Tfish\Traits\TraversalCheck	Validates that a filename or path does NOT contain directory traversals in any form.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         array $item Array of \Tfish\CriteriaItem.
 * @var         array $condition Array of conditions used to join CriteriaItem (AND, OR).
 * @var         string $groupBy Column to group results by.
 * @var         int $limit Number of records to retrieve.
 * @var         int $offset Starting point for retrieving records.
 * @var         string $sort Primary column to sort records by.
 * @var         string $order Ascending (ASC) or descending(DESC).
 * @var         string $secondarySort secondary column to sort records by.
 * @var         string $secondaryOrder Ascending (ASC) or descending (DESC).
 * @var         array $tag Array of tag IDs.
 */
class Criteria
{
    use Traits\IntegerCheck;
    use Traits\TraversalCheck;
    use Traits\ValidateString;

    public array $item = [];
    public array $condition = [];
    public string $groupBy = '';
    public int $limit = 0;
    public int $offset = 0;
    public string $sort = '';
    public string $order = "DESC";
    public string $secondarySort = '';
    public string $secondaryOrder = "DESC";
    public array $tag = [];

    /**
     * Constructor.
     */
    public function __construct(){}

    /**
     * Add conditions (CriteriaItem) to a query.
     *
     * @param \Tfish\CriteriaItem $criteriaItem CriteriaItem object.
     * @param string $condition Condition used to chain CriteriaItems, "AND" or "OR" only.
     */
    public function add(CriteriaItem $criteriaItem, string $condition = "AND")
    {
        $this->setItem($criteriaItem);
        $this->setCondition($condition);
    }

    /**
     * Add a condition (AND, OR) to a query.
     *
     * @param string $condition AND or OR, only.
     */
    private function setCondition(string $condition)
    {
        $clean_condition = $this->trimString($condition);

        if ($clean_condition === "AND" || $clean_condition === "OR") {
            $this->condition[] = $clean_condition;
        } else {
            throw new \InvalidArgumentException(TFISH_ERROR_ILLEGAL_VALUE);
        }
    }

    /**
     * Set a GROUP BY condition on a query.
     *
     * @param string $groupBy Column to group results by.
     */
    public function setGroupBy(string $groupBy)
    {
        $cleanGroupBy = $this->trimString($groupBy);

        if ($this->isAlnumUnderscore($cleanGroupBy)) {
            $this->groupBy = $cleanGroupBy;
        } else {
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_ALNUMUNDER);
        }
    }

    /**
     * Add an item to filter a query with.
     *
     * @param \Tfish\CriteriaItem $item Contains database column, value and operator to filter a query.
     */
    private function setItem(CriteriaItem $item)
    {
        $this->item[] = $item;
    }

    /**
     * Sets a limit on the number of database records to retrieve in a database query.
     *
     * @param int $limit The number of records to retrieve.
     */
    public function setLimit(int $limit)
    {
        if ($this->isInt($limit, 0)) {
            $this->limit = (int) $limit;
        } else {
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_INT);
        }
    }

    /**
     * Sets an offset (starting point) for retrieving records in a database query.
     *
     * @param int $offset The record to start retrieving results from, from a result set.
     */
    public function setOffset(int $offset)
    {
        if ($this->isInt($offset, 0)) {
            $this->offset = (int) $offset;
        } else {
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_INT);
        }
    }

    /**
     * Sets the primary column to sort query results by.
     *
     * @param string $column Name of the primary column to sort the query results by.
     */
    public function setSort(string $column)
    {
        $column = $this->trimString($column);

        if ($this->isAlnumUnderscore($column)) {
            $this->sort = $column;
        } else {
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_ALNUMUNDER);
        }
    }

    /**
     * Sets the sorting order (ascending or descending) for the primary sort column of a result set.
     *
     * @param string $order Ascending (ASC) or descending (DESC).
     */
    public function setOrder(string $order)
    {
        $order = $this->trimString($order);

        if ($order === "ASC") {
            $this->order = "ASC";
        } else {
            $this->order = "DESC";
        }
    }

    /**
     * Sets the secondary column to sort query results by.
     *
     * @param string $column Name of the secondary column to sort the query results by.
     */
    public function setSecondarySort(string $column)
    {
        $column = $this->trimString($column);

        if ($this->isAlnumUnderscore($column)) {
            $this->secondarySort = $column;
        } else {
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_ALNUMUNDER);
        }
    }

    /**
     * Sets the sorting order (ascending or descending) for the secondary sort column of a result set.
     *
     * @param string $order Ascending (ASC) or descending (DESC).
     */
    public function setSecondaryOrder(string $order)
    {
        $order = $this->trimString($order);

        if ($order === "ASC") {
            $this->secondaryOrder = "ASC";
        } else {
            $this->secondaryOrder = "DESC";
        }
    }

    /**
     * Set tag(s) to filter query results by.
     *
     * @param array $tags Array of tag IDs to be used to filter a query.
     */
    public function setTag(array $tags)
    {
        if (\is_array($tags)) {
            $cleanTags = [];

            foreach ($tags as $tag) {
                if ($this->isInt($tag, 1)) {
                    $cleanTags[] = (int) $tag;
                } else {
                    throw new \InvalidArgumentException(TFISH_ERROR_NOT_INT);
                }
            }

            $this->tag = $cleanTags;
        } else {
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_ARRAY);
        }
    }
}
