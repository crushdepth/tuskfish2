<?php

declare(strict_types=1);

namespace Tfish\Content\Block;

/**
 * \Tfish\Content\Block\Html class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Static HTML block.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Conten\Traits\ContentTypes Provides definition of permitted content object types.
 * @uses        trait \Tfish\Traits\IntegerCheck Validate and range check integers.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 */
class Html implements \Tfish\Interface\Block
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\IntegerCheck;
    use \Tfish\Traits\ValidateString;

    private int $id = 0;
    private string $type = '\Tfish\Content\Block\Html';
    private string $position = '';
    private string $title = '';
    private string $html = '';
    private array $config = [];
    private int $weight = 0;
    private string $template = 'html';
    private string $configTemplate = 'html-config';
    private int $onlineStatus = 0;
    private mixed $content = false;

    /** Constructor. */
    public function __construct(array $row, \Tfish\Database $database, \Tfish\criteriaFactory $criteriaFactory)
    {
        $this->load($row);
        $this->render();
    }

    /**
     * Populate block object with row from database.
     *
     * @param array $row Database entry for this block.
     * @return void
     */
    public function load(array $row): void
    {
       $this->id = (int)$row['id'];
       $this->position = $this->trimString($row['position']);
       $this->title = $this->trimString($row['title']);
       $this->html = $this->trimString($row['html']);
       $this->setConfig($row['config']);
       $this->weight = (int)$row['weight'];
       $this->template = \in_array($row['template'], $this->listTemplates(), true)
           ? $row['template'] : 'html';
       $this->onlineStatus = ($row['onlineStatus'] == 1) ? 1 : 0;
    }

    /**
     * Retrieve content data from database.
     *
     * Not required for static HTML block, which holds it's own content.
     *
     * @param \Tfish\Database $database
     * @param \Tfish\CriteriaFactory $criteriaFactory
     * @return void
     */
    public function content(\Tfish\Database $database, \Tfish\CriteriaFactory $criteriaFactory): void {}

    /**
     * Render the block and store output in $html (output buffering is required).
     *
     * @return void
     */
    public function render(): void
    {
        $content = $this->content;

        $filepath = TFISH_CONTENT_BLOCK_PATH . $this->template . '.html';

        if (!\file_exists($filepath)) {
            \trigger_error(TFISH_ERROR_TEMPLATE_NOT_FOUND, E_USER_ERROR);
            exit;
        }

        \ob_start();
        include TFISH_CONTENT_BLOCK_PATH . $this->template . '.html';
        $this->html = \ob_get_clean();
    }

    /** Utilities */

    /**
     * Serialise config data as JSON in preparation for DB storage.
     *
     * @return string
     */
    public function serialiseConfig(): string
    {
        $json = \json_encode($this->config);

        if ($json == false || !\json_validate($json, 3)) {
            \trigger_error(TFISH_ERROR_INVALID_JSON, E_USER_ERROR);
            exit;
        }

        return $json;
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
        return ['html'];
    }

    /** Accessors */

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
     * Return block type (fully qualified class).
     *
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Return position.
     *
     * @return string
     */
    public function position(): string
    {
        return $this->position;
    }

    /**
     * Return currently selected template file name.
     *
     * @return string
     */
    public function template(): string
    {
        return $this->template;
    }

    /**
     * Return configuration template file name.
     *
     * @return string
     */
    public function configTemplate(): string
    {
        return $this->configTemplate;
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

    /**
     * Return weight.
     *
     * @return integer
     */
    public function weight(): int
    {
        return $this->weight;
    }

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
     * Return online status (0 = offline, 1 = online).
     *
     * @return integer
     */
    public function onlineStatus(): int
    {
        return $this->onlineStatus;
    }

    /**
     * Return config data.
     *
     * @return array
     */
    public function config(): array
    {
        return $this->config;
    }


    /**
     * Set and validate config data from JSON.
     *
     * There are no configuration options for HTML blocks at this time.
     *
     * @param string $json
     * @return void
     */
    public function setConfig(string $json)
    {
        $this->config = [];
    }
}
