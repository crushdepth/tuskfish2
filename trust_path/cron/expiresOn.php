<?php

declare(strict_types=1);

namespace Tfish;

/**
 * Tuskfish cron job script to check for expired content and mark it as offline.
 *
 * Run this script via cron job once per day (midnight).
 *
 * BONEHEAD WARNING: If the system time is wrong, this script could mark your whole site offline.
 *
 * @copyright   Simon Wilkinson 2023+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

require_once 'mainfile.php';
require_once TFISH_PATH . 'header.php';

$database = $dice->create('\\Tfish\\Database');
$cache = $dice->create('\\Tfish\\Cache');

// For this to work the date format must match that used in the database: Y-m-d (output as yyyy-mm-dd)
$date = \date('Y-m-d', \time());

// Count to determine if any rows are due for expiry.
$sql = "SELECT COUNT(*) FROM `content` WHERE `expiresOn` < :date AND `onlineStatus` = '1';";
$statement = $database->preparedStatement($sql);
$statement->bindValue(':date', $date, \PDO::PARAM_STR);
$statement->execute();
$count = $statement->fetch(\PDO::FETCH_NUM);
$count = (int) reset($count);

// If any candidate rows were found, expire them and flush the cache.
if ($count > 0) {
    $sql = "UPDATE `content` SET `onlineStatus` = '0' WHERE `expiresOn` < :date AND `onlineStatus` = '1';";
    $statement = $database->preparedStatement($sql);
    $statement->bindValue(':date', $date, \PDO::PARAM_STR);
    $database->executeTransaction($statement);
    //$statement->executeTransaction();
    $cache->flush();
}
