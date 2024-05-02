<?php

declare(strict_types=1);

namespace Tests;

/**
 * \Tests\IntegerCheckTest class file.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.7
 * @since       2.0.7
 * @package     tests
 */

 /**
 * Unit tests for the IntegerCheck validation trait.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.7
 * @since       2.0.7
 * @package     core
 * @uses        trait \Tfish\Traits\IntegerCheck
 */

use PHPUnit\Framework\TestCase;
use Tfish\Traits\IntegerCheck;

class IntegerCheckTest extends TestCase
{
    use IntegerCheck;

    public function testIsIntWithValidInteger(): void
    {
        $this->assertTrue($this->isInt(5));
    }

    public function testIsIntWithInvalidInteger(): void
    {
        $this->assertFalse($this->isInt('not an integer'));
    }

    public function testIsIntWithInRangeInteger(): void
    {
        $this->assertTrue($this->isInt(5, 0, 10));
    }

    public function testIsIntWithOutOfRangeInteger(): void
    {
        $this->assertFalse($this->isInt(15, 0, 10));
    }

    public function testIsIntWithMinValue(): void
    {
        $this->assertTrue($this->isInt(5, 5));
    }

    public function testIsIntWithLessThanMinValue(): void
    {
        $this->assertFalse($this->isInt(3, 5));
    }

    public function testIsIntWithMaxValue(): void
    {
        $this->assertTrue($this->isInt(5, null, 10));
    }

    public function testIsIntWithMoreThanMaxValue(): void
    {
        $this->assertFalse($this->isInt(15, null, 10));
    }
}
