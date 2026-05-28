<?php

declare(strict_types=1);

namespace Tfish;

// Script runs via CLI only.
if (\PHP_SAPI !== 'cli') {
    exit(0);
}

/**
 * Migration script to add partial index for content stream queries.
 *
 * Optimises the main index/listing page by pre-excluding TfTag rows and
 * including sort columns, eliminating a full scan + temp sort on every page load.
 *
 * Safe to run multiple times (uses IF NOT EXISTS).
 *
 * USAGE:
 *
 * 1. Uncomment and set the correct path to mainfile.php below.
 * 2. Run: php add_stream_index.php
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

$sql = "CREATE INDEX IF NOT EXISTS idx_content_stream ON content(onlineStatus, date DESC, submissionTime DESC) WHERE type != 'TfTag'";

echo "Tuskfish database migration: adding partial index for content stream.\n\n";

$statement = $database->preparedStatement($sql);
$statement->execute();
echo "  Created index: idx_content_stream\n";

echo "\nDone.\n";

exit;
