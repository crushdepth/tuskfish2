<?php

declare(strict_types=1);

namespace Tfish\Model;

/**
 * PreferenceModel class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 1.0
 * @since       1.1
 * @package     core
 */

/**
 * Read and write site preferences to the database.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 1.0
 * @since       1.1
 * @package     core
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 */

class Preference
{
    private \Tfish\Database $database;
    private \Tfish\CriteriaFactory $criteriaFactory;
    private \Tfish\Entity\Preference $preference;
    private \Tfish\Cache $cache;

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish site preferences class.
     * @param   \Tfish\Cache Instance of the Tuskfish cache class.
     */
    public function __construct(
        \Tfish\Database $database,
        \Tfish\CriteriaFactory $criteriaFactory,
        \Tfish\Entity\Preference $preference,
        \Tfish\Cache $cache
        )
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
        $this->preference = $preference;
        $this->cache = $cache;
    }

    /** Actions. */

    /**
     * Update site preferences.
     *
     * @return  bool True on success, false on failure.
     */
    public function update(): bool
    {
        if (!isset($_POST['preference']) || !\is_array($_POST['preference'])) {
            return false;
        }

        $this->preference->load($_POST['preference']);

        $result = $this->writePreferences();

        if ($result) {
            $this->cache->flush();
        }

        return $result;
    }

    /**
     * Updates the site preferences in the database.
     *
     * @param \Tfish\Entity\Preference $preference Instance of the Tuskfish site preference class.
     * @return bool True on success false on failure.
     */
    private function writePreferences(): bool
    {
        $keyValues = $this->preference->getPreferencesAsArray();

        foreach ($keyValues as $key => $value) {
            $sql = "UPDATE `preference` SET `value` = :value WHERE `title` = :title";
            $statement = $this->database->preparedStatement($sql);
            $statement->bindValue(':title', $key, $this->database->setType($key));
            $statement->bindValue(':value', $value, $this->database->setType($value));

            unset($sql, $key, $value);

            $result = $this->database->executeTransaction($statement);

            if (!$result) {
                \trigger_error(TFISH_ERROR_INSERTION_FAILED, E_USER_ERROR);
                return false;
            }
        }

        return true;
    }

    /**
     * Returns a list of themes installed on the system (admin not included).
     */
    public function themes(): array
    {

        if (!\is_dir(TFISH_THEMES_PATH)) {
            return [];
        }

        $entries = \scandir(TFISH_THEMES_PATH);
        $dirs = [];
        $excluded = ['.', '..', 'admin', 'rss', 'signin'];

        foreach ($entries as $entry) {
            if (\in_array($entry, $excluded, true)) {
                continue;
            }

            $fullPath = TFISH_THEMES_PATH . $entry;
            if (\is_dir($fullPath)) {
                $dirs[] = $entry;
            }
        }

        return $dirs;
    }
}
