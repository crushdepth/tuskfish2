<?php

declare(strict_types=1);

namespace Tfish;

/**
 * Tuskfish script to check for expired content and mark it as offline. Run via cron job at midnight.
 *
 * BONEHEAD WARNINGS:
 *
 * 1. Do not put this script in your web root, it should remain in an inaccessible location.
 * 2. If the system time is wrong, content with expiry dates could incorrectly be set offline.
 *
 * In the interests of default security, the reference to mainfile.php is disabled by default to
 * prevent this script from running.
 *
 * @copyright   Simon Wilkinson 2023+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

// CONFIG: Uncomment line below and set the path to mainfile.php.
// require_once '../../mainfile.php';
require_once TFISH_PATH . 'header.php';

$database = $dice->create('\\Tfish\\Database');
$cache = $dice->create('\\Tfish\\Cache');

// For this to work the date format must match that used in the database: Y-m-d (output as yyyy-mm-dd)
$date = \date('Y-m-d', \time());

// Count to determine if any rows are due for expiry.
$sql = "SELECT COUNT(*) FROM `content` WHERE `expiresOn` > 0 AND `expiresOn` < :date AND `onlineStatus` = '1';";
$statement = $database->preparedStatement($sql);
$statement->bindValue(':date', $date, \PDO::PARAM_STR);
$statement->execute();
$count = $statement->fetch(\PDO::FETCH_NUM);
$count = (int) reset($count);

// If any candidate rows were found, expire them and flush the cache.
if ($count > 0) {
    $sql = "UPDATE `content` SET `onlineStatus` = '0' WHERE `expiresOn` > '0' AND `expiresOn` < :date AND `onlineStatus` = '1';";
    $statement = $database->preparedStatement($sql);
    $statement->bindValue(':date', $date, \PDO::PARAM_STR);
    $database->executeTransaction($statement);
    $cache->flush();
}

exit;
