<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\Router class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Generates route objects containing MVVM component class names relevant to incoming requests.
 *
 * A static routing table is used to return route objects configured to instantiate the relevant
 * view, model, viewModel and controller components for this page.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @var         array $table Static routing table listing the MVVMC components required to generate routes.
 */

class Router
{
    private array $table = [];

    /**
     * Constructor
     *
     * @param   array $routingTable Array of routes and components required to initialise them.
     */
    public function __construct(array $routingTable)
    {
        $this->table = $routingTable;
    }

    /**
     * Returns a route object containing the classnames of the MVVMC components required for this route.
     *
     * @param   string $path The path component of the URL associated with this request.
     * @return  \Tfish\Route Route object.
     */
    public function route(string $path): Route
    {
        return isset($this->table[$path]) ? $this->table[$path] : $this->table['/error/'];
    }
}
