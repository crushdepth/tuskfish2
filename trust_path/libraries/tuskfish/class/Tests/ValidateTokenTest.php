<?php

/**
 * \Tests\ValidateTokenTest class file.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.7
 * @since       2.0.7
 * @package     tests
 */

 /**
 * Unit tests for the ValidateToken trait.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.7
 * @since       2.0.7
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateToken
 */

use PHPUnit\Framework\TestCase;
use Tfish\Traits\ValidateToken;

class ValidateTokenTest extends TestCase
{
    use ValidateToken;

    public function testValidateTokenWithValidToken(): void
    {
        // A valid token should return true
        $_SESSION['token'] = 'valid_token';
        $this->assertTrue($this->validateToken('valid_token'));
        unset($_SESSION['token']);
    }

    public function testValidateTokenWithInvalidToken(): void
    {
        // An invalid token should redirect and exit
        $this->expectOutputString('');
        $this->expectException(\PHPUnit\Framework\Error\Error::class);
        $this->validateToken('invalid_token');
    }

    public function testValidateTokenWithEmptyToken(): void
    {
        // An empty token should redirect and exit
        $this->expectOutputString('');
        $this->expectException(\PHPUnit\Framework\Error\Error::class);
        $this->validateToken('');
    }

    public function testValidateTokenWithMissingTokenInSession(): void
    {
        // If the token is missing from the session, it should redirect and exit
        $this->expectOutputString('');
        $this->expectException(\PHPUnit\Framework\Error\Error::class);
        $this->validateToken('some_token');
    }
}
