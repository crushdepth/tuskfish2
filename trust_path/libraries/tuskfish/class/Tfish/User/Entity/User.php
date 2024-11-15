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
 * @since       2.0
 * @package     user
 */

/**
 * Represents a single user object.
 *
 * Users are the administrators of Tuskfish CMS (one super user and multiple Editors. They are never
 * untrusted members of the public! The super user has full administrative access to the site,
 * while Editors have access to content creation and editing functions, only.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         int $id Auto-increment, set by database.
 * @var         string $adminEmail Email address of this user.
 * @var         string $passwordHash Password hash for this user.
 * @var         string $userGroup User group to which this user belongs.
 * @var         string $yubikeyId Public ID of primary yubikey for two factor authentication.
 * @var         string $yubikeyId2 Public ID of secondary yubikey for two factor authentication.
 * @var         string $yubikeyId3 Public ID of tertiary yubikey for two factor authentication.
 * @var         int $onlineStatus Whether the user's privileges are enabled (1) or suspended (0).
 * @var         int $loginErrors Number of failed logins since last successful long.
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
    private $yubikeyId3 = '';
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
        $this->setYubikeyId3((string) ($row['yubikeyId3'] ?? ''));
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
     * Return tertiary Yubikey ID of this user.
     *
     * @return string
     */
    public function yubikeyId3(): string
    {
        return $this->yubikeyId3;
    }

    /**
     * Set tertiary Yubikey ID of this user.
     *
     * @param string $yubikey
     * @return void
     */
    public function setYubikeyId3(string $yubikey)
    {
        $this->yubikeyId3 = $this->trimString($yubikey);
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

    /**
     * Return number of login errors since last successful login.
     *
     * This is used to set a proportional delay on subsequent login attempts.
     *
     * @return integer
     */
    public function loginErrors(): int
    {
        return (int) $this->loginErrors;
    }

    /**
     * Set the number of login errors for this user.
     *
     * Used when instantiating a user object from storage.
     *
     * @param integer $errors
     * @return void
     */
    public function setLoginErrors(int $errors)
    {
        $errors = (int) $errors;

        if ($errors < 0) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

       $this->loginErrors = $errors;
    }
}
