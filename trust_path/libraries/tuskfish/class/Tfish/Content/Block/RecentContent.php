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
 * @uses        trait \Tfish\Conten\Traits\ContentTypes
 * @uses        trait \Tfish\Traits\IntegerCheck
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 */

class RecentContent implements \Tfish\Interface\Block
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\IntegerCheck;
    use \Tfish\Traits\ValidateString;

    private int $id = 0;
    private string $type = '\Tfish\Content\Block\RecentContent';
    private string $position = '';
    private string $title = 'Recent content';
    private string $html = '';
    private array $config = [];
    private int $weight = 0;
    private string $template = 'recent-content-compact';
    private string $configTemplate = 'recent-content-config';
    private int $onlineStatus = 0;
    private array $content = [];

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
       $this->type = $this->trimString($row['type']);
       $this->title = $this->trimString($row['title']);
       $this->setConfig($row['config']);
       $this->weight = (int)$row['weight'];
       $this->template = \in_array($row['template'], $this->listTemplates(), true)
           ? $row['template'] : 'recent-content-compact';
       $this->onlineStatus = ($row['onlineStatus'] == 1) ? 1 : 0;
    }

    /**
     * Retrieve content data from database.
     *
     * Block options:
     * 1. Number of content items to list (limit 20).
     * 2. Filter by content type (multi-select).
     * 3. Filter by tag (multi-select).
     *
     * @param \Tfish\Database $database
     * @param \Tfish\CriteriaFactory $criteriaFactory
     * @return void
     */
    public function content(\Tfish\Database $database, \Tfish\CriteriaFactory $criteriaFactory): void
    {
        $types = $this->config['type'] ?? [];
        $tags  = $this->config['tag']  ?? [];
        $sql = "SELECT `content`.`id`, `title`
        FROM `content` ";

        if (!empty($tags)) {
            $sql .= "INNER JOIN `taglink` ON `content`.`id` = `taglink`.`contentId` ";
        }

        $conditions = ["`onlineStatus` = 1"];

        // Filter by content types.
        if (!empty($types)) {
            $typePlaceholders = \implode(',', \array_fill(0, \count($types), '?'));
            $conditions[] = "`type` IN ($typePlaceholders)";
        }

        // Filter by tags.
        if (!empty($tags)) {
            $tagPlaceholders = \implode(',', \array_fill(0, \count($tags), '?'));
            $conditions[] = "`taglink`.`tagId` IN ($tagPlaceholders)";
        }

        $sql .= " WHERE " . \implode(' AND ', $conditions);
        $sql .= " ORDER BY `date` DESC, `submissionTime` DESC LIMIT :limit";

        $statement = $database->preparedStatement($sql);
        $statement->bindValue(':limit', $this->config['items'], \PDO::PARAM_INT);

        // Bind values for types
        $bindIndex = 1;
        foreach ($types as $type) {
            $statement->bindValue($bindIndex++, $type, \PDO::PARAM_STR);
        }

        // Bind values for tags
        foreach ($tags as $tag) {
            $statement->bindValue($bindIndex++, $tag, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_KEY_PAIR);
        $statement->execute();

        $this->content = $statement->fetchAll();
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
        return ['recent-content-compact'];
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
        $config = !empty($json) ? \json_decode($json, true) : [];

        // Number of content items.
        $numItems = (int) $config['numItems'] ?? 0;
        $validConfig['items'] = $this->isInt($numItems, 0, 20) ? $numItems : 0;

        // Tag filters.
        if (!empty($config['tag'])) {

            foreach ($config['tag'] as $key => $tag) {
                $tag = (int) $tag;
                if ($tag > 0) {
                    $validConfig['tag'][] = $tag;
                }
            }
        }

        // Content type filter.
        if (!empty($config['type'])) {
            foreach ($config['type'] as $type) {
                $validConfig['type'][] = \array_key_exists($type, $this->listTypes()) ? $type : '';
            }
        }

        $this->config = $validConfig;
    }
}
