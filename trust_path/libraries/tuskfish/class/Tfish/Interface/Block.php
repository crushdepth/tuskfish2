<?php

declare(strict_types=1);

namespace Tfish\Interface;

/**
 * \Tfish\Interface\Block class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Interface that ensures compliance with minimum block signature.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

interface Block
{
    /**
     * Return block ID.
     *
     * @return integer
     */
    public function id(): int;

    /**
     * Return block title.
     *
     * @return string
     */
    public function title(): string;

    /**
     * Return block contents (html).
     *
     * @return string
     */
    public function html(): string;

    /**
     * Return template options for this block.
     *
     * @return array
     */
    public function listTemplates(): array;

    /**
     * Render the block.
     *
     * Call render() from the constructor, it is responsible for populating the block with data
     * and storing the result in the $html property. Wrap the assignment to $html in output
     * buffering so that it doesn't output immediately.
     *
     * See Content/RecentContent.php for an example.
     *
     * @return void
     */
    public function render(): void;
}
