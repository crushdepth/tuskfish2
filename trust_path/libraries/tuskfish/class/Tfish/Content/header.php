<?php

declare(strict_types=1);

/**
 * Tuskfish header script for the Content module.
 *
 * Registers the Content module's block types, templates, config sub-templates and routes into the
 * block registry seed arrays declared in the core header. Auto-discovered by index.php. Content's
 * page routes remain in routingTable.php and its page templates in themes/ for now; those move here
 * when the Content module is fully converted.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

namespace Tfish\Content;

// Content module block template path, used by each block's render() method.
\define("TFISH_CONTENT_BLOCK_PATH", TFISH_PATH . 'class/Tfish/Content/Block/');

// Register Content block types (fully qualified class name => label).
$blockTypes['\Tfish\Content\Block\RecentContent'] = TFISH_BLOCK_RECENT_CONTENT;
$blockTypes['\Tfish\Content\Block\Spotlight'] = TFISH_BLOCK_SPOTLIGHT;
$blockTypes['\Tfish\Content\Block\FeaturedVideo'] = TFISH_BLOCK_FEATURED_VIDEO;
$blockTypes['\Tfish\Content\Block\Html'] = TFISH_BLOCK_HTML;

// Register the templates available to each Content block type (class => [templateName => label]).
$blockTemplates['\Tfish\Content\Block\RecentContent'] = ['recent-content-compact' => TFISH_BLOCK_RECENT_CONTENT_COMPACT];
$blockTemplates['\Tfish\Content\Block\Spotlight'] = ['spotlight-compact' => TFISH_BLOCK_SPOTLIGHT_COMPACT];
$blockTemplates['\Tfish\Content\Block\FeaturedVideo'] = ['featured-video' => TFISH_BLOCK_VIDEO_COMPACT];
$blockTemplates['\Tfish\Content\Block\Html'] = ['html' => TFISH_BLOCK_HTML];

// Register the configuration sub-template for each Content block type (class => template name).
$blockConfig['\Tfish\Content\Block\RecentContent'] = 'recent-content-config';
$blockConfig['\Tfish\Content\Block\Spotlight'] = 'spotlight-config';
$blockConfig['\Tfish\Content\Block\FeaturedVideo'] = 'featured-video-config';
$blockConfig['\Tfish\Content\Block\Html'] = 'html-config';

// Routes that Content blocks may be displayed on, merged with the core seed.
$blockRoutes = \array_merge($blockRoutes, ['/', '/gallery/', '/search/']);
