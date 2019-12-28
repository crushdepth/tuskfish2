<?php

namespace Tfish\Traits;

/**
 * \Tfish\Traits\ValidateToken trait file.
 * 
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Provides method for validating cross-site request forgery tokens.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait ValidateToken
{
    /**
     * Validate a cross-site request forgery token from a form submission.
     * 
     * Forms contain a hidden field with a random token taken from the user's session. This token
     * is used to validate that a form submission did indeed originate from the user, by comparing
     * the value against that stored in the user's session. If they do not match then the request
     * could be a forgery and the form submission should be rejected.
     * 
     * @param string $token A form token to validate against the user's session.
     * @return boolean True if token is valid.
     */
    public function validateToken(string $token)
    {
        $cleanToken = $this->trimString($token);

        // Valid token.
        if (!empty($_SESSION['token']) && $_SESSION['token'] === $cleanToken) {
            return true;
        }
        
        // Invalid token - redirect to warning message and cease processing the request.
        \header('location: ' . TFISH_URL . 'token/');
        exit;
    }
}

