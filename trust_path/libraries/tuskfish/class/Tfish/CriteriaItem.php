<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\CriteriaItem class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     database
 */

/**
 * Represents a single clause in the WHERE component of a database query.
 *
 * Add CriteriaItem to Criteria to build your queries. Please see the Tuskfish Developer Guide
 * for a full explanation and examples.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     database
 * @uses        trait \Tfish\Traits\IntegerCheck	Validate and range check integers.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         string $column Name of the database column to use in the query.
 * @var         mixed $value Value to use in the query.
 * @var         string $operator The operator to use in the query.
 */
class CriteriaItem
{
    use Traits\IntegerCheck;
    use Traits\ValidateString;

    public string $column = '';
    public $value = '';
    public string $operator = "="; // Default value.

    /**
     * Constructor.
     *
     * @param string $column Name of column in database table. Alphanumeric and underscore
     * characters only.
     * @param mixed $value Value of the column.
     * @param string $operator See listPermittedOperators() for a list of acceptable operators.
     */
    function __construct(string $column, $value, string $operator = '=')
    {
        $this->setColumn($column);
        $this->setValue($value);
        $this->setOperator($operator);
    }

    /**
     * Provides a whitelist of permitted operators for use in database queries.
     *
     * @todo Consider adding support for "IN", "NOT IN", "BETWEEN", "IS" and "IS NOT". This is a bit
     * messy in PDO if you want to use placeholders because PDO will escape them as a single element
     * unless you pass in an array and build the query string fragment manually in a loop
     * (complicated by the need to distinguish between string and int datatypes). So manual queries
     * may be easier for now. An alternative approach would be to add an extra parameter to
     * Criteria that allows a manual query fragment to be passed in and appended as the last
     * clause of the dynamically generated query string. That would let you handle cases like this
     * simply, but lose the protection from using 100% bound values in the Tuskfish API, which I am
     * very reluctant to give up.
     *
     * @return array Array of permitted operators for use in database queries.
     */
    public function listPermittedOperators(): array
    {
        return ['=', '==', '<', '<=', '>', '>=', '!=', '<>', 'LIKE'];
    }

    /**
     * Specifies the column to use in a query clause.
     *
     * @param string $value Name of column.
     */

    public function setColumn(string $value)
    {
        $cleanValue = $this->trimString($value);

        if ($this->isAlnumUnderscore($cleanValue)) {
            $this->column = $cleanValue;
        } else {
            \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
        }
    }

    /**
     * Sets the operator (=, <, >, etc) to use in a query clause.
     *
     * @param string $value An operator to use in a clause.
     */
    public function setOperator(string $value)
    {
        $cleanValue = $this->trimString($value);

        if (\in_array($cleanValue, $this->listPermittedOperators(), true)) {
            $this->operator = $cleanValue;
        } else {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }
    }

    /**
     * Sets the value of a column to use in a query clause.
     *
     * @param mixed $value Value of column.
     */
    public function setValue($value)
    {
        $type = gettype($value);

        switch ($type) {
            case "string":
                $cleanValue = $this->trimString($value);
                break;

            // Types that can't be validated further in the current context.
            case "array":
            case "boolean":
            case "integer":
            case "double":
                $cleanValue = $value;
                break;

            // Illegal types.
            case "object":
            case "resource":
            case "NULL":
            case "unknown type":
                \trigger_error(TFISH_ERROR_ILLEGAL_TYPE, E_USER_ERROR);
                break;
        }

        $this->value = $cleanValue;
    }
}
