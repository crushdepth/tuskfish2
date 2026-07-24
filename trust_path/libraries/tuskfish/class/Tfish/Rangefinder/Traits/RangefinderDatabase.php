<?php

declare(strict_types=1);

namespace Tfish\Rangefinder\Traits;

/**
 * \Tfish\Rangefinder\Traits\RangefinderDatabase trait file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

/**
 * Shared connection and query helpers for the Rangefinder occurrence database.
 *
 * Provides a read-only PDO connection to the occurrence SQLite database, which is separate from
 * the main Tuskfish site database and is a full rebuild from source (Darwin Core archives ->
 * build_db.py). Holding it as its own read-only connection preserves that pipeline and keeps CMS
 * data isolated from occurrence data; the occurrence schema is never imported into Tuskfish's
 * own tables.
 *
 * All access goes through select(), which prepares and binds every statement — no query string is
 * ever assembled from caller input. Where a query must vary structurally (a column to sort on, a
 * filter to include), the variation is chosen from a fixed whitelist in the calling model and the
 * *values* are still bound.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 * @var         ?\PDO $occurrenceDb Connection to the Rangefinder occurrence database.
 * @uses        \Tfish\Logger $logger Host class must provide a logger property.
 */
trait RangefinderDatabase
{
    private ?\PDO $occurrenceDb = null;

    /**
     * Open a read-only connection to the occurrence database.
     *
     * @return  bool True on success, false on failure.
     */
    public function connect(): bool
    {
        $dbPath = TFISH_DATABASE_PATH . TFISH_RANGEFINDER_DB;

        if (!\is_file($dbPath)) {
            $this->logger->logError(0, 'Occurrence database not found: ' . $dbPath, __FILE__, __LINE__);
            return false;
        }

        try {
            // Open read-only where the driver supports it: this database is reference data the site
            // only ever reads (it is regenerated wholesale by build_db.py), so the connection should
            // be incapable of writing it even if a future query bug tried to. PHP 8.5 moved these
            // constants onto the Pdo\Sqlite class and deprecated the old PDO::SQLITE_* spellings;
            // prefer the new ones where present and fall back to the old ones, so the code is
            // correct across 7.3-8.4 and 8.5+ alike. If neither is defined (an older/!sqlite driver)
            // the connection simply opens read-write.
            $options = [];

            if (\defined('Pdo\Sqlite::ATTR_OPEN_FLAGS')) {
                $options[\Pdo\Sqlite::ATTR_OPEN_FLAGS] = \Pdo\Sqlite::OPEN_READONLY;
            } elseif (\defined('PDO::SQLITE_ATTR_OPEN_FLAGS')) {
                $options[\PDO::SQLITE_ATTR_OPEN_FLAGS] = \PDO::SQLITE_OPEN_READONLY;
            }

            $this->occurrenceDb = new \PDO('sqlite:' . $dbPath, null, null, $options);
            $this->occurrenceDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->occurrenceDb->setAttribute(\PDO::ATTR_TIMEOUT, 5);
        } catch (\PDOException $e) {
            $this->logger->logError((int) $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
            return false;
        }

        return true;
    }

    /**
     * Whether the occurrence database is connected and available for querying.
     *
     * @return  bool True if connected.
     */
    public function isConnected(): bool
    {
        return $this->occurrenceDb instanceof \PDO;
    }

    /**
     * Last-modified time of the occurrence database file, as a Unix timestamp.
     *
     * The database is never written in place: it is rebuilt wholesale from the Darwin Core
     * archives and copied in, so its mtime changes if and only if the data changes. That makes it
     * an exact, free validator for HTTP caching — an ETag derived from it invalidates every
     * browser copy the moment new data is deployed, with no manual purge step to forget. A flat
     * max-age cannot do that: it would keep serving superseded records for its full duration with
     * no way to recall them.
     *
     * @return  int Unix timestamp, or 0 if the file is unreadable.
     */
    public function databaseTimestamp(): int
    {
        $dbPath = TFISH_DATABASE_PATH . TFISH_RANGEFINDER_DB;
        $mtime = \is_file($dbPath) ? \filemtime($dbPath) : false;

        return $mtime === false ? 0 : $mtime;
    }

    /**
     * Run a read-only SELECT and return all rows as associative arrays.
     *
     * The statement is always prepared and its parameters bound; callers must never interpolate
     * input into $sql. Returns an empty array (and logs) on failure, so a page degrades to empty
     * rather than throwing a database error at a public visitor.
     *
     * @param   string $sql SELECT statement, with named placeholders for every variable value.
     * @param   array $params Named parameters as placeholder => value pairs.
     * @return  array List of rows, each an associative array. Empty on failure.
     */
    private function select(string $sql, array $params = []): array
    {
        if (!$this->isConnected()) return [];

        try {
            $statement = $this->occurrenceDb->prepare($sql);
            $statement->execute($params);

            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $this->logger->logError((int) $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
            return [];
        }
    }

    /**
     * Run a read-only SELECT and return the first column of the first row.
     *
     * @param   string $sql SELECT statement, with named placeholders for every variable value.
     * @param   array $params Named parameters as placeholder => value pairs.
     * @return  mixed The scalar value, or null if there is no result or the query failed.
     */
    private function selectValue(string $sql, array $params = [])
    {
        $rows = $this->select($sql, $params);

        if (empty($rows)) return null;

        $first = \reset($rows);

        return \reset($first);
    }
}
