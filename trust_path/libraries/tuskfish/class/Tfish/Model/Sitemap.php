<?php

declare(strict_types=1);

namespace Tfish\Model;

/**
 * \Tfish\Model\Sitemap class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Model for generating a sitemap.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

class Sitemap
{
    private $database;
    private $criteriaFactory;

    /**
     * Constructor
     *
     * @param \Tfish\Database $database
     * @param \Tfish\CriteriaFactory $criteriaFactory
     */
    public function __construct(
        \Tfish\Database $database,
        \Tfish\CriteriaFactory $criteriaFactory
        )
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
    }

    /**
     * Writes a sitemap to the site root.
     *
     * @return boolean True on success, false on failure.
     */
    public function generate(): bool
    {
        $sitemap = TFISH_ROOT_PATH . 'sitemap.txt';
        $content = [];

        // Select id, title from content where onlineStatus = 1
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('type', 'TfBlock', '!='));
        $criteria->add($this->criteriaFactory->item('onlineStatus', 1));
        $statement = $this->database->select('content', $criteria, ['id', 'title', 'metaSeo']);

        $content = $statement->fetchAll(\PDO::FETCH_ASSOC);

        // Remove any existing sitemap.
        \clearstatcache();
        if (\file_exists($sitemap)
                && \unlink($sitemap) === false) {
            return false;
        }

        // Write new/updated sitemap to site root.
        $fileHandle = \fopen($sitemap, 'a+');

        if ($fileHandle === false) {
            return false;
        }

        foreach ($content as $item) {
            $value = TFISH_PERMALINK_URL . '?id=' . (string) $item['id'];

            if (!empty($item['metaSeo'])) {
                $value .= '&amp;title=' . $item['metaSeo'];
            }

            $value .= "\n";

            \fwrite($fileHandle, $value);
        }

        \fclose($fileHandle);

        // Enforce permissions.
        \chmod ($sitemap, 0644);

        return true;
    }
}
