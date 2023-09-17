<?php

declare(strict_types=1);

namespace Tfish;

/**
 * Tuskfish script to check for expired content and mark it as offline. Run via cron job at midnight.
 * 
 * Sample crontab entry (modify the paths to php / expiresOn.php to suit your own environment): 
 * 0 0 * * * /usr/local/bin/php /var/www/trust_path/cron/expiresOn.php
 * 
 * If you are running Tuskfish in a Docker container, you can use the crontab on the host machine
 * via the exec command (modify the paths as required):
 * 
 * 0 0 * * * docker exec -it containerName php /var/www/trust_path/cron/expiresOn.php
 *
 * BONEHEAD WARNINGS:
 *
 * 1. DO NOT put this script in your web root, it should remain in a location inaccessible by browser.
 * 2. Set the file permissions/ownership as restrictive as you can (0400 is fine).
 * 3. If the system time is wrong, content with expiry dates could incorrectly be set offline.
 *
 * NOTE:
 * 
 * In the interests of security by default, the reference to mainfile.php is commented out
 * to prevent this script from running. Uncomment to enable, after heeding the warnings above.
 *
 * @copyright   Simon Wilkinson 2023+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

// CONFIG: Uncomment line below and SET TEH CORRECT PATH to mainfile.php.
// require_once '../../mainfile.php';
require_once TFISH_PATH . 'header.php';

$database = $dice->create('\\Tfish\\Database');
$cache = $dice->create('\\Tfish\\Cache');

// For this to work the date format must match that used in the database: Y-m-d (output as yyyy-mm-dd)
$date = \date('Y-m-d', \time());

// Count to determine if any rows are due for expiry.
$sql = "SELECT COUNT(*) FROM `content` WHERE `expiresOn` != '' AND `expiresOn` < :date AND `onlineStatus` = '1';";
$statement = $database->preparedStatement($sql);
$statement->bindValue(':date', $date, \PDO::PARAM_STR);
$statement->execute();
$count = $statement->fetch(\PDO::FETCH_NUM);
$count = (int) reset($count);

// If any candidate rows were found, expire them and flush the cache.
if ($count > 0) {
    $sql = "UPDATE `content` SET `onlineStatus` = '0' WHERE `expiresOn` != '' AND `expiresOn` < :date AND `onlineStatus` = '1';";
    $statement = $database->preparedStatement($sql);
    $statement->bindValue(':date', $date, \PDO::PARAM_STR);
    $database->executeTransaction($statement);
    $cache->flush();
}

exit;
