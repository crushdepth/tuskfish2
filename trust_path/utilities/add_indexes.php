<?php

declare(strict_types=1);

namespace Tfish;

// Script runs via CLI only.
if (\PHP_SAPI !== 'cli') {
    exit(0);
}

/**
 * Migration script to add database indexes and enable WAL mode on existing Tuskfish installations.
 *
 * Safe to run multiple times (all statements use IF NOT EXISTS).
 *
 * USAGE:
 *
 * 1. Uncomment and set the correct path to mainfile.php below.
 * 2. Run: php add_indexes.php
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     database
 */

// CONFIG: Uncomment line below and SET THE CORRECT PATH to mainfile.php.
// require_once '../../mainfile.php';
require_once TFISH_PATH . 'header.php';

$database = $dice->create('\\Tfish\\Database');

$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_session_lastactive ON session(lastActive)",
    "CREATE INDEX IF NOT EXISTS idx_content_online_type ON content(onlineStatus, type)",
    "CREATE INDEX IF NOT EXISTS idx_content_date_sub ON content(date DESC, submissionTime DESC)",
    "CREATE INDEX IF NOT EXISTS idx_content_parent ON content(parent)",
    "CREATE INDEX IF NOT EXISTS idx_content_expireson ON content(expiresOn)",
    "CREATE INDEX IF NOT EXISTS idx_taglink_contentid ON taglink(contentId)",
    "CREATE INDEX IF NOT EXISTS idx_taglink_tagid ON taglink(tagId)",
    "CREATE INDEX IF NOT EXISTS idx_taglink_module_contentid ON taglink(module, contentId)",
    "CREATE INDEX IF NOT EXISTS idx_blockroute_route ON blockRoute(route)",
    "CREATE INDEX IF NOT EXISTS idx_blockroute_blockid ON blockRoute(blockId)",
    "CREATE INDEX IF NOT EXISTS idx_content_stream ON content(onlineStatus, date DESC, submissionTime DESC) WHERE type != 'TfTag'",
];

echo "Tuskfish database migration: adding indexes and enabling WAL mode.\n\n";

foreach ($indexes as $sql) {
    $statement = $database->preparedStatement($sql);
    $statement->execute();

    // Extract index name for display.
    \preg_match('/idx_\w+/', $sql, $matches);
    echo "  Created index: " . ($matches[0] ?? 'unknown') . "\n";
}

// Enable WAL mode (persistent — only needs to be done once per database file).
$statement = $database->preparedStatement("PRAGMA journal_mode=WAL");
$statement->execute();
$result = $statement->fetch(\PDO::FETCH_NUM);
echo "\n  Journal mode: " . $result[0] . "\n";

echo "\nDone.\n";

exit;
