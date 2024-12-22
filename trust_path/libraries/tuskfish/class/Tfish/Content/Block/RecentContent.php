<?php

declare(strict_types=1);

namespace Tfish\Content\Block;

/**
 * \Tfish\Content\Block\RecentContent class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Block for displaying a list of recent content.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         object $model Classname of the model used to display this page.
 * @var         object $viewModel Classname of the viewModel used to display this page.
 */

class RecentContent implements \Tfish\Interface\Block
{
    private string $type = '';
    private int $id = 0;
    private string $position = '';
    private string $title = 'Recent content';
    private string $html = '';
    private string $config = '';
    private int $weight = 0;
    private string $template = 'recent-content-compact';
    private int $onlineStatus = 0;

    /** Constructor. */
    public function __construct()
    {
        $this->render();
    }

    /**
     * TODO:
     *
     * 1. Block options handling (serialise, unserialse, verify JSON).
     * 2. Block management interface.
     */

    /**
     * Render the block and store output in $html (output buffering is required).
     *
     * @return void
     */
    public function render(): void
    {
        // To be fully functional need to read content from database.
        $content = [2 => "Sample title", 3 => "Another title", 4 => "Last title"];

        // Retrieve last 5 content items from database.
        

        $filepath = TFISH_CONTENT_BLOCK_PATH . $this->template . '.html';

        if (!\file_exists($filepath)) {
            \trigger_error(TFISH_ERROR_TEMPLATE_NOT_FOUND, E_USER_NOTICE);
            exit;
        }

        \ob_start();
        include TFISH_CONTENT_BLOCK_PATH . $this->template . '.html';
        $this->html = \ob_get_clean();
    }

    /**
     * Returns a list of display-side template options for this block.
     *
     * Add your custom templates to this list (without .html extension) and put a template file
     * with the same name (with .html extensin) in the Block directory.
     *
     * @return  array Array of type-template key values.
     */
    public function listTemplates(): array
    {
        return ['recent-content-compact'];
    }

    /** Accessors */

    /**
     * Return block content (html).
     *
     * @return string
     */
    public function html(): string
    {
        return $this->html;
    }

    /**
     * Return ID.
     *
     * @return integer
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * Return title.
     *
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }
}