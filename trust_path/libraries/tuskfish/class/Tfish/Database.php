<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\Database class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     database
 */

/**
 * Tuskfish database handler class, implements \PDO and exclusively uses prepared statements.
 *
 * Prepared statements with bound values are used to mitigate SQL injection attacks. Table and
 * column identifiers are also escaped. However, you should have thoroughly validated and range
 * checked data before it reaches this class.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     database
 * @uses        trait \Tfish\Traits\IntegerCheck	Validate and range check integers.
 * @uses        trait \Tfish\Traits\Language Whitelist of languages supported on this system.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \PDO $database Instance of the \PDO database abstraction layer.
 * @var         \Tfish\FileHandler $fileHandler Instance of the Tuskfish file handler.
 * @var         \Tfish\Logger $logger Instance of the Tuskfish error logger.
 */
class Database
{
    use Traits\IntegerCheck;
    use Traits\Language;
    use Traits\ValidateString;

    private $database;
    private $fileHandler;
    private $logger;

    /**
     * Constructor.
     *
     * @param Logger $logger An instance of the Tuskfish error logger class.
     * @param FileHandler $fileHandler An instance of the Tuskfish file handler class.
     */
    public function __construct(Logger $logger, FileHandler $fileHandler)
    {
        $this->logger = $logger;
        $this->fileHandler = $fileHandler;
        $this->connect();
    }

    /** No cloning permitted. */
    final public function __clone() {}

    /** Always close the connection on termination. **/
    public function __destruct()
    {
        $this->close();
    }

    /** No serialisation. */
    final public function __wakeup() {}

    /**
     * Enclose table and column identifiers in backticks to escape them.
     *
     * This method must only be used on TABLE and COLUMN names. Column values must be escaped
     * through the use of bound parameters.
     *
     * @param string $identifier Table or column name.
     * @return string Identifier encapsulated in backticks.
     */
    public function addBackticks(string $identifier): string
    {
        return '`' . $identifier . '`';
    }

    /**
     * Close the connection to the database.
     *
     * @return bool True on success false on failure.
     */
    public function close(): bool
    {
        $this->database = null;
        return true;
    }

    /**
     * Establish a connection to the database.
     *
     * Connection is deliberately non-persistent (persistence can break things if scripts terminate
     * unexpectedly).
     *
     * @return bool True on success, false on failure.
     */
    public function connect(): bool
    {
        if (\defined("TFISH_DATABASE")) {
            $this->database = new \PDO('sqlite:' . TFISH_DATABASE);

            if ($this->database) {
                // Set \PDO to throw exceptions every time it encounters an error.
                $this->database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                return true;
            }
        }

        return false;

    }

    /**
     * Create an SQLite database with random prefix and creates a language constant for it.
     *
     * Database name must be alphanumeric and underscore characters only. The database will
     * automatically be appended with the suffix .db
     *
     * @param string $dbName Database name.
     * @return string|bool Path to database file on success, false on failure.
     */
    public function create(string $dbName)
    {
        // Validate input parameters
        $dbName = $this->trimString($dbName);

        if ($this->isAlnumUnderscore($dbName)) {
            return $this->_create($dbName . '.db');
        } else {
            \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
            exit;
        }
    }

