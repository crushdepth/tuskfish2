<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\BlockOption trait file.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Whitelisted route and position options for blocks.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait BlockOption
{
    /**
     * Whitelist of permitted block positions.
     *
     * You can customise this list, but don't delete or rename positions that currently have blocks
     * assigned. If you want to do that, you need to update the positions in the database too.
     *
     * @return array
     */
    public function blockPositions(): array
    {
        return [
            'banner' => TFISH_BLOCK_BANNER,
            'top-left' => TFISH_BLOCK_TOP_LEFT,
            'top-right' => TFISH_BLOCK_TOP_RIGHT,
            'top-centre' => TFISH_BLOCK_TOP_CENTRE,
            'left' => TFISH_BLOCK_LEFT,
            'right' => TFISH_BLOCK_RIGHT,
            'bottom-left' => TFISH_BLOCK_BOTTOM_LEFT,
            'bottom-right' => TFISH_BLOCK_BOTTOM_RIGHT,
            'bottom-centre' => TFISH_BLOCK_BOTTOM_CENTRE,
            'footer' => TFISH_BLOCK_FOOTER
        ];
    }

    /**
     * Whitelist of routes that blocks are permitted to be displayed on.
     *
     * You can customise this list, but don't delete or rename positions that currently have blocks
     * assigned. If you want to do that, you need to update the positions in the database too.
     *
     * @return array
     */
    public function blockRoutes(): array
    {
        return [
            "/",
            "/error/",
            "/gallery/",
            "/search/"
        ];
    }

    /**
     * Whitelist of templates available to each block type.
     *
     * If you add a custom block, add its templates to this list. Template name must match the
     * file name (without .html extension).
     *
     * @return array
     */
    public function blockTemplates(): array
    {
        return [
            '\Tfish\Content\Block\RecentContent' => ['recent-content-compact' => TFISH_BLOCK_RECENT_CONTENT_COMPACT],
            '\Tfish\Content\Block\Spotlight' => ['spotlight-compact' => TFISH_BLOCK_SPOTLIGHT_COMPACT],
            '\Tfish\Content\Block\Html' => ['html' => TFISH_BLOCK_HTML],
        ];
    }

    /**
     * Whitelist of block types available on the system.
     *
     * If you add a custom block type, add it to this list. The key is the fully qualified
     * class name.
     *
     * @return array
     */
    public function blockTypes(): array
    {
        return [
            '\Tfish\Content\Block\RecentContent' => TFISH_BLOCK_RECENT_CONTENT,
            '\Tfish\Content\Block\Spotlight' => TFISH_BLOCK_SPOTLIGHT,
            '\Tfish\Content\Block\Html' => TFISH_BLOCK_HTML,
        ];
    }
}
