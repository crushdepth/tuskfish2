<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\TagRead trait file.
 *
 * Handles tag retrieval for display-side purposes. Requires simultaneous use of \Tfish\Traits\ValidateString.
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
     * Return IDs and titles of tags that are actually in use with content objects for a given module.
     *
     * @param   string  Module name to filter results by.
     * @return  array IDs and titles as key-value pairs.
     */
    public function activeTagOptions(string $module): array
    {
        $module = $this->trimString($module); // Alphanumeric and underscores, only.

        if (!$this->isAlnumUnderscore($module)) {
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_ALNUMUNDER);
        }

        // Get a list of active tag IDs (those listed in the taglnks table)
        // AND that are marked as inFeed = 1.
        $sql = "SELECT DISTINCT `tag`.`id`, `tag`.`title` "
            . "FROM `taglink` "
            . "INNER JOIN `content` AS `tag` ON `taglink`.`tagId` = `tag`.`id` "
            . "WHERE `tag`.`inFeed` = 1 "
               . "AND `taglink`.`module` = :module "
               . "AND `tag`.`onlineStatus` = 1";

        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':module', $module, \PDO::PARAM_STR);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        return $statement->fetchAll();

    }

    /**
     * Return a collection of tags.
     *
     * Retrieves tags that have been grouped into a collection as ID-title key-value pairs.
     *
     * @param   int $id ID of the collection content object.
     * @return  array Tag IDs and titles as associative array.
     */
    public function collectionTagOptions(int $id)
    {
        if ($id < 1) {
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_INT);
        }

        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('type', 'TfTag'));
        $criteria->add($this->criteriaFactory->item('parent', $id));
        $criteria->add($this->criteriaFactory->item('onlineStatus', 1));

        return $this->database->select('content', $criteria, ['id', 'title'])
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get tags associated with an object.
     *
     * @param   int $id ID of content object.
     * @param   string $module Name of module associated with this object.
     * @param   string $table Name of DB table associated with this object.
     * @return  array Tag IDs and titles as key-value pairs.
     */
    public function getTagsForObject(int $id, string $module, string $table): array
    {
        if ($id < 1) {
            return [];
        }

        $module = $this->trimString($module); // Alphanumeric and underscores, only.

        if (!$this->isAlnumUnderscore($module)) {
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_ALNUMUNDER);
        }


        $table = $this->trimString($table); // Alphanumeric characters only.

        if (!$this->isAlnum($table)) {
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_ALNUM);
        }

        // Look up tags associated with this content object in the taglinks table.
        $sql = "SELECT DISTINCT `tag`.`id`, `tag`.`title` "
            . "FROM `taglink` "
            . "INNER JOIN `{$table}` AS `tag` ON `taglink`.`tagId` = `tag`.`id` "
            . "WHERE `taglink`.`contentId` = :id "
                . "AND `taglink`.`module` = :module ";
            //  . "AND `tag`.`onlineStatus` = 1";

        // Bind values
        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->bindValue(':module', $module, \PDO::PARAM_STR);

        $statement->setFetchMode(\PDO::FETCH_KEY_PAIR);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Returns a list of options for the tag select box.
     *
     * @return  array Array of tag IDs and titles as key-value pairs.
     */
    public function onlineTagSelectOptions(): array
    {
        $columns = ['id', 'title'];

        $criteria = $this->criteriaFactory->criteria();

        $criteria->add($this->criteriaFactory->item('type', 'TfTag'));
        $criteria->add($this->criteriaFactory->item('onlineStatus', 1));
        $criteria->setSort('title');
        $criteria->setOrder('ASC');
        $criteria->setSecondarySort('submissionTime');
        $criteria->setSecondaryOrder('DESC');

        $statement = $this->database->select('content', $criteria, $columns);

        if(!$statement) {
            throw new \InvalidArgumentException(TFISH_ERROR_NO_RESULT);
        }

        return $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}
