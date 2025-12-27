<?php

declare(strict_types=1);

namespace Tfish\Model;

/**
 * \Tfish\Model\WebAuthnChallenge class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 */

/**
 * Model for WebAuthn challenge session storage.
 *
 * Challenges are stored in the session (ephemeral) not the database.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 */

class WebAuthnChallenge
{
    /**
     * Store registration challenge in session.
     *
     * @param   string $challenge Base64-encoded challenge.
     */
    public function storeRegistration(string $challenge): void
    {
        $_SESSION['webauthn_registration_challenge'] = $challenge;
    }

    /**
     * Retrieve and clear registration challenge from session.
     *
     * @return  string|null Challenge or null if not found.
     */
    public function getRegistration(): ?string
    {
        $challenge = $_SESSION['webauthn_registration_challenge'] ?? null;
        unset($_SESSION['webauthn_registration_challenge']);

        return $challenge;
    }

    /**
     * Store authentication challenge in session.
     *
     * @param   string $challenge Base64-encoded challenge.
     */
    public function storeAuthentication(string $challenge): void
    {
        $_SESSION['webauthn_authentication_challenge'] = $challenge;
    }

    /**
     * Retrieve and clear authentication challenge from session.
     *
     * @return  string|null Challenge or null if not found.
     */
    public function getAuthentication(): ?string
    {
        $challenge = $_SESSION['webauthn_authentication_challenge'] ?? null;
        unset($_SESSION['webauthn_authentication_challenge']);

        return $challenge;
    }

    /**
     * Store pending login user ID in session.
     *
     * @param   int $userId User ID.
     */
    public function storePendingUserId(int $userId): void
    {
        $_SESSION['webauthn_pending_user_id'] = $userId;
    }

    /**
     * Retrieve and clear pending login user ID from session.
     *
     * @return  int|null User ID or null if not found.
     */
    public function getPendingUserId(): ?int
    {
        $userId = $_SESSION['webauthn_pending_user_id'] ?? null;
        unset($_SESSION['webauthn_pending_user_id']);

        return $userId !== null ? (int)$userId : null;
    }

    /**
     * Clear all WebAuthn session data.
     */
    public function clear(): void
    {
        unset($_SESSION['webauthn_registration_challenge']);
        unset($_SESSION['webauthn_authentication_challenge']);
        unset($_SESSION['webauthn_pending_user_id']);
    }
}
