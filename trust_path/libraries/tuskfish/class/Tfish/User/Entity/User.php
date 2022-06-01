<?php

declare(strict_types=1);

namespace Tfish\User\Entity;

/**
 * \Tfish\User\Entity\User class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     user
 */

/**
 * Represents a single user object.
 *
 * Users are the administrators of Tuskfish CMS (one super user and multiple Editors. They are never
 * untrusted members of the public! The super user has full administrative access to the site,
 * while Editors have access to content creation and editing functions.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     user
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         int $id Auto-increment, set by database.
 * @var         string $adminEmail
 * @var         string $passwordHash
 * @var         string $userGroup
 * @var         string $yubikeyId
 * @var         string $yubikeyId2
 * @var         int $onlineStatus
 * @var         int $loginErrors
 */

class User
{
    use \Tfish\Traits\ValidateString;

    private $id = 0;
    private $adminEmail = '';
    private $passwordHash = '';
    private $userGroup = 0;
    private $yubikeyId = '';
    private $yubikeyId2 = '';
    private $loginErrors = 0;
    private $onlineStatus = 0;

    /**
     * Load properties.
     *
     * Parameters are validated by the respective setters.
     *
     * @param   array $row Data to load into properties.
     */
    public function load(array $row): void
    {
        $this->setId((int) ($row['id'] ?? 0));
        $this->setAdminEmail((string) ($row['adminEmail'] ?? ''));
        $this->setPasswordHash((string) ($row['passwordHash'] ?? ''));
        $this->setUserGroup((int) ($row['userGroup'] ?? 0));
        $this->setYubikeyId((string) ($row['yubikeyId'] ?? ''));
        $this->setYubikeyId2((string) ($row['yubikeyId2'] ?? ''));
        $this->setLoginErrors((int) ($row['loginErrors'] ?? 0));
        $this->setOnlineStatus((int) ($row['onlineStatus'] ?? 0));
    }

    /** Getters and setters */

    /**
     * Return user ID.
     *
     * @return int
     */
    public function id(): int
    {
        return (int) $this->id;
    }

    /**
     * Set ID
     *
     * @param   int $id ID of user object.
     */
    public function setId(int $id)
    {
        if ($id < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->id = $id;
    }

    /**
     * Return admin email address.
     *
     * @return string
     */
    public function adminEmail(): string
    {
        return $this->adminEmail;
    }

    /**
     * Set admin email address.
     *
     * @param string $email
     * @return void
     */
    public function setAdminEmail(string $email)
    {
        $this->adminEmail = $this->trimString($email);
    }

    /**
     * Return password hash.
     *
     * @return string
     */
    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    /**
     * Set password hash.
     *
     * @param string $hash
     * @return void
     */
    public function setPasswordHash(string $hash)
    {
        $this->passwordHash = $this->trimString($hash);
    }

    /**
     * Return user group.
     *
     * @return integer
     */
    public function userGroup(): int
    {
        return (int) $this->userGroup;
    }

    /**
     * Set user group.
     *
     * @param integer $group
     * @return void
     */
    public function setUserGroup(int $group)
    {
        $group = (int) $group;

        if ($group < 0 || $group > 2) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

        $this->userGroup = $group;
    }

    /**
     * Return primary Yubikey ID of this user.
     *
     * @return string
     */
    public function yubikeyId(): string
    {
        return $this->yubikeyId;
    }

    /**
     * Set primary Yubikey ID of this user.
     *
     * @param string $yubikey
     * @return void
     */
    public function setYubikeyId(string $yubikey)
    {
        $this->yubikeyId = $this->trimString($yubikey);
    }

    /**
     * Return secondary Yubikey ID of this user.
     *
     * @return string
     */
    public function yubikeyId2(): string
    {
        return $this->yubikeyId2;
    }

    /**
     * Set secondary Yubikey ID of this user.
     *
     * @param string $yubikey
     * @return void
     */
    public function setYubikeyId2(string $yubikey)
    {
        $this->yubikeyId2 = $this->trimString($yubikey);
    }

    /**
     * Return online status.
     *
     * @return int 0 if offline, 1 if online.
     */
    public function onlineStatus(): int
    {
        return (int) $this->onlineStatus;
    }

    /**
     * Set online status.
     *
     * @param   int $status 0 for offline, 1 for online.
     */
    public function setOnlineStatus(int $status)
    {
        if ($status !== 0 && $status !== 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->onlineStatus = $status;
    }

    public function loginErrors(): int
    {
        return (int) $this->loginErrors;
    }

    public function setLoginErrors(int $errors)
    {
        $errors = (int) $errors;

        if ($errors < 0) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

       $this->loginErrors = $errors;
    }
}
