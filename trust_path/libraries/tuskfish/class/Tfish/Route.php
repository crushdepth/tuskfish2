<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\Route class file.
 * 
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Holds classnames of components required to meet the present routing request (model-view-viewModel-controller).
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         string $model Class name of the model.
 * @var         string $viewModel Class name of the viewModel.
 * @var         string $view Class name of the view.
 * @var         string $controller Class name of the controller.
 * @var         bool $login True if this route requires admin privileges, otherwise false.
 */

class Route
{
    use Traits\ValidateString;

    private $model;
    private $viewModel;
    private $view;
    private $controller;
    private $login;

    /**
     * Constructor
     * 
     * @param   string $model Classname of the model required for this route.
     * @param   string $viewModel Classname of the viewModel required for this route.
     * @param   string $view Classname of the view required for this route.
     * @param   string $controller Classname of the controller required for this route.
     * @param   bool $login Whether this route is restricted to admins (true) or not (false).
     */
    public function __construct(
        string $model,
        string $viewModel,
        string $view,
        string $controller,
        bool $login
        )
    {
        $this->model = $this->trimString($model);
        $this->viewModel = $this->trimString($viewModel);
        $this->view = $this->trimString($view);
        $this->controller = $this->trimString($controller);
        $this->login = $login;
    }

    /**
     * Controller class name.
     * 
     * @return  string Classname of the controller required for this route.
     */
    public function controller(): string
    {
        return $this->controller;
    }

    /**
     * Restricted route?
     * 
     * @return  bool Whether this route is restricted to admins (true) or not (false).
     */
    public function loginRequired(): bool
    {
        return $this->login;
    }

    /**
     * Model class name.
     * 
     * @return  string Classname of the model required for this route.
     */
    public function model(): string
    {
        return $this->model;
    }

    /**
     * ViewModel class name required for this route.
     * 
     * @return  string Classname of the viewModel required for this route.
     */
    public function viewModel(): string
    {
        return $this->viewModel;
    }

    /**
     * View class name.
     * 
     * @return  string Classname of the view required for this route.
     */
    public function view(): string
    {
        return $this->view;
    }
}
