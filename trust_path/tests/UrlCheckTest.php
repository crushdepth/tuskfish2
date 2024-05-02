<?php

use PHPUnit\Framework\TestCase;
use Tfish\Traits\UrlCheck;

class UrlCheckTest extends TestCase
{
    use UrlCheck;

    public function testIsUrlWithValidHttpUrl(): void
    {
        // A valid HTTP URL should return true
        $this->assertTrue($this->isUrl('http://example.com'));
    }

    public function testIsUrlWithValidHttpsUrl(): void
    {
        // A valid HTTPS URL should return true
        $this->assertTrue($this->isUrl('https://example.com'));
    }

    public function testIsUrlWithInvalidProtocol(): void
    {
        // An invalid protocol should return false
        $this->assertFalse($this->isUrl('ftp://example.com'));
    }

    public function testIsUrlWithInvalidUrl(): void
    {
        // An invalid URL format should return false
        $this->assertFalse($this->isUrl('not_a_url'));
    }

    public function testIsUrlWithEmptyString(): void
    {
        // An empty string should return false
        $this->assertFalse($this->isUrl(''));
    }

    public function testIsUrlWithInvalidDomain(): void
    {
        // A URL with an invalid domain should return false
        $this->assertFalse($this->isUrl('http://invalid_domain'));
    }

    public function testIsUrlWithMissingProtocol(): void
    {
        // A URL missing the protocol should return false
        $this->assertFalse($this->isUrl('example.com'));
    }
}
