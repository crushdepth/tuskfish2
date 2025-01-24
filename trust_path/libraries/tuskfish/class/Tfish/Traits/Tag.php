<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\Tag trait file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Add tag support to an object, typically an entity.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @var         array $tags Tag IDs associated with this object; not persistent (stored as taglinks in taglinks table).
 */

trait Tag
{
    private array $tags = [];

    /**
     * Return tags.
     *
     * @return array IDs of tags.
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * Set tags.
     *
     * @param   array $tags Tag IDs.
     */
    public function setTags(array $tags)
    {
        $cleanTags = [];

        foreach ($tags as $tag) {
            $cleanTag = (int) $tag;

            if ($cleanTag > 0) {
                $cleanTags[] = $cleanTag;
            } else {
               \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
            }

            unset($cleanTag);
        }

        $this->tags = $cleanTags;
    }
}
