<?php

declare(strict_types=1);

namespace Tfish\Content\Block;

/**
 * \Tfish\Content\Block\FeaturedVideo class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Block for highlighting a featured video.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Content\Traits\ContentTypes Provides definition of permitted content object types.
 * @uses        trait \Tfish\Traits\IntegerCheck Validate and range check integers.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 */
class FeaturedVideo implements \Tfish\Interface\Block
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\IntegerCheck;
    use \Tfish\Traits\ValidateString;

    private int $id = 0;
    private string $type = '\Tfish\Content\Block\FeaturedVideo';
    private string $position = '';
    private string $title = 'Featured video';
    private string $html = '';
    private array $config = [];
    private int $weight = 0;
    private string $template = 'featured-video';
    private string $configTemplate = 'featured-video-config';
    private int $onlineStatus = 0;
    private mixed $content = false;

    /** Constructor. */
    public function __construct(array $row, \Tfish\Database $database, \Tfish\CriteriaFactory $criteriaFactory)
    {
        if (empty($row['id'])) return;

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
        $this->id = (int)($row['id'] ?? 0);
        $this->position = $this->trimString((string)($row['position'] ?? ''));
        $this->title = $this->trimString((string)($row['title'] ?? ''));
        $this->setConfig((string)($row['config'] ?? ''));
        $this->weight = (int)($row['weight'] ?? 0);
        $tpl = (string)($row['template'] ?? '');
        $this->template = \in_array($tpl, $this->listTemplates(), true) ? $tpl : 'featured-video';
        $this->onlineStatus = ((int)($row['onlineStatus'] ?? 0) === 1) ? 1 : 0;
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
        $cfgId = $this->config['id'] ?? 0;
        $id = $this->isInt($cfgId, 1) ? (int)$cfgId : 0;

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
            throw new \InvalidArgumentException(TFISH_ERROR_TEMPLATE_NOT_FOUND);
        }

        \ob_start();
        include $filepath;
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

        // Strict false check; guard json_validate() for PHP < 8.3.
        if ($json === false || (\function_exists('json_validate') && !\json_validate($json, 3))) {
            throw new \InvalidArgumentException(TFISH_ERROR_INVALID_JSON);
        }

        return $json;
    }

    /**
     * Returns a list of display-side template options for this block.
     *
     * Add your custom templates to this list (without .html extension) and put a template file
     * with the same name (with .html extension) in the Block directory.
     *
     * @return  array Array of type-template key values.
     */
    public function listTemplates(): array
    {
        return ['featured-video'];
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
     * @param string $json
     * @return void
     */
    public function setConfig(string $json): void
    {
        $decoded = \json_decode($json, true);
        $config = \is_array($decoded) ? $decoded : [];
        $this->config = $this->validateConfig($config);
    }

    /**
     * Validate configuration settings.
     *
     * Invalid configuration options will be zeroed or set to default values.
     *
     * @param array $config
     * @return array Validated configuration data (whitelisted, type and range checked).
     */
    public function validateConfig(array $config): array
    {
        $validConfig = [];

        // ID of spotlighted content.
        $validConfig['id'] = isset($config['id']) ? (int)$config['id'] : 0;

        // Show a different image (ID).

        return $validConfig;
    }
}
