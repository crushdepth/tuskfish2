<?php

declare(strict_types=1);

namespace Tfish\Model;

/**
 * \Tfish\Model\BlockEdit class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 1.0
 * @since       1.1
 * @package     core
 */

/**
 * Model for adding and editing block objects.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\BlockOption Validate that email address conforms to specification.
 * @uses        trait \Tfish\Traits\HtmlPurifier Instance of HTMLPurifier class.
 * @uses        trait \Tfish\Traits\TagRead Retrieve tag information for display.
 * @uses        trait \Tfish\Traits\ValidateString Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\Session $session Instance of the Tuskfish session manager class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 * @var         \Tfish\Cache Instance of the Tfish cache class.
 */
class BlockEdit
{
    use \Tfish\Traits\BlockOption;
    use \Tfish\Traits\HtmlPurifier;
    use \Tfish\Traits\TagRead;
    use \Tfish\Traits\ValidateString;

    private $database;
    private $session;
    private $criteriaFactory;
    private $preference;
    private $cache;

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\Session $session Instance of the Tuskfish session manager class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
     * @param   \Tfish\Entity\Preference Instance of the Tfish site preferences class.
     * @param   \Tfish\Cache $cache Instance of the Tuskfish cache class.
     */
    public function __construct(
        \Tfish\Database $database,
        \Tfish\Session $session,
        \Tfish\CriteriaFactory $criteriaFactory,
        \Tfish\Entity\Preference $preference,
        \Tfish\Cache $cache
        )
    {
        $this->database = $database;
        $this->session = $session;
        $this->criteriaFactory = $criteriaFactory;
        $this->preference = $preference;
        $this->cache = $cache;
    }

    /** Actions. */

    /**
     * Edit block object.
     *
     * @param   int $id ID of block.
     * @return  object Block data as array.
     */
    public function edit(int $id): array
    {
        $row = $this->getRow($id);

        if (empty($row)) {
            return [];
        }

        if (!empty($row['config']))
            $row['config'] = \json_decode($row['config'], true);

        return $row;
    }

    /**
     * Insert a block into the database.
     *
     * @return  bool True on success, false on failure.
     */
    public function insert(): bool
    {
        $content = $this->validateForm($_POST['content']);

        // Insert block.
        if (!$this->database->insert('block', $content)) {
            return false;
        }

        // Insert associated blockRoutes.
        $blockId = $this->database->lastInsertId();

        if (!empty($_POST['route'])) {
            $routes = $this->validateRoutes($_POST['route']) ?? [];

            if (!$this->saveblockRoutes($blockId, $routes)) {
                return false;
            }
        }

        $this->cache->flush();

        return true;
    }

