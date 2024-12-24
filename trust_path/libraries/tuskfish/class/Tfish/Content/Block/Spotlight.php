<?php

declare(strict_types=1);

namespace Tfish\Content\Block;

/**
 * \Tfish\Content\Block\Spotlight class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Block for highlighting a pieces of content.
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
class Spotlight implements \Tfish\Interface\Block
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\IntegerCheck;
    use \Tfish\Traits\ValidateString;

    private int $id = 0;
    private string $type = '\Tfish\Content\Block\Spotlight';
    private string $position = '';
    private string $title = 'Spotlight';
    private string $html = '';
    private array $config = [];
    private int $weight = 0;
    private string $template = 'spotlight-compact';
    private int $onlineStatus = 0;
    private mixed $content = false;

    /** Constructor. */
    public function __construct(array $row, \Tfish\Database $database, \Tfish\criteriaFactory $criteriaFactory)
    {
        $this->load($row);
        $this->content($database, $criteriaFactory);
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
       $this->setConfig($row['config']);
       $this->weight = (int)$row['weight'];
       $this->template = \in_array($row['template'], $this->listTemplates(), true)
           ? $row['template'] : 'spotlight-compact';
       $this->onlineStatus = ($row['onlineStatus'] == 1) ? 1 : 0;
    }

    /**
     * Retrieve content data from database.
     *
     * @param \Tfish\Database $database
     * @param \Tfish\CriteriaFactory $criteriaFactory
     * @return void
     */
    public function content(\Tfish\Database $database, \Tfish\CriteriaFactory $criteriaFactory): void
    {
        $id = $this->isInt($this->config['id'], 1) ? $this->config['id'] : 0;

        $criteria = $criteriaFactory->criteria();
        $criteria->add($criteriaFactory->item('id', $id));
        $criteria->add($criteriaFactory->item('onlineStatus', 1));

        $statement = $database->select('content', $criteria);
        $this->content = $statement->fetchObject('\Tfish\Content\Entity\Content');
        $statement->closeCursor();
    }

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
        return ['spotlight-compact'];
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
     * @param string $json
     * @return void
     */
    public function setConfig(string $json)
    {
        $validConfig = [];
        $config = !empty($json) ? \json_decode($json, true) : [];

        // ID of spotlighted content.
        $validConfig['id'] = $this->isInt($config['id'], 0) ? (int)$config['id'] : 0;

        // Show image?

        // Show a different image?

        $this->config = $validConfig;
    }
}