    /** @internal */
    private function _create(string $dbName)
    {
        // Generate a random prefix for the database filename to make it unpredictable.
        $prefix = \mt_rand();
        // Create database file and append a constant with the database path to config.php
        try {
            $dbPath = TFISH_DATABASE_PATH . $prefix . '_' . $dbName;
            $this->database = new \PDO('sqlite:' . $dbPath);
            $db_constant = PHP_EOL . 'if (!\defined("TFISH_DATABASE")) define("TFISH_DATABASE", "'
                    . $dbPath . '");' . PHP_EOL;
            $result = $this->fileHandler->appendToFile(TFISH_CONFIGURATION_PATH, $db_constant);

            if (!$result) {
                \trigger_error(TFISH_ERROR_FAILED_TO_APPEND_FILE, E_USER_NOTICE);
                return false;
            }

            return $dbPath;
        } catch (\PDOException $e) {
            $this->logger->logError($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
            return false;
        }
    }

    /**
     * Create a table in the database.
     *
     * Table names may only be alphanumeric characters. Column names are also alphanumeric but may
     * also contain underscores.
     *
     * @param string $table Table name (alphanumeric characters only).
     * @param array $columns Array of column names (keys) and types (values).
     * @param string|null $primaryKey Name of field to be used as primary key.
     * @return bool True on success, false on failure.
     */
    public function createTable(string $table, array $columns, string|null $primaryKey = null)
    {
        // Initialise
        $cleanPrimaryKey = null;
        $cleanColumns = [];

        // Validate input parameters
        $cleanTable = $this->validateTableName($table);

        if (\is_array($columns) && !empty($columns)) {
            $typeWhitelist = ["BLOB", "TEXT", "INTEGER", "NULL", "REAL"];

            foreach ($columns as $key => $value) {
                $key = $this->escapeIdentifier($key);

                if (!$this->isAlnumUnderscore($key)) {
                    \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
                    exit;
                }

                if (!\in_array($value, $typeWhitelist, true)) {
                    \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
                    exit;
                }

                $cleanColumns[$key] = $value;
                unset($key, $value);
            }
        } else {
            \trigger_error(TFISH_ERROR_NOT_ARRAY_OR_EMPTY, E_USER_ERROR);
            exit;
        }

        if (isset($primaryKey)) {
            $primaryKey = $this->escapeIdentifier($primaryKey);

            if (\array_key_exists($primaryKey, $cleanColumns)) {
                $cleanPrimaryKey = $this->isAlnumUnderscore($primaryKey)
                        ? $primaryKey : null;
            }

            if (!isset($cleanPrimaryKey)) {
                \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
                exit;
            }
        }

        // Proceed with the query
        if ($cleanPrimaryKey) {
            return $this->_createTable($cleanTable, $cleanColumns, $cleanPrimaryKey);
        } else {
            return $this->_createTable($cleanTable, $cleanColumns);
        }
    }

    /** @internal */
    private function _createTable(string $table_name, array $columns, string|null $primaryKey = null)
    {
        if (\mb_strlen($table_name, 'UTF-8') > 0 && \is_array($columns)) {
            $sql = "CREATE TABLE IF NOT EXISTS `" . $table_name . "` (";

            foreach ($columns as $key => $value) {
                $sql .= "`" . $key . "` " . $value . "";
                if (isset($primaryKey) && $primaryKey === $key) {
                    $sql .= " PRIMARY KEY";
                }
                $sql .= ", ";
            }

            $sql = \trim($sql, ', ');
            $sql .= ")";
            $statement = $this->preparedStatement($sql);
            $statement->execute();

            if ($statement) {
                return true;
            } else {
                \trigger_error(TFISH_ERROR_NO_STATEMENT, E_USER_ERROR);
            }
        }
    }

    /**
     * Delete single row from table based on its ID.
     *
     * @param string $table Name of table.
     * @param int $id ID of row to be deleted.
     * @return bool True on success false on failure.
     */
    public function delete(string $table, int $id)
    {
        $cleanTable = $this->validateTableName($table);
        $cleanId = $this->validateId($id);

        return $this->_delete($cleanTable, $cleanId);
    }

    /** @internal */
    private function _delete(string $table, int $id)
    {
        $sql = "DELETE FROM " . $this->addBackticks($table) . " WHERE `id` = :id";
        $statement = $this->preparedStatement($sql);

        if ($statement) {
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        } else {
            return false;
        }

        return $this->executeTransaction($statement);
    }

    /**
     * Delete multiple rows from a table according to criteria.
     *
     * For safety reasons criteria are required; the function will not unconditionally empty table.
     * Note that SQLite does not support DELETE with INNER JOIN or table alias. Therefore, you
     * cannot use tags as a criteria in deleteAll() (they will simply be ignored). It may be
     * possible to get around this restriction with a loop or subquery.
     *
     * @param string $table Name of table.
     * @param \Tfish\Criteria $criteria Criteria object used to build conditional database query.
     * @return bool True on success, false on failure.
     */
    public function deleteAll(string $table, Criteria $criteria)
    {
        $cleanTable = $this->validateTableName($table);
        $cleanCriteria = $this->validateCriteriaObject($criteria);

        return $this->_deleteAll($cleanTable, $cleanCriteria);
    }

    /** @internal */
    private function _deleteAll(string $table, Criteria $criteria)
    {
        // Set table.
        $sql = "DELETE FROM " . $this->addBackticks($table) . " ";

        // Set WHERE criteria.
        if ($criteria) {

            if (!empty($criteria->item)) {
                $sql .= "WHERE ";
            }

            if (\is_array($criteria->item)) {
                $pdoPlaceholders = [];
                $sql .= $this->renderSql($criteria);
                $pdoPlaceholders = $this->renderPdo($criteria);
            } else {
                \trigger_error(TFISH_ERROR_NOT_ARRAY, E_USER_ERROR);
            }

            // Set the sorting column and order (default is ascending), limit and offset.
            $sql .= $this->renderSort($criteria);
            $sql .= $this->renderLimitAndOffset($criteria);
        }

        // Prepare the statement and bind the values.
        $statement = $this->preparedStatement($sql);

        if ($criteria) {
            if (isset($pdoPlaceholders)) {
                $this->bindPdo($statement, $pdoPlaceholders);
            }

            $this->bindLimitAndOffset($statement, $criteria);
        }

        return $this->executeTransaction($statement);
    }

    /**
     * Escape delimiters for identifiers (table and column names).
     *
     * SQLite supports three styles of identifier delimitation:
     *
     * 1. Standard SQL double quotes: "
     * 2. MySQL style grave accents: `
     * 3. MS SQL style square brackets: []
     *
     * Escaping of delimiters where they are used as part of a table or column name is done by
     * doubling them, eg ` becomes ``. In order to safely escape table and column names ALL
     * three delimiter types must be escaped.
     *
     * Tuskfish policy is that table names can only contain alphanumeric characters (and column
     * names can only contain alphanumeric plus underscore characters) so delimiters should never
     * get into a query as part of an identifier. But just because we are paranoid they are
     * escaped here anyway.
     *
     * @param string $identifier Name of table or column.
     * @return string Escaped table or column name.
     */
    public function escapeIdentifier(string $identifier)
    {
        $cleanIdentifier = '';
        $identifier = $this->trimString($identifier);
        $identifier = \str_replace('"', '""', $identifier);
        $identifier = \str_replace('`', '``', $identifier);
        $identifier = \str_replace('[', '[[', $identifier);
        $cleanIdentifier = \str_replace(']', ']]', $identifier);

        return $cleanIdentifier;
    }

    /**
     * Execute a prepared statement within a transaction.
     *
     * The $statement parameter should be a prepared statement obtained via preparedStatement($sql).
     * Note that statement execution is within a transaction and rollback will occur if it fails.
     * This method should be used with database write operations (INSERT, UPDATE, DELETE).
     *
     * @param \PDOStatement $statement Prepared statement.
     * @return bool True on success, false on failure.
     */
    public function executeTransaction(\PDOStatement $statement)
    {
        try {
            $this->database->beginTransaction();
            $statement->execute();
            $this->database->commit();
        } catch (\PDOException $e) {
            $this->database->rollBack();
            $this->logger->logError((int) $e->getCode(), $e->getMessage(), $e->getFile(), (int) $e->getLine());
            return false;
        }

        return true;
    }

    public function beginTransaction()
    {
        $this->database->beginTransaction();
    }

    public function commit()
    {
        $this->database->commit();
    }

    public function rollback()
    {
        $this->database->rollback();
    }

    /**
     * Insert a single row into the database within a transaction.
     *
     * @param string $table Name of table.
     * @param array $keyValues Column names and values to be inserted.
     * @return bool True on success, false on failure.
     */
    public function insert(string $table, array $keyValues)
    {
        $cleanTable = $this->validateTableName($table);
        $cleanKeys = $this->validateKeys($keyValues);

        return $this->_insert($cleanTable, $cleanKeys);
    }

    /** @internal */
    private function _insert(string $table, array $keyValues)
    {
        $pdoPlaceholders = '';
        $sql = "INSERT INTO " . $this->addBackticks($table) . " (";

        // Prepare statement
        foreach ($keyValues as $key => $value) {
            $pdoPlaceholders .= ":" . $key . ", ";
            $sql .= $this->addBackticks($key) . ", ";
            unset($key, $value);
        }

        $pdoPlaceholders = \trim($pdoPlaceholders, ', ');
        $sql = \trim($sql, ', ');
        $sql .= ") VALUES (" . $pdoPlaceholders . ")";

        // Prepare the statement and bind the values.
        $statement = $this->database->prepare($sql);

        foreach ($keyValues as $key => $value) {
            $statement->bindValue(":" . $key, $value, $this->setType($value));
            unset($key, $value);
        }

        return $this->executeTransaction($statement);
    }

   /**
    * Returns the maximum value of an integer column (eg. an ID).
    *
    * @param string $column
    * @param string $table
    * @return int Maximum value of column
    */
    public function maxVal(string $column, string $table) {
        $cleanColumn = $this->validateColumns([$column]);
        $cleanTable = $this->validateTableName($table);
        $cleanColumn = reset($cleanColumn);

        $sql = "SELECT MAX(" . $this->addBackticks($column) . ") as max FROM " . $this->addBackticks($table);
        $statement = $this->preparedStatement($sql);
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_OBJ);

        return (int) $row->max;
    }

