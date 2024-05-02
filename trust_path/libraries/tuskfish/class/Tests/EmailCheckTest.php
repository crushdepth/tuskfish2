<?php

declare(strict_types=1);

namespace Tests;

/**
 * \Tests\EmailCheckTest class file.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.7
 * @since       2.0.7
 * @package     tests
 */

 /**
 * Unit tests for the EmailCheck validation trait.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.7
 * @since       2.0.7
 * @package     core
 * @uses        trait \Tfish\Traits\EmailCheck
 */

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
