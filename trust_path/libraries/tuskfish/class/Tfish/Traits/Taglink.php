<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\Taglink trait file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Manage object-tag associations via taglinks.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait TagLink
{
    /**
     * Delete taglinks associated with an object.
     *
     * @param   int $contentId ID of content object.
     * @param   string $module Module the content belongs to.
     * @return  bool True on success, false on failure.
     */
    private function deleteTaglinks(int $contentId, string $module): bool
    {
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('contentId', $contentId));
        $criteria->add($this->criteriaFactory->item('module', $module));

        return $this->database->deleteAll('taglink', $criteria);
    }

    /**
     * Delete references of all content objects to a tag that is in process of deletion.
     *
     * Affects all modules (system wide).
     *
     * @param   int $id ID of tag.
     * @return  bool True on success, false on failure.
     */
    private function deleteReferencestoTag(int $id): bool
    {
        if ($id < 1) return false;

        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('tagId', $id));

        return $this->database->deleteAll('taglink', $criteria);
    }

    /**
     * Get tag IDs (only) associated with a SINGLE content object.
     *
     * This is a helper function used in edit operations.
     *
     * @param   int $id ID of content object.
     * @param string $module Name of module (disambiguate content ID).
     * @return  array Array of tag IDs.
     */
    private function getTagIds(int $id, string $module = 'content'): array
    {
        $columns = ['tagId'];
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('contentId', $id));
        $criteria->add($this->criteriaFactory->item('module', 'content'));

        return $this->database->select('taglink', $criteria, $columns)
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Insert the tags associated with an object.
     *
     * This is a helper function for  CRUD insert() operations. The $content parameter MUST include
     * 'id', 'type', 'module' and 'tags' array.
     *
     * Tags are stored separately in the taglinks table. Tags are assembled in one batch before
     * proceeding to insertion; so if one fails a range check all should fail. If the
     * lastInsertId could not be retrieved, then halt execution because this data
     * is necessary in sort to correctly assign taglinks to content objects.
     *
     * @param   int $contentId ID of the content object associated with these taglinks.
     * @param   string $contentType The type of content object.
     * @param   string $module The module this content object is associated with.
     * @param   array $tags Array of Tag IDs associated with this content object.
     * @return boolean
     */
    private function saveTaglinks(int $contentId, string $contentType, string $module, array $tags): bool
    {
        if ($module === '') {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_NOTICE);
            return false;
        }

        $cleanTaglinks = [];

        foreach ($tags as $tag) {
            $taglink = [];
            $taglink['tagId'] = (int) $tag;
            $taglink['contentType'] = $contentType;
            $taglink['contentId'] = $contentId;
            $taglink['module'] = $module;

            if (!$this->database->insert('taglink', $taglink)) {
                return false;
            }

            unset($tag, $taglink);
        }

        return true;
    }

    /**
     * Update taglinks for a content object.
     *
     * @param   int $contentId ID of the content object associated with these taglinks.
     * @param   string $contentType The type of content object.
     * @param   string $module The module this content object is associated with.
     * @param   array $tags Array of Tag IDs associated with this content object.
     * @return  bool True on success, false on failure.
     */
    private function updateTaglinks(int $contentId, string $contentType, string $module, array $tags): bool
    {
        // Delete existing taglinks for this content.
        if (!$this->deleteTaglinks($contentId, $module)) {
            return false;
        }

        // Save the updated taglinks for this content.
        $content['tags'] = $tags;

        if (!$this->saveTaglinks($contentId, $contentType, $module, $tags)) {
            return false;
        }

        return true;
    }

    /**
     * Validate tag IDs.
     *
     * @param   array $tags Tag IDs.
     * @return  array Validated tag IDs.
     */
    private function validateTags(array $tags): array
    {
        $cleanTags = [];

        foreach ($tags as $key => $value) {

            if (((int) $value) > 0) {
                $cleanTags[] = $value;
            }

            unset($key, $value);
        }

        return $cleanTags;
    }
}