    /**
     * Retrieves the ID of the last row inserted into the database.
     *
     * Used primarily to grab the ID of newly created content objects so that their accompanying
     * taglinks can be correctly associated to them.
     *
     * @return int|bool Row ID on success, false on failure.
     */
    public function lastInsertId()
    {
        if ($this->database->lastInsertId()) {
            return (int) $this->database->lastInsertId();
        } else {
            return false;
        }
    }

    /**
     * Return a \PDO statement object.
     *
     * Statement object can be used to bind values or parameters and execute queries, thereby
     * mitigating direct SQL injection attacks.
     *
     * @param string $sql SQL statement.
     * @return \PDOStatement \PDOStatement object on success \PDOException object on failure.
     */
    public function preparedStatement(string $sql)
    {
        return $this->database->prepare($sql);
    }

    /**
     * Generate an SQL WHERE clause with \PDO placeholders based on criteria items.
     *
     * Loop through the criteria items building a list of \PDO placeholders together
     * with the SQL. These will be used to bind the values in the statement to prevent
     * SQL injection. Note that values are NOT inserted into the SQL directly.
     *
     * Enclose column identifiers in backticks to escape them. Link criteria items with AND/OR
     * except on the last iteration ($count-1).
     *
     * @param \Tfish\Criteria $criteria Query composer object containing parameters for building a query.
     * @return string $sql SQL query fragment.
     */
    private function renderSql(Criteria $criteria)
    {
        $sql = '';
        $count = \count($criteria->item);

        if ($count) {
            $sql = "(";

            for ($i = 0; $i < $count; $i++) {
                $sql .= "`" . $this->escapeIdentifier($criteria->item[$i]->column) . "` "
                        . $this->renderOperator($criteria->item[$i]->operator) . " :placeholder"
                        . (string) $i;

                if ($i < ($count - 1)) {
                    $sql .= " " . $this->renderAndOr($criteria->condition[$i]) . " ";
                }
            }
            $sql .= ") ";
        }

        return $sql;
    }

    /**
     * Generate an array of \PDO placeholders based on criteria items.
     *
     * Use this function to get a list of placeholders generated by renderSql(). The two functions
     * should be used together; use renderSql() to create a WHERE clause with named placeholders,
     * and renderPdo() to get a list of the named placeholders so that you can bind values to them.
     *
     * @param \Tfish\Criteria $criteria Query composer object containing parameters for building a query.
     * @return array $pdoPlaceholders Array of \PDO placeholders used for building SQL query.
     */
    private function renderPdo(Criteria $criteria)
    {
        $pdoPlaceholders = [];
        $count = \count($criteria->item);

        for ($i = 0; $i < $count; $i++) {
            $pdoPlaceholders[":placeholder" . (string) $i] = $criteria->item[$i]->value;
        }

        return $pdoPlaceholders;
    }

    /**
     * Bind values to \PDO placeholders based on criteria items.
     *
     * @param \PDOStatement $statement \PDO statement object.
     * @param array $pdoPlaceholders Array of \PDO placeholders for columns.
     */
    private function bindPdo(\PDOStatement $statement, array $pdoPlaceholders)
    {
        if (!empty($pdoPlaceholders)) {
            foreach ($pdoPlaceholders as $placeholder => $value) {
                $statement->bindValue($placeholder, $value, $this->setType($value));
                unset($placeholder);
            }
        }
    }

