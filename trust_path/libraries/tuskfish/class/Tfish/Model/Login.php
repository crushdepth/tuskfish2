<?php

declare(strict_types=1);

namespace Tfish\Model;

/**
 * \Tfish\Model\Login class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Model for logging in.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Session $session Instance of the session management class.
 */

class Login
{
    use \Tfish\Traits\ValidateString;

    private $session;

    /**
     * Process admin login.
     *
     * Note that email validation is handled by the Session class.
     *
     * @param   string $email Email address.
     * @param   string $password Password.
     */
    public function login(string $email, string $password)
    {
        $email = $this->trimString($email);
        $this->session->login($email, $password);
    }

    /**
     * Logout the user and redirect to the login form.
     */
    public function logout()
    {
        $this->session->logout(TFISH_URL . 'login/');
    }

    /**
     * Set the session object.
     *
     * This will be deprecated when the DICE dependency injection container is adopted.
     *
     * @param   \Tfish\Session $session Instance of the session management class.
     */
    public function setSession(\Tfish\Session $session)
    {
        $this->session = $session;
    }
}
