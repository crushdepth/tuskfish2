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
    public function activeTagOptions(string $module)
    {
        $module = $this->trimString($module); // Alphanumeric and underscores, only.

        if (!$this->isAlnumUnderscore($module)) {
            \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
            exit;
        }

        // Get a list of active tag IDs (those listed in the taglnks table).
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('module', $module));

        $taglinks = $this->database->selectDistinct('taglink', $criteria, ['tagId'])
            ->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($taglinks)) {
            return [];
        }

        // Look up the actual tag IDs.
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

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
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
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
            exit;
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
    public function getTagsForObject(int $id, string $module, string $table)
    {
        if ($id < 1) {
            return [];
        }

        $module = $this->trimString($module); // Alphanumeric and underscores, only.

        if (!$this->isAlnumUnderscore($module)) {
            \trigger_error(TFISH_ERROR_NOT_ALNUMUNDER, E_USER_ERROR);
            exit;
        }


        $table = $this->trimString($table); // Alphanumeric characters only.

        if (!$this->isAlnum($table)) {
            \trigger_error(TFISH_ERROR_NOT_ALNUM, E_USER_ERROR);
            exit;
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

    /**
     * Returns a list of options for the tag select box.
     *
     * @return  array Array of tag IDs and titles as key-value pairs.
     */
    public function onlineTagSelectOptions()
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
            \trigger_error(TFISH_ERROR_NO_RESULT, E_USER_ERROR);
        }

        return $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}
