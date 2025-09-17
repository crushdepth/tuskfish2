<?php

declare(strict_types=1);

namespace Tfish;

// Script runs via CLI only.
if (\PHP_SAPI !== 'cli') {
    exit(0);
}

/**
 * Tuskfish script to check for expired content and mark it as offline. Run via cron job at midnight.
 *
 * USAGE: For detailed instructions, please read: https://tuskfish.biz/articles/?id=195
 *
 * 1. Make this script executable, eg. chmod +x ./expiresOn.php
 *
 * 2. Uncomment and specify the correct path to your mainfile below.
 *
 * 3. Add a cron job to execute the script periodically (recommend midnight, daily), but modify the
 * paths to the PHP binary and this file to suit your own environment, eg:
 *
 * 0 0 * * * /usr/local/bin/php /var/www/trust_path/cron/expiresOn.php
 *
 * DOCKER USERS:
 *
 * If you are running Tuskfish in a Docker container, you can use the crontab on the host machine
 * via the exec command (modify the paths to PHP and expiresOn.php as required):
 *
 * 0 0 * * * docker exec -t container-name /usr/bin/local/php /var/www/somepath/cron/expiresOn.php
 *
 * If you try to run exec from cron with the interactive flag (-i) it will not work, because you
 * are not running it from an interactive context.
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

// CONFIG: Uncomment line below and SET THE CORRECT PATH to mainfile.php.
// require_once '../../mainfile.php';
require_once TFISH_PATH . 'header.php';

$database = $dice->create('\\Tfish\\Database');
$cache = $dice->create('\\Tfish\\Cache');

// For this to work the date format must match that used in the database: Y-m-d (output as yyyy-mm-dd)
$date = \date('Y-m-d', \time());

$sql = "UPDATE `content`
        SET `onlineStatus` = '0'
        WHERE `expiresOn` != ''
          AND `expiresOn` < :date
          AND `onlineStatus` = '1'";

$statement = $database->preparedStatement($sql);
$statement->bindValue(':date', $date, \PDO::PARAM_STR);
$database->executeTransaction($statement);
$affected = $statement->rowCount();

// If anything changed, flush the cache and regenerate sitemap.
if ($affected > 0) {
    $cache->flush();

    $sitemap = $dice->create('\\Tfish\\Model\\Sitemap');
    $sitemap->generate();
}

exit;
