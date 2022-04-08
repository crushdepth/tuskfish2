<?php

declare(strict_types=1);

namespace Tfish\Model;

/**
 * \Tfish\Model\Cache class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Model for handling cache operations.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @var         \Tfish\Cache Instance of the Tfish cache class.
 */

class Cache
{
    private $cache;

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish site preferences class.
     * @param   \Tfish\Cache Instance of the Tuskfish cache class.
     */
    public function __construct(\Tfish\Database $database, \Tfish\CriteriaFactory $criteriaFactory, \Tfish\Entity\Preference $preference, \Tfish\Cache $cache)
    {
        $this->cache = $cache;
    }

    /* Actions. */

    /**
     * Flush the cache.
     *
     * @return  bool True on success, false on failure.
     */
    public function flush(): bool
    {
        return $this->cache->flush();
    }
}
