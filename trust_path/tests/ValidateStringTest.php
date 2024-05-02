<?php

use PHPUnit\Framework\TestCase;
use Tfish\Traits\ValidateString;

class ValidateStringTest extends TestCase
{
    use ValidateString;

    public function testEncodeEscapeUrlWithValidUrl(): void
    {
        // A valid URL should be properly encoded and escaped
        $this->assertEquals('http%3A%2F%2Fexample.com%2Fpath%3Fquery%3Dvalue', $this->encodeEscapeUrl('http://example.com/path?query=value'));
    }

    public function testIsAlnumWithValidAlnumString(): void
    {
        // A valid alphanumerical string should return true
        $this->assertTrue($this->isAlnum('abc123'));
    }

    public function testIsAlnumWithInvalidAlnumString(): void
    {
        // An invalid alphanumerical string should return false
        $this->assertFalse($this->isAlnum('abc@123'));
    }

    public function testIsAlnumUnderscoreWithValidString(): void
    {
        // A valid alphanumerical string with underscores should return true
        $this->assertTrue($this->isAlnumUnderscore('abc_123'));
    }

    public function testIsAlnumUnderscoreWithInvalidString(): void
    {
        // An invalid alphanumerical string with underscores should return false
        $this->assertFalse($this->isAlnumUnderscore('abc@_123'));
    }

    public function testIsAlphaWithValidAlphaString(): void
    {
        // A valid alphabetical string should return true
        $this->assertTrue($this->isAlpha('abc'));
    }

    public function testIsAlphaWithInvalidAlphaString(): void
    {
        // An invalid alphabetical string should return false
        $this->assertFalse($this->isAlpha('abc123'));
    }

    public function testIsUtf8WithValidUtf8String(): void
    {
        // A valid UTF-8 encoded string should return true
        $this->assertTrue($this->isUtf8('Hello, 世界'));
    }

    public function testIsUtf8WithInvalidUtf8String(): void
    {
        // An invalid UTF-8 encoded string should return false
        $this->assertFalse($this->isUtf8(utf8_encode('Hello, world!')));
    }

    public function testTrimStringWithTrailingWhitespace(): void
    {
        // A string with trailing whitespace should be properly trimmed
        $this->assertEquals('Hello', $this->trimString('Hello   '));
    }

    public function testTrimStringWithControlCharacters(): void
    {
        // A string with control characters should be properly trimmed
        $this->assertEquals('Hello', $this->trimString("Hello\x00"));
    }

    public function testTrimStringWithNonStringInput(): void
    {
        // A non-string input should return an empty string
        $this->assertEquals('', $this->trimString(123));
    }
}
