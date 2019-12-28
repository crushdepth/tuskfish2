<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\CriteriaFactory class file.
 * 
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.1
 * @package     database
 */

/**
 * Factory for instantiating Criteria objects and injecting dependencies.
 * 
 * Use this class to delegate construction of Criteria objects. See the Tuskfish Developer Guide
 * for a full explanation and examples.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.1
 * @package     database
 */
class CriteriaFactory
{    
    /**
     * Factory method to instantiate and return a Criteria object.
     * 
     * @return \Tfish\Criteria Instance of a \Tfish\Criteria object.
     */
    public function criteria(): Criteria
    {
        return new Criteria();
    }
    
    /**
     * Factory method to instantiate and return a Tfish\CriteriaItem object.
     * 
     * @param string $column Name of column in database table. Alphanumeric and underscore
     * characters only.
     * @param mixed $value Value of the column.
     * @param string $operator See \Tfish\CriteriaItem::listPermittedOperators() for a list of
     * acceptable operators.
     * @return \Tfish\CriteriaItem
     */
    public function item(string $column, $value, string $operator = '='): CriteriaItem
    {
        return new CriteriaItem($column, $value, $operator);
    }
}
