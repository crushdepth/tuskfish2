<?php

declare(strict_types=1);

/**
 * Tuskfish header script for the Bookshelf module.
 *
 * Custom (non-core) drop-in module. Auto-discovered by index.php, which globs each module
 * directory for a header.php and includes it in the scope where $routingTable and the block seed
 * arrays live. Registers the /bookshelf/ route and the module's path constants without touching any
 * core file, so a Tuskfish core update cannot silently drop the route.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     Bookshelf
 */

namespace Tfish\Bookshelf;

// Route: hand-curated grid of book covers grouped by subject. Public (access mask 0).
$routingTable['/bookshelf/'] = new \Tfish\Route(
    '\\Tfish\\Bookshelf\\Model\\Bookshelf',
    '\\Tfish\\Bookshelf\\ViewModel\\Bookshelf',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Bookshelf\\Controller\\Bookshelf',
    0);

// Module template directory, used as the bundled-default fallback when the active theme does not
// provide bookshelf.html (see \Tfish\Entity\Template::validPath() and the ViewModel's modulePath).
\define("TFISH_BOOKSHELF_TEMPLATE_PATH", TFISH_CLASS_PATH . 'Tfish/Bookshelf/templates/');

// Absolute path to the module stylesheet. trust_path is not web-accessible, so the template inlines
// this file server-side (readfile) rather than linking it. Kept as a real .css file so it stays
// editable/lintable and references the grid themes' shared classes instead of duplicating them.
\define("TFISH_BOOKSHELF_CSS", TFISH_CLASS_PATH . 'Tfish/Bookshelf/bookshelf.css');
