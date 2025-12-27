<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\Session class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 21.0
 * @since       1.0
 * @package     security
 */

/**
 * Provides functions for managing user sessions in a security-conscious manner.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     security
 * @uses        trait \Tfish\Traits\EmailCheck
 * @uses        trait \Tfish\Traits\Group
 * @uses        trait \Tfish\Traits\UrlCheck
 * @uses        trait \Tfish\Traits\ValidateString
 * @var         \Tfish\Database $db Instance of the Tuskfish database class.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish site preference class.
 */

class Session
{
    use Traits\EmailCheck;
    use Traits\Group;
    use Traits\UrlCheck;
    use Traits\ValidateString;

    /** Set within the start() method; ideally this should be injected. */
    private $db;
    private $preference;

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $db Database instance.
     * @param   \Tfish\Entity\Preference $preference Instance of Tuskfish preference class.
     */
    public function __construct(Database $db, Entity\Preference $preference)
    {
        $this->db = $db;
        $this->preference = $preference;
    }

    /** No cloning permitted */
    final public function __clone() {}

    /**
     * Unset session variables and destroy the session.
     */
    public function destroy()
    {
        $_SESSION = [];
        \session_destroy();
        \session_start();
        $this->setToken();
    }

    /**
     * Returns a login or logout link for insertion in the template.
     *
     * @return string HTML login or logout link.
     */
    public function getLoginLink(): string
    {
        if ($this->isLoggedIn()) {
            return '<a href="' . TFISH_URL . 'logout/">' . TFISH_LOGOUT . '</a>';
        } else {
            return '<a href="' . TFISH_URL . 'login/">' . TFISH_LOGIN . '</a>';
        }
    }

    /**
     * Shorthand admin (super user) privileges check.
     *
     * For added security this could retrieve an encrypted token, preferably the SSL session id,
     * although thats availability seems to depend on server configuration.
     *
     * @return bool True if admin false if not.
     */
    public function isAdmin(): bool
    {
        if ($this->verifyPrivileges() === 1) {
            return true;
        }

        return false;
    }

    /**
     * Shorthand editor privileges check (admin also qualifies).
     *
     * @return boolean
     */
    public function isEditor(): bool
    {
        $privileges = $this->verifyPrivileges();

        if ($privileges === 1 || $privileges === 2) {
            return true;
        }

        return false;
    }

    /**
     * Shorthand check if the user is logged in.
     *
     * DO NOT USE FOR AUTH CHECKS, DOES NOT PROVIDE GROUP INFORMATION.
     *
     * @return boolean True if logged in (ID is set), otherwise false.
     */
    public function isLoggedIn(): bool
    {
        if (!empty($_SESSION['id'])) {
            return true;
        }

        return false;
    }

    /**
     * Get the current user ID.
     *
     * @return int|null User ID or null if not logged in.
     */
    public function userId(): ?int
    {
        return isset($_SESSION['id']) ? (int)$_SESSION['id'] : null;
    }

    /**
     * Get the current user email.
     *
     * @return string|null User email or null if not logged in.
     */
    public function userEmail(): ?string
    {
        return $_SESSION['adminEmail'] ?? null;
    }


    /**
     * Return the target URL path for redirection AFTER a successful authentication (user group) challenge.
     *
     * Allows a user to be redirected onwards to their destination after passing an authentication challenge.
     * Note that the user must be a member of a group authorised to access the protected content.
     *
     * Convention: Use a relative URL (path and query string), not an absolute URL for portability.
     * The TFISH_URL constant should be used on the receiving side to construct the full URL.
     *
     * @return string $next URL path.
     */
    public function nextUrl(): ?string
    {
        $next = $_SESSION['nextUrl'] ?? null;
        unset($_SESSION['nextUrl']);

        return $next;
    }

    /**
     * Set the target URL for redirection pending a successful authentication (user group) challenge.
     *
     * Allows the intended destination to be stored for redirection when a user is subjected to
     * an authorisation check, for example to access member-ony content.
     *
     * @param string $path URL path.
     */
    public function setNextUrl(string $path = '')
    {
        $_SESSION['nextUrl'] = $path;
    }

