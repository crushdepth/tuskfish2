<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\FetchBlock trait file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * ViewModel trait for fetching block data from database.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @property    \Tfish\BlockFactory $blockFactory
 */

trait FetchBlock
{
    private \Tfish\BlockFactory $blockFactory;

    /**
     * Retrieve content blocks from database.
     *
     * Blocks are retrieved and instantiated based on the URL path (route) associated with request.
     * Blocks are sorted by ID. Display in layout.html via echo, eg: <?php echo $block[42]; ?>
     *
     * @param string $path URL path.
     * @return array Blocked indexed by ID.
     */
    public function fetchBlocks(string $path): array
    {
        $blocks = [];
        $blockData = $this->model->fetchBlockData($path) ?? [];

        if (!empty($blockData)) {
            $blocks = $this->blockFactory->makeBlocks($blockData);
        }

        return !empty($blocks) ? $blocks : [];
    }
}
