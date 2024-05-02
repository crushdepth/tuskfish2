<?php

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