    /**
     * Validates and renders an "AND" or "OR" for use in a query.
     *
     * @param string $condition Must be either "AND" or "OR".
     * @return string Validated AND/OR condition.
     */
    private function renderAndOr(string $condition)
    {
        $cleanCondition = $this->trimString($condition);

        if ($cleanCondition === "AND" || $cleanCondition === "OR") {
            return $cleanCondition;
        } else {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }
    }

    /**
     * Renders the primary and secondary sorting section of the SQL statement.
     *
     * @param \Tfish\Criteria $criteria Query composer object for the query.
     */
    private function renderSort(Criteria $criteria)
    {
        $sql = '';

        if ($criteria->sort) {
            $sql = "ORDER BY `t1`."
                    . $this->addBackticks($this->escapeIdentifier($criteria->sort)) . " ";
            $sql .= $criteria->order === "DESC" ? "DESC" : "ASC";

            if ($criteria->secondarySort && ($criteria->secondarySort != $criteria->sort)) {
                $sql .= ", `t1`."
                     . $this->addBackticks($this->escapeIdentifier($criteria->secondarySort)) . " ";
                $sql .= $criteria->secondaryOrder === "DESC" ? "DESC" : "ASC";
            }
        }

        return $sql;
    }

    /**
     * Renders the limit and offset sections of the SQL statement under construction.
     *
     * @param \Tfish\Criteria $criteria Query composer object.
     * @return string SQL statement with limit/offset added.
     */
    private function renderLimitAndOffset(Criteria $criteria)
    {
        $sql = '';

        if ($criteria->offset && $criteria->limit) {
            $sql = " LIMIT :limit OFFSET :offset";
        } elseif ($criteria->limit) {
            $sql = " LIMIT :limit";
        }

        return $sql;
    }

    /**
     * Binds values of limit and offset to \PDO placeholders.
     *
     * @param \PDOStatement $statement \PDO statement object.
     * @param \Tfish\Criteria $criteria Query composer object.
     */
    private function bindLimitAndOffset(\PDOStatement $statement, Criteria $criteria)
    {
        if ($criteria->limit && $criteria->offset) {
            $statement->bindValue(':limit', $criteria->limit, \PDO::PARAM_INT);
            $statement->bindValue(':offset', $criteria->offset, \PDO::PARAM_INT);
        } elseif ($criteria->limit) {
            $statement->bindValue(':limit', $criteria->limit, \PDO::PARAM_INT);
        }
    }

    /**
     * Renders the GROUP BY clause of the query.
     *
     * @param \Tfish\Criteria $criteria Query composer object.
     * @return string SQL statement.
     */
    private function renderGroupBy(Criteria $criteria)
    {
        $sql = '';

        if ($criteria->groupBy) {
            $sql = " GROUP BY `t1`." . $this->addBackticks($this->escapeIdentifier($criteria->groupBy));
        }

        return $sql;
    }

