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
        $content = $this->content($database, $criteriaFactory);
        $this->render($content);
    }

    /**
     * Populate object with row from database.
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
     * Retrieve content from database.
     *
     * @param \Tfish\Database $database
     * @param \Tfish\CriteriaFactory $criteriaFactory
     * @return void
     */
    public function content(\Tfish\Database $database, \Tfish\CriteriaFactory $criteriaFactory): void
    {
        $criteria = $criteriaFactory->criteria();
        $criteria->setLimit(5);
        $criteria->setSort('date');
        $criteria->setOrder('DESC');
        $criteria->setSecondarySort('submissionTime');
        $criteria->setSecondaryOrder('DESC');
        $criteria->add($criteriaFactory->item('onlineStatus', 1));
        // $criteria->setTag([$cleanParams['tag']]);

        // Just want to select ID, title with a link
        $statement = $database->select('content', $criteria, ['id', 'title']);
        $this->content = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
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