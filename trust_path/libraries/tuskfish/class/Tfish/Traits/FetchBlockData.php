<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\FetchBlockData trait file.
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
 */

trait FetchBlockData
{
    /**
     * Retrieve block data from database.
     *
     * Blocks are loaded based on the URL path (route) associated with this request.
     * Blocks are sorted by ID. Display in layout.html via echo, eg: <?php echo $block[42]; ?>
     *
     * @param string $path URL path.
     * @return array Blocked indexed by ID.
     */
    public function fetchBlockData(string $path): array
    {
        $blockData = [];

        $sql = "SELECT `block`.`id`, `type`, `position`, `title`, `config`, `weight`, "
            . "`template`, `onlineStatus` FROM `block` "
            . "INNER JOIN `blockRoute` ON `block`.`id` = `blockRoute`.`blockId` "
            . "WHERE `blockRoute`.`route` = :path";

        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':path', $path, \PDO::PARAM_STR);
        $statement->setFetchMode(\PDO::FETCH_UNIQUE); // Index by ID.
        $statement->execute();
        $blockData = $statement->fetchAll();

        return $blockData;
    }
}
