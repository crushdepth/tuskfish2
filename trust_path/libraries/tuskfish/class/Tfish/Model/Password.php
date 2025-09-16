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
    private \Tfish\Database $database;
    private \Tfish\Session $session;

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
     * Check if password is invalid (does not meet minimum length and UTF-8 coding requirements).
     * Minimum length is hardcoded at 15 characters. Any less and it doesn't matter what your
     * password is, the entire keyspace can be searched.
     *
     * @param   string $password
     * @param   string $confirm Confirmation password.
     * @return  bool True if INVALID, false if VALID.
     */
    private function passwordIsNotValid(string $password, string $confirm): bool
    {
        $len = \mb_strlen($password, 'UTF-8');

        if ($password !== $confirm || $len < 15 || $len === false) {
            return true;
        }
        return false;
    }

    /**
     * Update password in database.
     *
     * Must be logged in, member of at least one group, and account enabled to access.
     *
     * @param   string $password
     * @return  bool True on success, false on failure.
     */
    private function updatePassword(string $password): bool
    {
        $userId = (int) ($_SESSION['id'] ?? 0);
        $userMask = (int) $this->session->verifyPrivileges();

        if ($userId <= 0 || $userMask <= 0) {
            return false;
        }

        $hash = $this->session->hashPassword($password);

        return $this->database->update('user', $userId, ['passwordHash' => $hash]);
    }
}
