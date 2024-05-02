<?php

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