    /**
     * Set title of a redirect screen.
     *
     * Single use only.
     */
    public function redirectTitle(): ?string
    {
        $title = $_SESSION['redirectTitle'] ?? null;
        unset($_SESSION['redirectTitle']);

        return $title;
    }

    /**
     * Set a custom title for a redirection page.
     *
     * Use to provide context for login challenges, error messages, and confirmation screens.
     */
    public function setRedirectTitle(string $title = ''): void
    {
        $_SESSION['redirectTitle'] = $this->trimString($title);
    }

    /**
     * Set a context message for a redirect screen.
     */
    public function redirectMessage(): ?string
    {
        $message = $_SESSION['redirectMessage'] ?? null;
        unset($_SESSION['redirectMessage']);

        return $message;
    }

    /**
     * set a custom message for a redirection page.
     *
     * Use to provide context for login challenges, error messages, and confirmation screens.
     */
    public function setRedirectMessage(string $message = ''): void
    {
        $_SESSION['redirectMessage'] = $this->trimString($message);
    }

    /**
     * Verify that the current session is valid and user is enabled and return current user group.
     *
     * If the password has changed since the user logged in, they will be denied access. A user
     * whose privileges are suspended (onlineStatus = 0) will also be denied access.
     *
     * @return int User group.
     */
    public function verifyPrivileges(): int
    {
        if (empty($_SESSION['adminEmail'])) {
            return 0;
        }

        $user = $this->_getUser($_SESSION['adminEmail']);

        if (empty($user)) {
            return 0;
        }

        if ($_SESSION['authHash'] !== \hash('sha256', $user['passwordHash'])) {
            return 0;
        }

        if ((int) $user['onlineStatus'] !== 1) {
            return 0;
        }

        return (int) $user['userGroup'];
    }

