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
    use Traits\Group;
    use Traits\TraversalCheck;
    use Traits\ValidateString;

    /**
     * Cache-param keys that mean the visitor has moved to a deeper view of a route (single item,
     * pagination, tag filter, search) and so suppress that route's blocks. Read against the
     * controller's $cacheParams, which records a key only when its value is meaningful. 'type' is
     * deliberately excluded: it is genuine navigation on the listing but a hardcoded constant on
     * the gallery route, so it cannot be used as a reliable deeper-view signal. Add a key here when
     * a route gains a new navigation parameter.
     */
    private const NAVIGATION_PARAMS = ['id', 'start', 'tag', 'searchTerms'];

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
        $this->renderLayout($metadata, $viewModel, $path, $cacheParams);

        // Do not cache restricted content.
        if ($viewModel->doNotCache() === false) {
            $cache->save($cacheParams, \ob_get_contents());
        }

        $database->close();
        return \ob_end_flush();
    }

    /**
     * Check if user is authorised to access this route (bitwise tests).
     *
     * Redirect to login screen on failure. Site admin has access to all routes.
     *
     * @param   \Tfish\Route $route
     */
    private function checkAccessRights(Route $route): void
    {
        $routeMask = (int) $route->loginRequired();
        if ($routeMask === 0) return; // Public

        // Hard-stop if route mask contains invalid bits.
        if (($routeMask & ~$this->groupsMask()) !== 0) {
            throw new \RuntimeException(TFISH_ERROR_INVALID_GROUP);
        }

        $userMask = (int) $this->session->verifyPrivileges();

        // SUPER always has access, else any overlap with allowed route groups.
        if (($userMask & self::G_SUPER) !== 0 || $this->hasAnyGroup($userMask, $routeMask)) {
            return;
        }

        // Restricted route, unauthenticated users must log in, renders 303.
        if ($userMask === 0) {
            $this->session->setNextUrl($_SERVER['REQUEST_URI'] ?? '/');
            \header('Location: ' . TFISH_URL . 'login/', true, 303);
            exit;
        }

        // Restricted route, authenticated user but unauthorised for this route, renders 403.
        \header('Location: ' . TFISH_URL . 'restricted/', true, 303);
        exit;
    }

    /**
     * Check if site is closed and redirect non-admins to login.
     *
     * @param   \Tfish\Entity\Preference $preference Tfish preference object.
     */
    private function checkSiteClosed(Entity\Preference $preference, string $path)
    {
        if ($preference->closeSite() && !$this->session->isAdmin() && $path !== '/login/') {
            \header('Location: ' . TFISH_URL . 'login/', true, 303);
            exit;
        }
    }

    /**
     * Renders the layout (main template) of a theme.
     *
     * @param \Tfish\Entity\Metadata $metadata Instance of the Tuskfish metadata class.
     * @param mixed $viewModel Instance of a viewModel class.
     * @param string $path URL path (route) associated with this request.
     * @param array $cacheParams Controller's parsed view state, used to decide block suppression.
     */
    private function renderLayout(Entity\Metadata $metadata, $viewModel, string $path, array $cacheParams = [])
    {
        $page = $this->view->render();

        $metadata->update($viewModel->metadata());
        $session = $this->session;

        $theme = $this->trimString($viewModel->theme() ?? 'default');
        $layout = $this->trimString($viewModel->layout() ?? 'layout');

        if ($this->hasTraversalorNullByte($theme) || $this->hasTraversalorNullByte($layout)) {
            throw new \InvalidArgumentException(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE);
        }

        // Resolve the theme before rendering blocks so each block can prefer a theme-supplied
        // template (themes/{theme}/blocks/) over its module's bundled default.
        $blocks = $this->renderBlocks($path, $theme, $cacheParams);

        include_once TFISH_THEMES_PATH . $theme . "/" . $layout . ".html";
    }

    /**
     * Renders the blocks for insertion into the layout (main template) of a theme.
     *
     * Blocks are loaded based on the URL path (route) associated with this request.
     * Blocks are sorted by ID. Display in layout.html via echo, eg: <?php echo $block[42]; ?>
     * Blocks are also available by position, sorted by weight. A position may be accessed using
     * its name as key, eg. $blocks['position']['top-left'] is an array containing the blocks for
     * that position.
     *
     * @param string $path URL path.
     * @param string $theme Active theme, passed to each block so it can prefer a theme-supplied
     *               template over its module's bundled default.
     * @param array $cacheParams Controller's parsed view state for this request.
     * @return array Blocked indexed by ID.
     */
    private function renderBlocks(string $path, string $theme = '', array $cacheParams = []): array
    {
        // Blocks belong to a route's canonical (bare) view. When the visitor has moved to a deeper
        // view (a single item, pagination, a tag/type filter, a search) the blocks are dropped. We
        // read this from $cacheParams, the controller's own parsed view state, rather than from raw
        // $_GET: the controller records a navigation key only when its value is meaningful, so empty
        // or zero params (e.g. /?id=) and tracking tags (utm_*, gclid, etc.) correctly leave blocks
        // in place. NAVIGATION_PARAMS filters out framework baseline keys such as 'page'/'loggedIn'.
        if (\array_intersect_key($cacheParams, \array_flip(self::NAVIGATION_PARAMS))) {
            return ['position' => []];
        }

        $sql = "SELECT `block`.`id`, `type`, `position`, `title`, `html`, `config`, `weight`,
         `template`, `onlineStatus`
          FROM `block`
          INNER JOIN `blockRoute` ON `block`.`id` = `blockRoute`.`blockId`
          WHERE `blockRoute`.`route` = :path AND `onlineStatus` = '1'
          ORDER BY `position`, `weight`, `block`.`id`
        ";

        $statement = $this->database->preparedStatement($sql);
        $statement->bindValue(':path', $path, \PDO::PARAM_STR);
        $statement->execute();

        $blocks = ['position' => []];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $className = $row['type'];

            if (!\class_exists($className) || !\is_a($className, \Tfish\Interface\Block::class, true)) {
                continue;
            }

            $obj = new $className($row, $this->database, $this->criteriaFactory, $theme);

            $blocks[$row['id']] = $obj;

            // Group.
            $pos = $row['position'];
            $blocks['position'][$pos] ??= [];
            $blocks['position'][$pos][] = $obj;
        }

        return $blocks;
    }
}