    /**
     * Validates and renders an expression operator for use in query.
     *
     * @param string $operator
     * @return string Validated operator.
     */
    private function renderOperator(string $operator)
    {
        $cleanOperator = $this->trimString($operator);
        $permittedOperators = ['=', '==', '<', '<=', '>', '>=', '!=', '<>', 'LIKE'];

        if (\in_array($cleanOperator, $permittedOperators, true)) {
            return $cleanOperator;
        } else {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

    }

    /**
     * Generate an SQL WHERE clause with \PDO placeholders based on the tag property.
     *
     * Loop through the criteria->tags building a list of \PDO placeholders together
     * with the SQL. These will be used to bind the values in the statement to prevent
     * SQL injection. Note that values are NOT inserted into the SQL directly.
     *
     * @param \Tfish\Criteria $criteria Query composer object containing parameters for building a query.
     * @return string $sql SQL query fragment.
     */
    private function renderTagSql(Criteria $criteria)
    {
        $sql = '';
        $count = \count($criteria->tag);

        if ($count === 1) {
            $sql .= "`taglink`.`tagId` = :tag0 ";
        } elseif ($count > 1) {
            $sql .= "`taglink`.`tagId` IN (";

            for ($i = 0; $i < \count($criteria->tag); $i++) {
                $sql .= ':tag' . (string) $i . ',';
            }

            $sql = \rtrim($sql, ',');
            $sql .= ") ";
        }

        return $sql;
    }

    /**
     * Generate an array of \PDO placeholders based on the tag property.
     *
     * Use this function to get a list of placeholders generated by renderTagSql(). The two
     * functions should be used together; use renderTagSql() to create a WHERE clause with named
     * placeholders, and renderTagPdo() to get a list of the named placeholders so that you can
     * bind values to them.
     *
     * @param \Tfish\Criteria $criteria Query composer object containing parameters for building a query.
     * @return array $tagPlaceholders Array of \PDO placeholders used for building SQL query.
     */
    private function renderTagPdo(Criteria $criteria)
    {
        $tagPlaceholders = [];

        for ($i = 0; $i < \count($criteria->tag); $i++) {
            $tagPlaceholders[":tag" . (string) $i] = (int) $criteria->tag[$i];
        }

        return $tagPlaceholders;
    }

    /**
     * Bind values to \PDO placeholders for tags.
     *
     * @param \PDOStatement $statement \PDO statement object.
     * @param array $tagPlaceholders Array of \PDO placeholders for tags.
     */
    private function bindTagPdo(\PDOStatement $statement, array $tagPlaceholders)
    {
        foreach ($tagPlaceholders as $tag_placeholder => $value) {
            $statement->bindValue($tag_placeholder, $value, \PDO::PARAM_INT);
            unset($placeholder);
        }
    }

    /**
     * Prepare and execute a select query.
     *
     * Returns a \PDO statement object, from which results can be extracted with standard \PDO calls.
     *
     * @param string $table Name of table.
     * @param \Tfish\Criteria|null $criteria Query composer object used to build conditional database query.
     * @param array|null $columns Names of database columns to be selected.
     * @return \PDOStatement \PDOStatement object on success \PDOException on failure.
     */
    public function select(string $table, Criteria|null $criteria = null, array|null $columns = null)
    {
        $cleanTable = $this->validateTableName($table);
        $cleanCriteria = isset($criteria) ? $this->validateCriteriaObject($criteria) : null;
        $cleanColumns = isset($columns) ? $this->validateColumns($columns) : [];

        return $this->_select($cleanTable, $cleanColumns, $cleanCriteria);
    }

    /** @internal */
    private function _select(string $table, array $columns, Criteria|null $criteria = null)
    {
        // Specify operation.
        $sql = "SELECT ";

        // Select columns.
        if ($columns) {
            foreach ($columns as $column) {
                $sql .= '`t1`.' . $this->addBackticks($column) . ", ";
            }

            $sql = \rtrim($sql, ", ") . " ";
        } else {
            $sql .= "`t1`.* ";
        }

        // Set table.
        $sql .= "FROM " . $this->addBackticks($table) . " AS `t1` ";

        // Check if a tag filter has been applied (JOIN is required).
        if (isset($criteria) && !empty($criteria->tag)) {
            $sql .= $this->_renderTagJoin($table);
        }

        // Set WHERE criteria.
        if (isset($criteria)) {
            if (!empty($criteria->item) || !empty($criteria->tag)) {
                $sql .= "WHERE ";
            }

            if (\is_array($criteria->item)) {
                $pdoPlaceholders = [];
                $sql .= $this->renderSql($criteria);
                $pdoPlaceholders = $this->renderPdo($criteria);
            } else {
                \trigger_error(TFISH_ERROR_NOT_ARRAY, E_USER_ERROR);
            }

            if (!empty($criteria->item) && !empty($criteria->tag)) {
                $sql .= "AND ";
            }

            // Set tag(s).
            if (!empty($criteria->tag)) {
                $sql .= $this->renderTagSql($criteria);
                $tagPlaceholders = $this->renderTagPdo($criteria);
            }

            // Set GROUP BY.
            $sql .= $this->renderGroupBy($criteria);

            // Set the sort column and order (default is ascending), limit and offset.
            $sql .= $this->renderSort($criteria);
            $sql .= $this->renderLimitAndOffset($criteria);
        }

        // Prepare the statement and bind the values.
        $statement = $this->preparedStatement($sql);

        if ($statement && isset($criteria)) {
            if (isset($pdoPlaceholders)) {
                $this->bindPdo($statement, $pdoPlaceholders);
            }

            if (isset($tagPlaceholders)) {
                $this->bindTagPdo($statement, $tagPlaceholders);
            }

            $this->bindLimitAndOffset($statement, $criteria);
        }

        // Execute the statement.
        $statement->execute();

        // Return the statement object, results can be extracted as required with standard \PDO calls.
        return $statement;
    }

    /**
     * Count the number of rows matching a set of conditions.
     *
     * @param string $table Name of table.
     * @param \Tfish\Criteria|null $criteria Query composer object used to build conditional database query.
     * @param string $column Name of column.
     * @return int|object Row count on success, \PDOException object on failure.
     */
    public function selectCount(string $table, Criteria|null $criteria = null, string $column = '')
    {
        $cleanTable = $this->validateTableName($table);
        $cleanCriteria = isset($criteria) ? $this->validateCriteriaObject($criteria) : null;

        if ($column) {
            $column = $this->escapeIdentifier($column);

            if ($this->isAlnumUnderscore($column)) {
                $cleanColumn = $column;
            } else {
                \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
                exit;
            }
        } else {
            $cleanColumn = "*";
        }

        return $this->_selectCount($cleanTable, $cleanCriteria, $cleanColumn);
    }

    /** @internal */
    private function _selectCount(string $table, Criteria $criteria, string $column)
    {
        // Specify operation and column
        $sql = "SELECT COUNT(";
        $sql .= $column = "*" ? $column : $this->addBackticks($column);
        $sql .= ") ";

        // Set table.
        $sql .= "FROM " . $this->addBackticks($table) . " AS `t1` ";

        // Check if a tag filter has been applied (JOIN is required).
        if (isset($criteria) && !empty($criteria->tag)) {
            $sql .= $this->_renderTagJoin($table);
        }

        // Set WHERE criteria.
        if (isset($criteria)) {

            if (!empty($criteria->item) || !empty($criteria->tag)) {
                $sql .= "WHERE ";
            }

            if (\is_array($criteria->item)) {
                $pdoPlaceholders = [];
                $sql .= $this->renderSql($criteria);
                $pdoPlaceholders = $this->renderPdo($criteria);
            } else {
                \trigger_error(TFISH_ERROR_NOT_ARRAY, E_USER_ERROR);
                exit;
            }

            if (!empty($criteria->item) && !empty($criteria->tag)) {
                $sql .= "AND ";
            }

            // Set tag(s).
            if (!empty($criteria->tag)) {
                $sql .= $this->renderTagSql($criteria);
                $tagPlaceholders = $this->renderTagPdo($criteria);
            }
        }

        // Prepare the statement and bind the values.
        $statement = $this->preparedStatement($sql);

        if ($statement && isset($criteria) && isset($pdoPlaceholders)) {
            $this->bindPdo($statement, $pdoPlaceholders);
        }

        if (isset($tagPlaceholders)) {
            $this->bindTagPdo($statement, $tagPlaceholders);
        }

        // Execute the statement.
        $statement->execute();

        // Return the row count (integer) by retrieving the row.
        $count = $statement->fetch(\PDO::FETCH_NUM);

        return (int) reset($count);
    }

    /**
     * Select results from the database but remove duplicate rows.
     *
     * Use the $columns array to specify which fields you want to filter the results by.
     *
     * @param string $table Name of table.
     * @param array $columns Name of columns to filter results by.
     * @param \Tfish\Criteria|null $criteria Query composer object used to build conditional database query.
     * @return \PDOStatement \PDOStatement on success, \PDOException on failure.
     */
    public function selectDistinct(string $table, array $columns, Criteria|null $criteria = null)
    {
        // Validate the tablename (alphanumeric characters only).
        $cleanTable = $this->validateTableName($table);
        $cleanCriteria = isset($criteria) ? $this->validateCriteriaObject($criteria) : null;
        $cleanColumns = !empty($columns) ? $this->validateColumns($columns) : [];

        return $this->_selectDistinct($cleanTable, $cleanColumns, $cleanCriteria);
    }

    /** @internal */
    private function _selectDistinct(string $table, array $columns, Criteria $criteria)
    {
        // Specify operation
        $sql = "SELECT DISTINCT ";

        // Select columns.
        foreach ($columns as $column) {
            $sql .= '`t1`.' . $this->addBackticks($column) . ", ";
        }

        $sql = \rtrim($sql, ", ") . " ";

        // Set table.
        $sql .= "FROM " . $this->addBackticks($table) . " AS `t1` ";

        // Set parameters.
        if (isset($criteria)) {

            if (!empty($criteria->item) || !empty($criteria->tag)) {
                $sql .= "WHERE ";
            }

            if (\is_array($criteria->item)) {
                $pdoPlaceholders = [];
                $sql .= $this->renderSql($criteria);
                $pdoPlaceholders = $this->renderPdo($criteria);
            } else {
                \trigger_error(TFISH_ERROR_NOT_ARRAY, E_USER_ERROR);
                exit;
            }

            if (!empty($criteria->item) && !empty($criteria->tag)) {
                $sql .= "AND ";
            }

            // Set tag(s).
            if (!empty($criteria->tag)) {
                $sql .= $this->renderTagSql($criteria);
                $tagPlaceholders = $this->renderTagPdo($criteria);
            }

            // Set GROUP BY.
            $sql .= $this->renderGroupBy($criteria);

            // Set the sort column and order (default is ascending), limit and offset.
            $sql .= $this->renderSort($criteria);
            $sql .= $this->renderLimitAndOffset($criteria);
        }

        // Prepare the statement and bind the values.
        $statement = $this->preparedStatement($sql);

        if ($statement && isset($criteria)) {
            if (isset($pdoPlaceholders)) {
                $this->bindPdo($statement, $pdoPlaceholders);
            }

            $this->bindLimitAndOffset($statement, $criteria);
        }

        if (isset($tagPlaceholders)) {
                $this->bindTagPdo($statement, $tagPlaceholders);
            }

        $statement->execute();

        return $statement;
    }

    /**
     * Toggle the online status of a column between 0 and 1, use for columns representing booleans.
     *
     * Convention: $id and $lang MUST represent columns called 'id' and 'language' for any table
     * you run this method against.
     *
     * @param int $id ID of the row to update.
     * @param string $lang 2-letter ISO 639-1 language code.
     * @param string $table Name of table.
     * @param string $column Name of column to update.
     * @return bool True on success, false on failure.
     */
    public function toggleBoolean(int $id, string $table, string $column, string $lang = '')
    {
        $cleanId = $this->validateId($id);
        $cleanTable = $this->validateTableName($table);
        $cleanColumn = $this->validateColumns([$column]);
        $cleanColumn = reset($cleanColumn);
        $cleanLang = $this->validateLanguage($lang);

        return $this->_toggleBoolean($cleanId, $cleanTable, $cleanColumn, $cleanLang);
    }

    /** @internal */
    private function _toggleBoolean(int $id, string $table, string $column, string $language)
    {
        $sql = "UPDATE " . $this->addBackticks($table) . " SET " . $this->addBackticks($column)
                . " = CASE WHEN " . $this->addBackticks($column)
                . " = 1 THEN 0 ELSE 1 END WHERE `id` = :id";

        if (!empty($language)) {
            $sql .= " AND `language` = :language";
        }

        // Prepare the statement and bind the ID value.
        $statement = $this->preparedStatement($sql);

        if ($statement) {
            $statement->bindValue(":id", $id, \PDO::PARAM_INT);

            if (!empty($language)) {
                $statement->bindValue(":language", $language, \PDO::PARAM_STR);
            }
        }

        return $this->executeTransaction($statement);
    }

    /**
     * Increment a content object counter field by one.
     *
     * Call this method when the full description of an individual content object is viewed, or
     * when a related media file is downloaded.
     *
     * @param int $id ID of content object.
     * @param string $table Name of table.
     * @param string $column Name of column.
     * @return bool True on success false on failure.
     */
    public function updateCounter(int $id, string $table, string $column, $lang = '')
    {
        $cleanId = $this->validateId($id);
        $cleanTable = $this->validateTableName($table);
        $cleanColumn = $this->validateColumns([$column]);
        $cleanColumn = reset($cleanColumn);
        $cleanLang = $this->validateLanguage($lang);

        return $this->_updateCounter($cleanId, $cleanTable, $cleanColumn, $cleanLang);
    }

    /** @internal */
    private function _updateCounter(int $id, string $table, string $column, string $language)
    {
        $sql = "UPDATE " . $this->addBackticks($table) . " SET " . $this->addBackticks($column)
                . " = " . $this->addBackticks($column) . " + 1 WHERE `id` = :id";
        if (!empty($language)) {
            $sql .= " AND `language` = :language";
        }

        // Prepare the statement and bind the ID value.
        $statement = $this->preparedStatement($sql);

        if ($statement) {
            $statement->bindValue(":id", $id, \PDO::PARAM_INT);

            if (!empty($language)) {
                $statement->bindValue(":language", $language, \PDO::PARAM_STR);
            }
        }

        return $this->executeTransaction($statement);
    }

    /**
     * Update a single row in the database.
     *
     * @param string $table Name of table.
     * @param int $id ID of row to update.
     * @param array $keyValues Array of column names and values to update.
     * @return bool True on success, false on failure.
     */
    public function update(string $table, int $id, array $keyValues)
    {
        $cleanTable = $this->validateTableName($table);
        $cleanId = $this->validateId($id);
        $cleanKeys = $this->validateKeys($keyValues);

        return $this->_update($cleanTable, $cleanId, $cleanKeys);
    }

    /** @internal */
    private function _update(string $table, int $id, array $keyValues)
    {
        // Prepare the statement
        $sql = "UPDATE " . $this->addBackticks($table) . " SET ";

        foreach ($keyValues as $key => $value) {
            $sql .= $this->addBackticks($key) . " = :" . $key . ", ";
        }

        $sql = \trim($sql, ", ");
        $sql .= " WHERE `id` = :id";

        // Prepare the statement and bind the values.
        $statement = $this->preparedStatement($sql);

        if ($statement) {
            $statement->bindValue(":id", $id, \PDO::PARAM_INT);

            foreach ($keyValues as $key => $value) {
                $type = \gettype($value);
                $statement->bindValue(":" . $key, $value, $this->setType($type));
                unset($type);
            }
        } else {
            return false;
        }

        return $this->executeTransaction($statement);
    }

    /**
     * Update multiple rows in a table according to criteria.
     *
     * Note that SQLite does not support INNER JOIN or table aliases in UPDATE; therefore it is
     * not possible to use tags as a criteria in updateAll() at present. It may be possible to get
     * around this limitation with a subquery. But given that the use case would be unusual /
     * marginal it is probably just easier to work around it.
     *
     * @param string $table Name of table.
     * @param array $keyValues Array of column names and values to update.
     * @param \Tfish\Criteria|null $criteria Query composer object used to build conditional database query.
     */
    public function updateAll(string $table, array $keyValues, Criteria|null $criteria = null)
    {
        $cleanTable = $this->validateTableName($table);
        $cleanKeys = $this->validateKeys($keyValues);

        if (isset($criteria)) {
            $cleanCriteria = $this->validateCriteriaObject($criteria);
        } else {
            $cleanCriteria = null;
        }

        return $this->_updateAll($cleanTable, $cleanKeys, $cleanCriteria);
    }

    /** @internal */
    private function _updateAll(string $table, array $keyValues, Criteria $criteria)
    {
        // Set table.
        $sql = "UPDATE " . $this->addBackticks($table) . " SET ";

        // Set key values.
        foreach ($keyValues as $key => $value) {
            $sql .= $this->addBackticks($key) . " = :" . $key . ", ";
        }

        $sql = \rtrim($sql, ", ") . " ";

        // Set WHERE criteria.
        if (isset($criteria)) {

            if (!empty($criteria->item)) {
                $sql .= "WHERE ";
            }

            if (\is_array($criteria->item)) {
                $pdoPlaceholders = [];
                $sql .= $this->renderSql($criteria);
                $pdoPlaceholders = $this->renderPdo($criteria);
            } else {
                \trigger_error(TFISH_ERROR_NOT_ARRAY, E_USER_ERROR);
                exit;
            }
        }

        // Prepare the statement and bind the values.
        $statement = $this->preparedStatement($sql);

        foreach ($keyValues as $key => $value) {
            $statement->bindValue(':' . $key, $value, $this->setType($value));
            unset($key, $value);
        }

        if (isset($criteria) && isset($pdoPlaceholders)) {
            $this->bindPdo($statement, $pdoPlaceholders);
        }

        return $this->executeTransaction($statement);
    }

    /**
     * Helper method to set appropriate \PDO predefined constants in bindValue() and bindParam().
     *
     * This method does not support arrays, objects, or resources. If an unsupported data type
     * is passed, PHP will throw a TypeError or the method will trigger an error for unexpected types.
     * Note that if you pass in an unexpected data type (i.e., one that clashes with a column type
     * definition), \PDO will throw an error.
     *
     * @param string|int|float|bool|null $data Input data to be type set.
     * @return int \PDO data type constant.
     */
    public function setType(string|int|float|bool|null $data): int
    {
        return match (true) {
            \is_int($data) => \PDO::PARAM_INT,
            \is_string($data), \is_float($data) => \PDO::PARAM_STR,
            \is_bool($data) => \PDO::PARAM_BOOL,
            \is_null($data) => \PDO::PARAM_NULL,
            default => \trigger_error(TFISH_ERROR_ILLEGAL_TYPE, E_USER_ERROR),
        };
    }

    /**
     * Renders a JOIN component of an SQL query for tagged content.
     *
     * If the $criteria for a query include tag(s), the object table must have a JOIN to the
     * taglinks table in order to sort the content.
     *
     * @internal
     * @param string $table Name of table.
     * @return string $sql SQL query fragment.
     */
    private function _renderTagJoin(string $table)
    {
        $sql = "INNER JOIN `taglink` ON `t1`.`id` = `taglink`.`contentId` ";

        return $sql;
    }

    /**
     * Validates the properties of a Criteria object to be used in constructing a query.
     *
     * @param \Tfish\Criteria $criteria Query composer object.
     * @return \Tfish\Criteria Validated Criteria object.
     */
    public function validateCriteriaObject(Criteria $criteria)
    {
        if ($criteria->item) {
            if (!\is_array($criteria->item)) {
                \trigger_error(TFISH_ERROR_NOT_ARRAY, E_USER_ERROR);
                exit;
            }

            if (empty($criteria->condition)) {
                \trigger_error(TFISH_ERROR_REQUIRED_PROPERTY_NOT_SET, E_USER_ERROR);
                exit;
            }

            if (!\is_array($criteria->condition)) {
                \trigger_error(TFISH_ERROR_NOT_ARRAY, E_USER_ERROR);
                exit;
            }

            if (\count($criteria->item) != \count($criteria->condition)) {
                \trigger_error(TFISH_ERROR_COUNT_MISMATCH, E_USER_ERROR);
                exit;
            }

            foreach ($criteria->item as $item) {
                if (!\is_a($item, '\Tfish\CriteriaItem')) {
                    \trigger_error(TFISH_ERROR_NOT_CRITERIA_ITEM_OBJECT, E_USER_ERROR);
                    exit;
                }

                if (!$this->isAlnumUnderscore($item->column)) {
                    \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
                    exit;
                }

                if ($item->operator && !\in_array($item->operator, $item->listPermittedOperators(),
                        true)) {
                    \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
                    exit;
                }
            }

            foreach ($criteria->condition as $condition) {
                if ($condition != "AND" && $condition != "OR") {
                    \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
                    exit;
                }
            }
        }

        if ($criteria->groupBy && !$this->isAlnumUnderscore($criteria->groupBy)) {
            \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
            exit;
        }

        if ($criteria->limit && !$this->isInt($criteria->limit, 1)) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
            exit;
        }

        if ($criteria->offset && !$this->isInt($criteria->offset, 0)) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
            exit;
        }

