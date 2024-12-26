<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\FrontController class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Top level controller that handles incoming requests and oversees the page generation life cycle.
 *
 * Components are instantiated according to the a static routing table. The controller runs the
 * relevant action on the viewModel, which draws data from the model and caches it. The viewModel
 * is assigned to the template by the view. Templates access data from the viewModel directly.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\TraversalCheck
 * @uses        trait \Tfish\Traits\ValidateString
 * @var         \Tfish\Session $session Instance of the Tuskfish session class.
 * @var         object $view
 * @var         object $controller
 * @var         \Tfish\CriteriaFactory $criteriaFactory
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 */

class FrontController
{
    use Traits\TraversalCheck;
    use Traits\ValidateString;

    private $session;
    private $view;
    private $controller;
    private $criteriaFactory;
    private $database;

    /**
     * Constructor
     *
     * @param   \Dice\Dice $dice DICE dependency injection container.
     * @param   \Tfish\Session $session Instance of the Tuskfish session class.
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
     * @param   \Tfish\Entity\Preference Instance of the Tfish site preferences class.
     * @param   \Tfish\Entity\Metadata  Instance of the Tfish metadata class.
     * @param   \Tfish\Cache Instance of the Tfish cache class.
     * @param   \Tfish\Route Instance of the Tfish route class.
     * @param   string $path URL path associated with this request.
     */
    public function __construct(
        \Dice\Dice $dice,
        Session $session,
        Database $database,
        CriteriaFactory $criteriaFactory,
        Entity\Preference $preference,
        Entity\Metadata $metadata,
        Cache $cache,
        Route $route,
        string $path)
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
        $this->session = $session;

        $session->start();
        $this->checkSiteClosed($preference, $path);
        $this->checkAccessRights($route);

        // Create MVVM components with dice (as they have variable dependencies).
        $pagination = $dice->create('\\Tfish\\Pagination', [$path]);
        $model = $dice->create($route->model());
        $viewModel = $dice->create($route->viewModel(), [$model]);
        $this->view = $dice->create($route->view(), [$viewModel]);
        $this->controller = $dice->create($route->controller(),[$model, $viewModel]);

        $action = $this->trimString(($_REQUEST['action'] ?? 'display'));

        // Attempt to inject a bad action will throw a soft error.
        if (!$this->isAlpha($action) || !\method_exists($this->controller, $action)) {
            \header('Location: ' . TFISH_URL . 'error/');
            exit;
        }

        \ob_start();

        $cacheParams = $this->controller->{$action}();
        $cache->check($path, $cacheParams);

        $this->renderLayout($metadata, $viewModel, $path);
        $cache->save($cacheParams, \ob_get_contents());
        $database->close();
        return \ob_end_flush();
    }

    /**
     * Check if the present route is restricted to admins.
     *
     * @param   \Tfish\Route $route
     */
    private function checkAccessRights(Route $route)
    {
        // Route restricted to admin.
        if ($route->loginRequired() === 1 && !$this->session->isAdmin()) {
            \header('Location: ' . TFISH_URL . 'login/');
            exit;
        }

        // Route restricted to Editors and admin.
        if ($route->loginRequired() === 2 && !$this->session->isEditor()) {
            \header('Location: ' . TFISH_URL . 'login/');
            exit;
        }
    }

    /**
     * Check if site is closed and redirect non-admins to login.
     *
     * @param   \Tfish\Entity\Preference $preference Tfish preference object.
     */
    private function checkSiteClosed(Entity\Preference $preference, string $path)
    {
        if ($preference->closeSite() && !$this->session->isAdmin() && $path !== '/login/') {
            \header('Location: ' . TFISH_URL . 'login/');
            exit;
        }
    }

    /**
     * Renders the layout (main template) of a theme.
     *
     * @param \Tfish\Entity\Metadata $metadata Instance of the Tuskfish metadata class.
     * @param mixed $viewModel Instance of a viewModel class.
     * @param string $path URL path (route) associated with this request.
     */
    private function renderLayout(Entity\Metadata $metadata, $viewModel, string $path)
    {
        $page = $this->view->render();
        $blocks = $this->renderBlocks($path);
        $metadata->update($viewModel->metadata());
        $session = $this->session;

        $theme = $this->trimString($viewModel->theme() ?? 'default');
        $layout = $this->trimString($viewModel->layout() ?? 'layout');

        if ($this->hasTraversalorNullByte($theme) || $this->hasTraversalorNullByte($layout)) {
            \trigger_error(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE, E_USER_ERROR);
            exit; // Hard stop due to high probability of abuse.
        }

        include_once TFISH_THEMES_PATH . $theme . "/" . $layout . ".html";
    }

    /**
     * Renders the blocks for insertion into the layout (main template) of a theme.
     *
     * Blocks are loaded based on the URL path (route) associated with this request.
     * Blocks are sorted by ID. Display in layout.html via echo, eg: <?php echo $block[42]; ?>
     *
     * @param string $path URL path.
     * @return array Blocked indexed by ID.
     */
    private function renderBlocks(string $path): array
    {
        $blocks = [];

        $sql = "SELECT `block`.`id`, `type`, `position`, `title`, `config`, `weight`, "
            . "`template`, `onlineStatus` FROM `block` "
            . "INNER JOIN `blockRoute` ON `block`.`id` = `blockRoute`.`blockId` "
            . "WHERE `blockRoute`.`route` = :path";

        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':path', $path, \PDO::PARAM_STR);
        $statement->setFetchMode(\PDO::FETCH_UNIQUE); // Index results by ID.
        $statement->execute();
        $rows = $statement->fetchAll();

        foreach ($rows as $key => $row) {
            $className = $row['type'];
            if (\class_exists($className)) {
                $blocks[$row['id']] = new $className($row, $this->database, $this->criteriaFactory);
            }
        }

        return $blocks;
    }
}