    /**
     * Insert the routes associated with a block into the blockRoute table.
     *
     * @param   int $id of the block associated with these routes.
     * @param   array $routes Array of routes associated with this block.
     * @return boolean
     */
    private function saveBlockRoutes(int $id, array $routes): bool
    {
        foreach ($routes as $route) {

            if (!$this->database->insert('blockRoute', ['blockId' => $id, 'route' => $route])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Update a block in the database.
     *
     * @return True on success, false on failure.
     */
    public function update(): bool
    {
        $content = $_POST['content'] ?? [];
        $validContent = $this->validateForm($content) ?? [];

        $routes = $_POST['route'] ?? [];
        $validRoutes = $this->validateRoutes($routes) ?? [];

        $id = (int) $validContent['id'];

        // Replace block routes.
        if (!$this->updateBlockRoutes($id, $validRoutes)) {
            return false;
        }

        // Update block.
        if (!$this->database->update('block', $id, $validContent)) {
            return false;
        }

        $this->cache->flush();

        return true;
    }

    /** Utilities. */

    /**
     * Get a single block data as array.
     *
     * Retrieves block-routes as a comma-delimited string, which must be converted to an array.
     *
     * @param   int $id ID of block.
     * @return  array Block data on success, empty array on failure.
     */
    private function getRow(int $id): array
    {
        $sql = "SELECT "
                . "`b`.*, "
                . "GROUP_CONCAT(`br`.`route`, ',') AS route "
            . "FROM "
                . "`block` AS `b` "
            . "LEFT JOIN "
                . "`blockRoute` AS `br` ON `b`.`id` = `br`.`blockId` "
            . "WHERE "
                . "`b`.`id` = :id "
            . "GROUP BY "
                . "`b`.`id`";

        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $row['route'] = $row['route'] ? explode(',', $row['route']) : [];
        }

        return $row ?: [];
    }

    /**
     * Update routes for a block.
     *
     * @param   int $id ID of the block.
     * @param   array $routes Array of routes this block should be displayed on.
     * @return  bool True on success, false on failure.
     */
    private function updateBlockRoutes(int $id, array $routes): bool
    {
        try {
            // Begin transaction.
            $this->database->beginTransaction();

            // Delete existing routes for this block.
            $sql = "DELETE FROM `blockRoute` WHERE `blockId` = :blockId";
            $statement = $this->database->preparedStatement($sql);

            $statement->bindValue(':blockId', $id, \PDO::PARAM_INT);
            if (!$statement->execute()) {
                $this->database->rollBack();
                \trigger_error(TFISH_BLOCK_ROUTE_UPDATE_FAILED, E_USER_ERROR);

                return false;
            }

            // Insert new routes, if any.
            if (!empty($routes)) {
                $sql = "INSERT INTO `blockRoute` (`blockId`, `route`) VALUES (:blockId, :route)";
                $statement = $this->database->preparedStatement($sql);
                $statement->bindValue(':blockId', $id, \PDO::PARAM_INT);

                $route = null;
                $statement->bindParam(':route', $route, \PDO::PARAM_STR);

                foreach ($routes as $routeValue) {
                    $route = $routeValue;
                    if (!$statement->execute()) {
                        $this->database->rollBack();
                        \trigger_error(TFISH_BLOCK_ROUTE_UPDATE_FAILED, E_USER_ERROR);

                        return false;
                    }
                }
            }

            // Commit transaction.
            $this->database->commit();
            return true;

        } catch (\Exception $e) {
            $this->database->rollBack();
            \trigger_error(TFISH_BLOCK_ROUTE_UPDATE_FAILED, E_USER_ERROR);

            return false;
        }
    }


    /**
     * Validate submitted form data for block.
     *
     * @param   array $form Submitted form data.
     * @return  array Validated form data.
     */
    public function validateForm(array $form): array
    {
        $clean = [];

        // ID.
        $id = ((int) ($form['id'] ?? 0));
        if ($id > 0) $clean['id'] = $id;

        // Type.
        $type = $this->trimString($form['type'] ?? '');

        if (!\array_key_exists($type, $this->blockTypes())) {
            \trigger_error(TFISH_ERROR_ILLEGAL_TYPE, E_USER_ERROR);
        }

        $clean['type'] = $type;

        // Template.
        $template = $this->trimString($form['template'] ?? '');
        $blockTemplates = $this->blockTemplates()[$clean['type']];

        if (!\array_key_exists($template, $blockTemplates)) {
            \trigger_error(TFISH_ERROR_ILLEGAL_TYPE, E_USER_ERROR);
        }

        $clean['template'] = $template;

        // Position.
        $position = $this->trimString($form['position'] ?? '');

        if ($position && !\array_key_exists($position, $this->blockPositions())) {
            \trigger_error(TFISH_ERROR_ILLEGAL_TYPE, E_USER_ERROR);
        }

        $clean['position'] = $position;

        // Weight.
        $weight = ((int) ($form['weight'] ?? 0));
        if ($weight >= 0) $clean['weight'] = $weight;

        // Title.
        $clean['title'] = $this->trimString($form['title'] ?? '');

        // Online status.
        $onlineStatus = !empty($form['onlineStatus']) ? (int) $form['onlineStatus'] : 0;

        if ($onlineStatus < 0 || $onlineStatus > 1) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

        $clean['onlineStatus'] = $onlineStatus;

        // HTML.
        $html = $this->trimString($form['html'] ?? '');
        $html = \str_replace(TFISH_LINK, 'TFISH_LINK', $html);
        $htmlPurifier = $this->getHtmlPurifier();
        $clean['html'] = $html ? $htmlPurifier->purify($html) : '';

        // Config.
        $config = $form['config'] ?? [];
        $json = \json_encode($config);

        if (!\json_validate($json)) {
            \trigger_error(TFISH_ERROR_INVALID_JSON, E_USER_ERROR);
        }

        $clean['config'] = $json ?? '';

        return $clean;
    }

    /**
     * Validate submitted form data for block.
     *
     * @param array $routes Submitted route data from form.
     * @return void Validated route data.
     */
    public function validateRoutes(array $routes): array
    {
        $verified = [];
        $blockRoutes = $this->blockRoutes();

        foreach ($routes as $route) {
            if (\in_array($route, $blockRoutes)) {
                $verified[] = $route;
            }
        }

        return $verified;
    }
}
