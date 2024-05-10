<?php

declare(strict_types=1);

namespace Tests;

/**
 * \Tests\TraversalCheckTest class file.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.7
 * @since       2.0.7
 * @package     tests
 */

 /**
 * Unit tests for the TraversalCheck validation trait.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.7
 * @since       2.0.7
 * @package     core
 * @uses        trait \Tfish\Traits\TraversalCheck
 */

use PHPUnit\Framework\TestCase;
use Tfish\Traits\TraversalCheck;

class TraversalCheckTest extends TestCase
{
    use TraversalCheck;

    public function testHasTraversalorNullByteWithValidPath(): void
    {
        // A valid path should return false
        $this->assertFalse($this->hasTraversalorNullByte('/path/to/file.txt'));
    }

    public function testHasTraversalorNullByteWithTraversal(): void
    {
        // A path with traversal should return true
        $this->assertTrue($this->hasTraversalorNullByte('../path/to/file.txt'));
    }

    public function testHasTraversalorNullByteWithNullByte(): void
    {
        // A path with null byte should return true
        $this->assertTrue($this->hasTraversalorNullByte('/path/to/file%00.txt'));
    }

    public function testHasTraversalorNullByteWithEncodedTraversal(): void
    {
        // A path with encoded traversal should return true
        $this->assertTrue($this->hasTraversalorNullByte('%2e%2e/path/to/file.txt'));
    }

    public function testHasTraversalorNullByteWithMultipleTraversals(): void
    {
        // A path with multiple traversals should return true
        $this->assertTrue($this->hasTraversalorNullByte('../..//path/to/file.txt'));
    }

    public function testHasTraversalorNullByteWithEmptyPath(): void
    {
        // An empty path should return false
        $this->assertFalse($this->hasTraversalorNullByte(''));
    }
}
