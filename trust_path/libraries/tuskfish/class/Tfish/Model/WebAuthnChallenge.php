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
    /** Maximum age (seconds) of a challenge or pending login. Browser prompts time out after 60. */
    private const MAX_AGE = 300;

    /**
     * Store registration challenge in session.
     *
     * @param   string $challenge Base64-encoded challenge.
     */
    public function storeRegistration(string $challenge): void
    {
        $_SESSION['webauthn_registration_challenge'] = $challenge;
        $_SESSION['webauthn_registration_time'] = \time();
    }

    /**
     * Retrieve and clear registration challenge from session.
     *
     * @return  string|null Challenge or null if not found or expired.
     */
    public function getRegistration(): ?string
    {
        $challenge = $_SESSION['webauthn_registration_challenge'] ?? null;
        $fresh = $this->isFresh('webauthn_registration_time');
        unset($_SESSION['webauthn_registration_challenge'], $_SESSION['webauthn_registration_time']);

        return $fresh ? $challenge : null;
    }

    /**
     * Store authentication challenge in session.
     *
     * @param   string $challenge Base64-encoded challenge.
     */
    public function storeAuthentication(string $challenge): void
    {
        $_SESSION['webauthn_authentication_challenge'] = $challenge;
        $_SESSION['webauthn_authentication_time'] = \time();
    }

    /**
     * Retrieve and clear authentication challenge from session.
     *
     * @return  string|null Challenge or null if not found or expired.
     */
    public function getAuthentication(): ?string
    {
        $challenge = $_SESSION['webauthn_authentication_challenge'] ?? null;
        $fresh = $this->isFresh('webauthn_authentication_time');
        unset($_SESSION['webauthn_authentication_challenge'], $_SESSION['webauthn_authentication_time']);

        return $fresh ? $challenge : null;
    }

    /**
     * Store pending login user ID in session.
     *
     * @param   int $userId User ID.
     */
    public function storePendingUserId(int $userId): void
    {
        $_SESSION['webauthn_pending_user_id'] = $userId;
        $_SESSION['webauthn_pending_time'] = \time();
    }

    /**
     * Retrieve and clear pending login user ID from session.
     *
     * @return  int|null User ID or null if not found or expired.
     */
    public function getPendingUserId(): ?int
    {
        $userId = $_SESSION['webauthn_pending_user_id'] ?? null;
        $fresh = $this->isFresh('webauthn_pending_time');
        unset($_SESSION['webauthn_pending_user_id'], $_SESSION['webauthn_pending_time']);

        return ($fresh && $userId !== null) ? (int)$userId : null;
    }

    /**
     * Check if there is a pending login user ID without clearing it.
     *
     * @return  int|null User ID or null if not found or expired.
     */
    public function hasPendingUserId(): ?int
    {
        $userId = $_SESSION['webauthn_pending_user_id'] ?? null;

        if (!$this->isFresh('webauthn_pending_time')) {
            return null;
        }

        return $userId !== null ? (int)$userId : null;
    }

    /**
     * Clear all WebAuthn session data.
     */
    public function clear(): void
    {
        unset($_SESSION['webauthn_registration_challenge']);
        unset($_SESSION['webauthn_registration_time']);
        unset($_SESSION['webauthn_authentication_challenge']);
        unset($_SESSION['webauthn_authentication_time']);
        unset($_SESSION['webauthn_pending_user_id']);
        unset($_SESSION['webauthn_pending_time']);
        unset($_SESSION['webauthn_pending_auth_hash']);
    }

    /**
     * Check that a stored timestamp exists and is no older than MAX_AGE (fails closed).
     *
     * @param   string $timeKey Session key holding the timestamp.
     * @return  bool True if fresh.
     */
    private function isFresh(string $timeKey): bool
    {
        $time = $_SESSION[$timeKey] ?? null;

        if (!\is_int($time)) {
            return false;
        }

        $age = \time() - $time;

        return $age >= 0 && $age <= self::MAX_AGE;
    }
}