    /**
     * Checks if client IP address or user agent has changed.
     *
     * These tests can indicate session hijacking but are by no means definitive; however they do
     * indicate elevated risk and the session should be regenerated as a counter measure.
     *
     * @return bool True if IP/user agent are unchanged, false otherwise.
     */
    public function isClean(): bool
    {
        $browser_profile = '';

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $browser_profile .= $_SERVER['REMOTE_ADDR'];
        }

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $browser_profile .= $_SERVER['HTTP_USER_AGENT'];
        }

        $browser_profile = \hash('sha256', $browser_profile);

        if (isset($_SESSION['browser_profile'])) {
            return $_SESSION['browser_profile'] === $browser_profile;
        }

        $_SESSION['browser_profile'] = $browser_profile;

        return true;
    }

    /**
     * Checks if a session has expired and sets last seen activity flag.
     *
     * @return bool True if session has expired, false if not.
     */
    public function isExpired(): bool
    {
        // Check if session carries a destroyed flag and kill it if the grace timer has expired.
        if (isset($_SESSION['destroyed']) && \time() > $_SESSION['destroyed']) {
            return true;
        }

        // Check for "last seen" timestamp.
        $last_seen = isset($_SESSION['last_seen']) ? (int) $_SESSION['last_seen'] : false;

        // Check expiry (but not if sessionLife === 0).
        if ($last_seen && $this->preference->sessionLife() > 0) {
            if ($last_seen && (\time() - $last_seen) > ($this->preference->sessionLife() * 60)) {
                return true;
            }
        }

        // Session not seen before, add an activity timestamp.
        $_SESSION['last_seen'] = \time();

        return false;
    }

    /**
     * Authenticate the user and establish a session.
     *
     * The number of failed login attempts is tracked. Subsequent login attempts will sleep for
     * an equivalent number of seconds before processing, in sort to frustrate brute force attacks.
     * A successful login will reset the counter to zero. Note that the password field is
     * unrestricted content.
     *
     * @param string $email Input email.
     * @param string $password Input password.
     */
    public function login(string $email, string $password)
    {
        // Check email and password have been supplied
        if (empty($email) || empty($password)) {
            // Issue incomplete form warning and redirect to the login page.
            $this->logout(TFISH_URL . "login/");
        } else {
            // Validate the admin email (which functions as the username in Tuskfish CMS)
            $cleanEmail = $this->trimString($email);

            if ($this->isEmail($cleanEmail)) {
                $this->_login($cleanEmail, $password);
            } else {
                // Issue warning - email should follow email format
                $this->logout(TFISH_URL . "login/");
            }
        }
    }

    /** @internal */
    private function _login(string $cleanEmail, string $dirtyPassword)
    {
        // Query the database for a matching user.
        $user = $this->_getUser($cleanEmail);

        // Authenticate user by calculating their password hash and comparing it to the one on file.
        if ($user) {
            $this->_authenticateUser($user, $dirtyPassword);
        } else {
            // Redirect to login page.
            $this->logout(TFISH_URL . "login/");
            exit;
        }
    }

    /** @internal */
    private function _getUser(string $cleanEmail): array
    {
        $user = [];

        $statement = $this->db->preparedStatement("SELECT * FROM `user` WHERE "
                . "`adminEmail` = :cleanEmail");
        $statement->bindParam(':cleanEmail', $cleanEmail, \PDO::PARAM_STR);
        $statement->execute();
        $user = $statement->fetch(\PDO::FETCH_ASSOC);

        return $user ? $user : [];
    }

    /** @internal */
    private function _authenticateUser(array $user, string $dirtyPassword)
    {
        if (!\is_array($user)) {
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_ARRAY_OR_EMPTY);
        }

        // If the user has previous failed login atttempts sleep to frustrate brute force attacks.
        if ($user['loginErrors']) {
            $this->delayLogin((int) $user['loginErrors']);
        }

        // If this user is suspended, do not proceed any further.
        if ((int) $user['onlineStatus'] !== 1) {
            $this->logout(TFISH_URL . "login/");
            exit;
        }

        // If login successful regenerate session due to privilege escalation.
        if (\password_verify($dirtyPassword, $user['passwordHash'])) {
            $this->regenerate();
            $this->setLoginFlags($user);

            // Reset failed login counter to zero.
            $this->db->update('user', (int) $user['id'], ['loginErrors' => 0]);

            // Send an admin notification email.
            $this->notifyAdminLogin($user['adminEmail']);

            // Redirect onwards (if nextUrl() is set), or to group home page if not. Note that the
            // redirect will still return a login page if privileges are not sufficient to access.
            $next = $this->nextUrl();

            if (!empty($next)) {
                \header('Location: ' . TFISH_LINK . $next, true, 303);
            } else {
                \header('Location: ' . $this->groupHomes()[$user['userGroup']], true, 303);
            }
            exit;
        } else {
            // Increment failed login counter, destroy session and redirect to the login page.
            if ((int) $user['loginErrors'] < 15) {
                $this->db->updateCounter((int) $user['id'], 'user', 'loginErrors');
            }

            $this->logout(TFISH_URL . "login/");
            exit;
        }
    }

    /**
     * Delay login processing to frustrate brute force attacks.
     *
     * Login is cumulatively delayed by one second for each failed login.
     * Delay is capped at 15 seconds to prevent DOS / abuse of this feature.
     *
     * @param   int $seconds Number of seconds to delay the login attempt.
     */
    private function delayLogin(int $seconds)
    {
        $delay = ($seconds <= 15) ? $seconds : 15;
        \sleep($delay);
    }

    /**
     * Set the User ID and user group in the session.
     *
     * This function must ONLY be called after a successful login, as it is used as the basis for
     * all subsequent authentication checks.
     *
     * To guard against compromised sessions, the pasword hash is not used directly, but is hashed
     * itself.
     *
     * @param array $user User info as an array read from database.
     * @return void
     */
    private function setLoginFlags(array $user)
    {
        $_SESSION['id'] = $user['id'];
        $_SESSION['adminEmail'] = $user['adminEmail'];
        $_SESSION['authHash'] = \hash('sha256', $user['passwordHash']);
    }

    /**
     * Hashes and salts a password to harden it against dictionary attacks.
     *
     * Uses the default password hashing algorithm, which wa bcrypt as of PHP 7.2, with a cost
     * of 11. If logging in is too slow, you could consider reducing this to 10 (the default value).
     * Lowering it further will weaken the security of the hash.
     *
     * @param string $password Input password.
     * @return string Password hash, incorporating algorithm and difficulty information.
     */
    public function hashPassword(string $password): string
    {
        $options = ['cost' => 11];
        $password = \password_hash($password, PASSWORD_DEFAULT, $options);

        return $password;
    }

    /**
     * Destroys the current session on logout
     *
     * @param string $urlRedirect The URL to redirect the user to on logging out.
     */
    public function logout(string $urlRedirect = '')
    {
        $cleanUrl = '';

        if (!empty($urlRedirect)) {
            $urlRedirect = $this->trimString($urlRedirect);
            $cleanUrl = $this->isUrl($urlRedirect) ? $urlRedirect : '';
        }

        $this->_logout($cleanUrl);
    }

    /** @internal */
    private function _logout(string $cleanUrl)
    {
        // Unset all of the session variables.
        $_SESSION = [];

        // Destroy the session cookie, DESTROY IT ISILDUR!
        if (\ini_get("session.use_cookies")) {
            $params = \session_get_cookie_params();
            \setcookie(session_name(), '', \time() - 42000, $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]);
        }

        // Destroy the session and redirect
        \session_destroy();

        // No-store: avoid caching any personalized logout response
        \header('Cache-Control: no-store', true);
        $target = $cleanUrl ?: TFISH_URL;
        \header('Location: ' . $target, true, 303);
        exit;
    }

    /**
     * Authenticate user with WebAuthn and establish a session.
     *
     * Called after WebAuthn assertion has been cryptographically verified.
     * This method validates the user account is active and creates the session.
     * Does not redirect - returns bool so controller can send JSON response.
     *
     * @param int $userId User ID from verified WebAuthn credential.
     * @return bool True on success, false on failure.
     */
    public function loginWithWebAuthn(int $userId): bool
    {
        // Query database for user record
        $statement = $this->db->preparedStatement("SELECT * FROM `user` WHERE `id` = :userId");
        $statement->bindParam(':userId', $userId, \PDO::PARAM_INT);
        $statement->execute();
        $user = $statement->fetch(\PDO::FETCH_ASSOC);

        if (empty($user)) {
            return false;
        }

        // If user is suspended, do not proceed
        if ((int) $user['onlineStatus'] !== 1) {
            return false;
        }

        // Regenerate session due to privilege escalation
        $this->regenerate();
        $this->setLoginFlags($user);

        // Reset failed login counter to zero
        $this->db->update('user', (int) $user['id'], ['loginErrors' => 0]);

        // Send admin notification email
        $this->notifyAdminLogin($user['adminEmail']);

        return true;
    }

    /**
     * Notify admin of login.
     *
     * Sends an email to the admin email notifying that an admin login has occurred. This
     * provides an alert if an unsanctioned login occurs.
     */

    private function notifyAdminLogin(string $email)
    {
        $siteName = $this->preference->siteName() ? $this->preference->siteName() : TUSKFISH_CMS;
        $siteEmail = $this->preference->siteEmail();

        $to = $siteEmail;
        $subject = TFISH_LOGIN_NOTED;
        $headers = [
            'From' => $siteName . '<' . $siteEmail . '>',
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-type' => 'text/plain; charset=utf-8'
        ];
        $message = TFISH_LOGIN_NOTED_MESSAGE . xss($email) . '.';

        mail($to, $subject, $message, $headers);
    }

    /**
     * Regenerates the session ID.
     *
     * Called whenever there is a privilege escalation (login) or at random intervals to reduce
     * risk of session hijacking. Note that the cross-site request forgery validation token remains
     * the same, unless the session is destroyed. This is to prevent the random session ID
     * regeneration events creating false positive CSRF checks.
     *
     * Note that it allows the new and  old sessions to co-exist for a short period, this is to
     * avoid headaches with flaky network connections and asynchronous (AJAX) requests, as explained
     * in the PHP Manual warning: http://php.net/manual/en/function.session-regenerate-id.php
     */
    public function regenerate()
    {
        // If destroyed flag is set, no need to regenerate ID as it has already been done.
        if (isset($_SESSION['destroyed'])) {
            return;
        }

        // Flag old session for destruction in (arbitrary) 10 seconds.
        $_SESSION['destroyed'] = \time() + 10;

        // Create new session. Update ID and keep current session info. Old one is not destroyed.
        \session_regenerate_id(false);
        // Get the (new) session ID.
        $new_session_id = \session_id();
        // Lock the session and close it.
        \session_write_close();
        // Set the session ID to the new value.
        \session_id($new_session_id);
        // Now working with the new session. Note that old one still exists and both carry a
        // 'destroyed' flag.
        \session_start();
        // Set a cross-site request forgery token.
        $this->setToken();
        // Remove the destroyed flag from the new session. Old one will be destroyed next time
        // isExpired() is called on it.
        unset($_SESSION['destroyed']);
    }

    /**
     * Reset session data after a session hijacking check fails. This will force logout.
     */
    public function reset()
    {
        $_SESSION = [];
        $browser_profile = '';

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $browser_profile .= $_SERVER['REMOTE_ADDR'];
        }

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $browser_profile .= $_SERVER['HTTP_USER_AGENT'];
        }

        $_SESSION['browser_profile'] = \hash('sha256', $browser_profile);
    }

    /**
     * Initialises a session and sets session cookie parameters to security-conscious values.
     */
    public function start()
    {
        // Force session to use cookies to prevent the session ID being passed in the URL.
        \ini_set('session.use_cookies', '1');
        \ini_set('session.use_only_cookies', '1');
        \ini_set('session.use_trans_sid', '0');

        // Session name. If the preference has been messed up it will assign one.
        $sessionName = !empty($this->preference->sessionName()) ? $this->preference->sessionName() : 'tfish';

        // Session life time, in seconds. '0' means until the browser is closed.
        $lifetime = $this->preference->sessionLife() * 60;

        // Path on the domain where the cookie will work. Use a single slash for all paths (default,
        // as there are admin checks in some templates).
        $path = '/';

        // Cookie domain, for example www.php.net. To make cookies visible on all subdomains
        // (default) prefix with dot eg. '.php.net'
        $host   = $_SERVER['SERVER_NAME'] ?? '';
        $domain = \strncasecmp($host, 'www.', 4) === 0 ? \substr($host, 4) : $host;

        // If true the cookie will only be sent over secure connections.
        // Note: If using NGINX as reverse proxy or Cloudflare tunnel to terminate SSL, you should lock this to
        // true (use the commented out line as an alternative).
        $secure = isset($_SERVER['HTTPS']);
        // $secure = true;

        // If true PHP will *attempt* to send the httponly flag when setting the session cookie.
        $http_only = true;

        // Instruct browsers to disallow embedding of site in frame. Options: strict, lax, none.
        $samesite = 'strict';

        // Set the parameters and start the session.
        \session_name($sessionName);
        $options = [
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' =>$http_only,
            'samesite' => $samesite,
        ];
        \session_set_cookie_params($options);
        \session_start();

        // Set a CSRF token.
        $this->setToken();

        // Check if the session has expired.
        if ($this->isExpired())
            $this->destroy();

        // Check for signs of session hijacking and regenerate if at risk. 10% chance of doing it
        // anyway.
        if (!$this->isClean()) {
            $this->reset();
            $this->regenerate();
        } elseif (rand(1, 100) <= 10) {
            $this->regenerate();
        }
    }

    /**
     * Sets a token for use in cross-site request forgery checks on form submissions.
     *
     * A random token is generated and stored in the current session (if not already set). The value
     * of this token is included as a hidden field in forms when they are loaded by the user. This
     * allows forms to be validated via validateToken().
     */
    public function setToken()
    {
        if (empty($_SESSION['token'])) {
            $_SESSION['token'] = \bin2hex(random_bytes(32)) ;
        }
    }

    /**
     * Authenticate the user with two factors and establish a session.
     *
     * Requires a Yubikey hardware token as the second factor. Note that the authenticator type
     * is not declared, as the desired response is to logout and redirect, rather than to throw
     * an error.
     *
     * @param string $dirtyPassword Input password.
     * @param string $dirtyOtp Input Yubikey one-time password.
     * @param \Yubico\Auth_yubico $yubikey Instance of the Yubico authenticator class.
     */
    public function twoFactorLogin(string $dirtyPassword, string $dirtyOtp,
            \Yubico\Auth_yubico $yubikey)
    {
        // Check password, OTP and Yubikey have been supplied
        if (empty($dirtyPassword) || empty($dirtyOtp) || empty($yubikey)) {
            $this->logout(TFISH_URL . "login/");
            exit;
        }

        $dirtyOtp = $this->trimString($dirtyOtp);

        // Yubikey OTP should be 44 characters long.
        if (\mb_strlen($dirtyOtp, "UTF-8") != 44) {
            $this->logout(TFISH_URL . "login/");
            exit;
        }

        // Yubikey OTP should be alphabetic characters only.
        if (!$this->isAlpha($dirtyOtp)) {
            $this->logout(TFISH_URL . "login/");
            exit;
        }

        // Public ID is the first 12 characters of the OTP.
        $dirtyId = \mb_substr($dirtyOtp, 0, 12, 'UTF-8');

        $this->_twoFactorLogin($dirtyId, $dirtyPassword, $dirtyOtp, $yubikey);
    }

    /** @internal */
    private function _twoFactorLogin(string $dirtyId, string $dirtyPassword, string $dirtyOtp,
        \Yubico\Auth_yubico $yubikey)
    {
        $first_factor = false;
        $second_factor = false;
        $cleanId = $this->trimString($dirtyId);

        $user = $this->_getYubikeyUser($cleanId);

        if (empty($user)) {
            $this->logout(TFISH_URL . "login/");
            exit;
        }

        // If the user has previous failed login attempts sleep to frustrate brute force attacks.
        if ($user['loginErrors']) {
            $this->delayLogin((int) $user['loginErrors']);
        }

        // If this user is suspended, do not proceed any further.
        if ((int) $user['onlineStatus'] !== 1) {
            $this->logout(TFISH_URL . "login/");
            exit;
        }

        // First factor authentication: Calculate password hash and compare to the one on file.
        if (\password_verify($dirtyPassword, $user['passwordHash'])) {
            $first_factor = true;
        }

        // Second factor authentication: Submit one-time password to Yubico authentication server.
        // Sync is set to 100 (most secure), timeout for responses is 15 seconds.
        $second_factor = $yubikey->verify($dirtyOtp, null, false, 100, 15);

        // If both checks are good regenerate session due to priviledge escalation and login.
        if ($first_factor === true && $second_factor === true) {
            $this->regenerate();
            $this->setLoginFlags($user);

            // Reset failed login counter to zero.
            $this->db->update('user', (int) $user['id'], ['loginErrors' => 0]);

            // Send email notification of login to this account.
            $this->notifyAdminLogin($user['adminEmail']);

            \header('Location: ' . TFISH_ADMIN_URL);
            exit;
        }

        // Fail: Increment failed login counter, destroy session and redirect to the login page.
        if ((int) $user['loginErrors'] < 15) {
            $this->db->updateCounter((int) $user['id'], 'user', 'loginErrors');
        }

        $this->logout(TFISH_URL . "login/");
        exit;
    }

    /** @internal */
    private function _getYubikeyUser(string $cleanId)
    {
        $user = false;

        $statement = $this->db->preparedStatement("SELECT * FROM user WHERE "
                . "`yubikeyId` = :yubikeyId OR "
                . "`yubikeyId2` = :yubikeyId OR "
                . "`yubikeyId3` = :yubikeyId");
        $statement->bindParam(':yubikeyId', $cleanId, \PDO::PARAM_STR);
        $statement->execute();
        $user = $statement->fetch(\PDO::FETCH_ASSOC);

        return $user;
    }
}
