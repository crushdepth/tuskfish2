<?php

declare(strict_types=1);

namespace Tfish\Rangefinder\Controller;

/**
 * \Tfish\Rangefinder\Controller\Map class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

/**
 * Controller for the Rangefinder occurrence map (/map/).
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 */
class Map
{
    use \Tfish\Traits\ValidateString;

    private object $model;
    private object $viewModel;
    private \Tfish\Logger $logger;

    public function __construct(object $model, object $viewModel, \Tfish\Logger $logger)
    {
        $this->model = $model;
        $this->viewModel = $viewModel;
        $this->logger = $logger;
    }

    /**
     * Render the occurrence map page.
     *
     * The page itself is filter-agnostic: the whole marker payload ships once and all filtering
     * (species/lineage, presence toggle, country, physical holding) happens client-side against
     * it, with the active state carried in the URL fragment for deep-linking. So there is one
     * cacheable page regardless of the filters in play.
     *
     * @return  array Cache parameters.
     */
    public function display(): array
    {
        $cacheParams = ['page' => 'map'];

        if (!empty($_SESSION['id'])) {
            $cacheParams['loggedIn'] = '1';
        }

        $this->viewModel->displayMap();

        return $cacheParams;
    }
}
