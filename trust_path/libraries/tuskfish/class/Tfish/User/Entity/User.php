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
 * @var         int $userGroup User group(s) to which this user belongs.
 * @var         int $onlineStatus Whether the user's privileges are enabled (1) or suspended (0).
 * @var         int $loginErrors Number of failed logins since last successful long.
 */

class User
{
    use \Tfish\Traits\Group;
    use \Tfish\Traits\ValidateString;

    private int $id = 0;
    private string $adminEmail = '';
    private string $passwordHash = '';
    private int $userGroup = 0;
    private int $loginErrors = 0;
    private int $onlineStatus = 0;

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
        if ($id < 1) {
            throw new \InvalidArgumentException(TFISH_ERROR_ILLEGAL_VALUE);
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
     * Set user groups.
     *
     * Must only contain bits from the whitelist.
     *
     * @param integer $groups
     * @return void
     */
    public function setUserGroup(int $groups)
    {
        $whitelistMask = \array_sum(\array_keys($this->listUserGroups()));

        if (($groups & ~$whitelistMask) !== 0) {
            throw new \InvalidArgumentException(TFISH_ERROR_INVALID_GROUP);
        }

        $this->userGroup = $groups;
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
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_INT);
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
            throw new \InvalidArgumentException(TFISH_ERROR_ILLEGAL_VALUE);
        }

       $this->loginErrors = $errors;
    }
}
