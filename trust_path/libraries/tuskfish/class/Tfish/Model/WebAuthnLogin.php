<?php

declare(strict_types=1);

namespace Tfish\Model;

/**
 * \Tfish\Model\WebAuthnLogin class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 */

/**
 * Model for WebAuthn-aware login operations.
 *
 * Detects if user requires WebAuthn second factor authentication.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the database class.
 */

class WebAuthnLogin
{
    use \Tfish\Traits\ValidateString;

    private \Tfish\Database $database;

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the database class.
     */
    public function __construct(\Tfish\Database $database)
    {
        $this->database = $database;
    }

    /**
     * Check if user requires second factor authentication.
     *
     * @param   int $userId User ID.
     * @return  string 'webauthn', 'otp', or 'none'.
     */
    public function requiresSecondFactor(int $userId): string
    {
        if ($userId < 1) {
            return 'none';
        }

        // Check for WebAuthn credentials first (preferred).
        // Query directly instead of creating another model
        $sql = "SELECT COUNT(*) as count FROM `webauthn_credentials` WHERE `userId` = :userId";
        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $webauthnCount = (int)($row['count'] ?? 0);

        if ($webauthnCount > 0) {
            return 'webauthn';
        }

        // Fallback to OTP during migration period.
        $sql = "SELECT yubikeyId FROM `user` WHERE `id` = :id LIMIT 1";
        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':id', $userId, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row && !empty($row['yubikeyId'])) {
            return 'otp';
        }

        return 'none';
    }

    /**
     * Check if user has WebAuthn enabled.
     *
     * @param   int $userId User ID.
     * @return  bool True if user has WebAuthn credentials.
     */
    public function hasWebAuthn(int $userId): bool
    {
        return $this->requiresSecondFactor($userId) === 'webauthn';
    }
}
