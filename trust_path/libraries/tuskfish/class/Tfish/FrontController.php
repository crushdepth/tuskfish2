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
 * @var         object $view
 * @var         object $controller
 */

class FrontController
{
    use Traits\TraversalCheck;
    use Traits\ValidateString;

    private $view;
    private $controller;

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
        $this->session = $session;
        $session->start();
        $this->checkSiteClosed($preference, $path);
        $this->checkAdminOnly($route);

        // Create MVVM components with dice (as they have variable dependencies).
        $pagination = $dice->create('\\Tfish\\Pagination', [$path]);
        $model = $dice->create($route->model());
        $viewModel = $dice->create($route->viewModel(), [$model]);
        $this->view = $dice->create($route->view(), [$viewModel]);
        $this->controller = $dice->create($route->controller(),[$model, $viewModel]);

        $action = $this->trimString(($_REQUEST['action'] ?? 'display'));

        if (!$this->isAlpha($action) || !method_exists($this->controller, $action)) {
            \trigger_error(TFISH_ERROR_BAD_ACTION, E_USER_NOTICE);
            \header('Location: ' . TFISH_URL . 'error/');
            exit;
        }

        \ob_start();
        
        $cacheParams = $this->controller->{$action}();
        $cache->check($path, $cacheParams);
        
        $this->renderLayout($metadata, $viewModel);
        $cache->save($cacheParams, \ob_get_contents());
        $database->close();

        return \ob_end_flush();
    }

    /**
     * Check if the present route is restricted to admins.
     * 
     * @param   \Tfish\Route $route
     */
    private function checkAdminOnly(Route $route)
    {
        if ($route->loginRequired() && !$this->session->isAdmin()) {
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
     * @param string $viewModel Instance of a viewModel class.
     */
    private function renderLayout(Entity\Metadata $metadata, $viewModel)
    {
        $page = $this->view->render();
        $metadata->update($this->view->metadata());
        $session = $this->session;

        $theme = $this->trimString($viewModel->theme() ?? 'default');
        $layout = $this->trimString($viewModel->layout() ?? 'layout');

        if ($this->hasTraversalorNullByte($theme) || $this->hasTraversalorNullByte($layout)) {
            \trigger_error(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE, E_USER_ERROR);
            exit; // Hard stop due to high probability of abuse.
        }

        include_once TFISH_THEMES_PATH . $theme . "/" . $layout . ".html";
    }
}
