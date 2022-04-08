<?php

declare(strict_types=1);

namespace Tfish\Model;

/**
 * \Tfish\Model\Password class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Model for changing the administrative password.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\Session $session Instance of the session management class.
 */

class Password
{
    private $database;
    private $session;

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\Session $session Instance of the Tuskfish session manager class.
     */
    public function __construct(\Tfish\Database $database, \Tfish\Session $session)
    {
        $this->database = $database;
        $this->session = $session;
    }

    /**
     * Change the admin password.
     *
     * @param   string $password
     * @param   string $confirm Confirmation password.
     * @return  bool True on success, false on failure.
     */
    public function changePassword(string $password, string $confirm): bool
    {
        if ($this->passwordIsNotValid($password, $confirm)) {
            return false;
        }

        return $this->updatePassword($password);
    }

    /**
     * Validate that password meets minimum length and UTF-8 coding requirements.
     * Minimum length is hardcoded at 15 characters. Any less and it doesn't matter
     * what your password is, the entire keyspace can be searched.
     *
     * @param   string $password
     * @param   string $confirm Confirmation password.
     * @return  bool True if valid, false if invalid.
     */
    private function passwordIsNotValid(string $password, string $confirm): bool
    {
        if (($password !== $confirm) || (\mb_strlen($password, 'UTF-8') < 15)) {
            return true;
        }

        return false;
    }

    /**
     * Update password in database.
     *
     * @param   string $password
     * @return  bool True on success, false on failure.
     */
    private function updatePassword(string $password): bool
    {
        $userId = (int) $_SESSION['userId'];

        if (empty($userId) || !isset($_SESSION['TFISH_LOGIN']) || $_SESSION['TFISH_LOGIN'] !== true) {
            return false;
        }

        $hash = $this->session->hashPassword($password);

        return $this->database->update('user', $userId, ['passwordHash' => $hash]);
    }
}
