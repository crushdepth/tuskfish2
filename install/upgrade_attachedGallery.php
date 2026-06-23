<?php

declare(strict_types=1);

/**
 * One-off migration: add the `attachedGallery` column to the content table.
 *
 * Fresh installs get this column from install/index.php. Existing databases need it added once.
 * The script is idempotent — running it again on an already-migrated database is a safe no-op.
 *
 * Usage (CLI):  php install/upgrade_attachedGallery.php /path/to/your.db
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @package     content
 */

if (\PHP_SAPI !== 'cli') {
    exit("This migration must be run from the command line.\n");
}

$dbPath = $argv[1] ?? '';

if ($dbPath === '' || !\is_file($dbPath)) {
    exit("Usage: php install/upgrade_attachedGallery.php /path/to/your.db\n");
}

try {
    $pdo = new \PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    // Is the column already present?
    $columns = $pdo->query("PRAGMA table_info(`content`)")->fetchAll(\PDO::FETCH_COLUMN, 1);

    if (\in_array('attachedGallery', $columns, true)) {
        exit("Column `attachedGallery` already exists — nothing to do.\n");
    }

    $pdo->exec("ALTER TABLE `content` ADD COLUMN `attachedGallery` INTEGER NOT NULL DEFAULT 0");

    echo "Added `attachedGallery` column to the content table.\n";
} catch (\Throwable $e) {
    exit("Migration failed: " . $e->getMessage() . "\n");
}