        if ($criteria->sort && !$this->isAlnumUnderscore($criteria->sort)) {
            \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
            exit;
        }

        if ($criteria->order &&
                ($criteria->order != "ASC" && $criteria->order != "DESC")) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
            exit;
        }

        if ($criteria->secondarySort && !$this->isAlnumUnderscore($criteria->secondarySort)) {
            \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
            exit;
        }

        if ($criteria->secondaryOrder &&
                ($criteria->secondaryOrder != "ASC" && $criteria->secondaryOrder != "DESC")) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
            exit;
        }

        return $criteria;
    }

    /**
     * Validate and escape column names to be used in constructing a database query.
     *
     * @param array $columns Array of unescaped column names.
     * @return array Array of valid, escaped column names
     */
    public function validateColumns(array $columns)
    {
        $cleanColumns = [];

        if (\is_array($columns) && !empty($columns)) {
            foreach ($columns as $column) {
                $column = $this->escapeIdentifier($column);

                if ($this->isAlnumUnderscore($column)) {
                    $cleanColumns[] = $column;
                } else {
                    \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
                    exit;
                }

                unset($column);
            }

            return $cleanColumns;
        } else {
            \trigger_error(TFISH_ERROR_NOT_ARRAY, E_USER_ERROR);
            exit;
        }
    }

    /**
     * Validates and sanitises an ID to be used in constructing a database query.
     *
     * @param int $id Input ID to be tested.
     * @return int $id Validated ID.
     */
    public function validateId(int $id)
    {
        $cleanId = (int) $id;
        if ($this->isInt($cleanId, 1)) {
            return $cleanId;
        } else {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
            exit;
        }
    }

    /**
     * Validate and escapes keys to be used in constructing a database query.
     *
     * Keys may only consist of alphanumeric and underscore characters. SQLite identifier delimiters
     * are escaped.
     *
     * @param array $keyValues Array of unescaped keys.
     * @return array Array of valid and escaped keys.
     */
    public function validateKeys(array $keyValues)
    {
        $cleanKeys = [];

        if (\is_array($keyValues) && !empty($keyValues)) {
            foreach ($keyValues as $key => $value) {
                $key = $this->escapeIdentifier($key);

                if ($this->isAlnumUnderscore($key)) {
                    $cleanKeys[$key] = $value;
                } else {
                    \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
                    exit;
                }

                unset($key, $value);
            }

            return $cleanKeys;
        } else {
            \trigger_error(TFISH_ERROR_NOT_ARRAY_OR_EMPTY, E_USER_ERROR);
            exit;
        }
    }

    /**
     * Validate and escape a table name to be used in constructing a database query.
     *
     * @param string $tableName Table name to be checked.
     * @return string Valid and escaped table name.
     */
    public function validateTableName(string $tableName)
    {
        $tableName = $this->escapeIdentifier($tableName);

        if ($this->isAlnum($tableName)) {
            return $tableName;
        } else {
            \trigger_error(TFISH_ERROR_NOT_ALNUM, E_USER_ERROR);
            exit;
        }
    }

    /**
     * Validate and escape a language code to be used in constructing a database query.
     *
     * @param string $lang 2-letter ISO language code.
     * @return string Valid and escaped ISO code or empty string.
     */
    public function validateLanguage(string $lang)
    {
        $language = $this->escapeIdentifier($lang);

        if (empty($language)) return '';

        if (!\array_key_exists($lang, $this->listLanguages())) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
            exit;
        }

        return $language;
    }
}
