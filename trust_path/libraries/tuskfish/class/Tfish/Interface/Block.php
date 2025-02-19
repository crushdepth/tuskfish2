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
    /** Accessors */

    /**
     * Return block ID.
     *
     * @return integer
     */
    public function id(): int;

    /**
     * Return block type (fully qualified class).
     *
     * @return string
     */
    public function type(): string;

    /**
     * Return position.
     *
     * @return string
     */
    public function position(): string;

    /**
     * Return currently selected template file name.
     *
     * @return string
     */
    public function template(): string;

    /**
     * Return configuration template file name.
     *
     * @return string
     */
    public function configTemplate(): string;

    /**
     * Return block title.
     *
     * @return string
     */
    public function title(): string;

    /**
     * Return weight.
     *
     * @return integer
     */
    public function weight(): int;

    /**
     * Return static block contents from database (html).
     *
     * @return string
     */
    public function html(): string;

    /**
     * Return online status (0 = offline, 1 = online).
     *
     * @return integer
     */
    public function onlineStatus(): int;

    /**
     * Return config data.
     *
     * @return array
     */
    public function config();

    /**
     * Set and validate config data from JSON.
     *
     * @param string $json
     * @return void
     */
    public function setConfig(string $json);

    /**
     * Retrieve dynamically generated block content.
     *
     * @param \Tfish\Database $database
     * @param \Tfish\CriteriaFactory $criteriaFactory
     * @return void
     */
    public function content(\Tfish\Database $database, \Tfish\CriteriaFactory $criteriaFactory): void;

    /** Utilities */

    /**
     * Return template options for this block.
     *
     * @return array
     */
    public function listTemplates(): array;

    /**
     * Load a database row into a new block object.
     *
     * Call in constructor, before render().
     *
     * @return void
     */
    public function load(array $row): void;

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

    /**
     * Validate configuration settings.
     *
     * Invalid configuration options will be zeroed or set to default values.
     *
     * @param array $config
     * @return array Validated configuration data (whitelisted, type and range checked).
     */
    public function validateConfig(array $config): array;
}
