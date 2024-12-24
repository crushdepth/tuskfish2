<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\BlockFactory class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.1
 * @package     database
 */

/**
 * Factory for instantiating Block objects and injecting dependencies.
 *
 * Use this class to delegate construction of block objects.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.1
 * @package     database
 */
class BlockFactory
{
    private \Tfish\Database $database;
    private \Tfish\CriteriaFactory $criteriaFactory;

    public function __construct(\Tfish\Database $database, \Tfish\CriteriaFactory $criteriaFactory)
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
    }
    /**
     * Factory method to instantiate and return a Block object.
     *
     * @return object Instance of a block object.
     */
    public function makeBlocks(array $rows): array
    {
        $blocks = [];

        foreach ($rows as $row) {
            $className = $row['type'];

            if (\class_exists($className)) {
                $blocks[$row['id']] = new $className($row, $this->database, $this->criteriaFactory);
            }

            unset($className);
        }

        return $blocks;
    }
}
