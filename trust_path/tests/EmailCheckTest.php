<?php

use PHPUnit\Framework\TestCase;
use Tfish\Traits\EmailCheck;

class EmailCheckTest extends TestCase
{
    use EmailCheck;

    public function testIsEmailWithValidEmail(): void
    {
        $this->assertTrue($this->isEmail('test@example.com'));
    }

    public function testIsEmailWithInvalidEmail(): void
    {
        $this->assertFalse($this->isEmail('not_an_email'));
    }

    public function testIsEmailWithInvalidEmailFormat(): void
    {
        // An invalid email format should return false
        $this->assertFalse($this->isEmail('test@example'));
    }

    public function testIsEmailWithEmptyString(): void
    {
        // An empty string should return false
        $this->assertFalse($this->isEmail(''));
    }

    public function testIsEmailWithShortString(): void
    {
        // A string less than 3 characters should return false
        $this->assertFalse($this->isEmail('a@'));
    }

    public function testIsEmailWithWhiteSpace(): void
    {
        // A string with only whitespace should return false
        $this->assertFalse($this->isEmail('   '));
    }

    public function testIsEmailWithLeadingAndTrailingWhiteSpace(): void
    {
        // A string with leading and trailing whitespace should be trimmed and checked
        $this->assertTrue($this->isEmail('  test@example.com  '));
    }
}
