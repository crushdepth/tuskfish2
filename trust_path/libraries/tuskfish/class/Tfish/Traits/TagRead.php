<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\TagRead trait file.
 * 
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Read the tags associated with an object, typically an entity.
 * 
 * Note that this is more concerned with reading tags for the presentation side. Actual management
 * of tag/links is handled by the taglink trait.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @var         array $tags Tag IDs associated with this object; not persistent (stored as taglinks in taglinks table). 
 */

trait TagRead
{
    /**
     * Get tags associated with an object.
     * 
     * @param   int $id ID of content object.
     * @param   string $module Name of module associated with this object.
     * @return  array Tag IDs and titles as key-value pairs.
     */
    public function getTagsForObject(int $id, string $module)
    {
        if ($id < 1) {
            return [];
        }

        $module = $this->trimString($module);

        if (!$module) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }
        
        // Look up related tag IDs.
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('contentId', $id));
        $criteria->add($this->criteriaFactory->item('module', $module));

        $taglinks = [];
        
        $taglinks = $this->database->select('taglink', $criteria, ['tagId'])
            ->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($taglinks)) {
            return [];
        }

        // Retrieve related tags.
        $sql = "SELECT `id`, `title` FROM `content` WHERE `id` IN (";
        
        foreach ($taglinks as $taglink) {
            $sql .= "?,";
        }

        $sql = rtrim($sql, ",");
        $sql .= ")";

        $statement = $this->database->preparedStatement($sql);
        $result = $statement->execute($taglinks);

        if (!$result) {
            \trigger_error(TFISH_ERROR_NO_RESULT, E_USER_ERROR);
            return false;
        }

        return $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}
