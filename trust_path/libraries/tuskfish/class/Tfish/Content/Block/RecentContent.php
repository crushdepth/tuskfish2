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
       $this->title = $this->trimString($row['title']);
       $this->config = !empty($row['config']) ? \json_decode($row['config'], true) : [];
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
        $types = $this->config['type'];
        $sql = "SELECT `content`.`id`, `title`
                FROM `content`";
        $conditions = ["`onlineStatus` = 1"];

        if (!empty($types)) {
            $placeholders = \implode(',', \array_fill(0, \count($types), '?'));
            $conditions[] = "`type` IN ($placeholders)";
        }

        $sql .= " WHERE " . \implode(' AND ', $conditions);
        $sql .= " ORDER BY `date` DESC, `submissionTime` DESC LIMIT :limit";

        $statement = $database->preparedStatement($sql);
        $statement->bindValue(':limit', $this->config['items'], \PDO::PARAM_INT);

        foreach ($types as $index => $type) {
            $statement->bindValue($index + 1, $type, \PDO::PARAM_STR);
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
     * Return config data as JSON.
     *
     * @return array
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * Set config data as JSON.
     *
     * @param array $json
     * @return void
     */
    public function setConfig(array $json)
    {
        $validConfig = [];

        // Number of content items.
        $validConfig['items'] = $this->isInt($json['items'], 0, 20) ? $json['items'] : 0;

        // Tag filters.
        if (!empty($json['tag'])) {

            foreach ($json['tag'] as $tag) {
                if ($this->isInt($tag, 0, null)) {
                    $validConfig['tag'][] = $tag;
                }
            }
        }

        // Content type filter.
        if (!empty($json['type'])) {
            foreach ($json['type'] as $type) {
                $validConfig['type'][] = \array_key_exists($type, $this->listTypes()) ? $type : '';
            }
        }

        $this->config = $validConfig;
    }
}