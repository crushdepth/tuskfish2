<?php

declare(strict_types=1);

namespace Tfish\Stats\Controller;

/**
 * \Tfish\Stats\Controller\Listing class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

/**
 * Controller for the Stats landing page (/) — the global overview dashboard.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 */
class Listing
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
     * Render the global overview page (and its member-state / species dashboard).
     *
     * @return  array Cache parameters.
     */
    public function display(): array
    {
        $country = $this->trimString($_GET['country'] ?? '');
        $species = $this->trimString($_GET['species'] ?? '');

        // Parameterised (dashboard) views are rendered fresh: country names are not safe cache-key
        // values, so caching them risks collisions. The bare page stays cached.
        $cacheParams = ($country !== '' || $species !== '') ? [] : ['page' => 'global'];

        if (!empty($_SESSION['id']) && !empty($cacheParams)) {
            $cacheParams['loggedIn'] = '1';
        }

        $this->model->loadCountryList();
        $this->viewModel->displayGlobal($country, $species);

        return $cacheParams;
    }

    /**
     * Return the list of countries as JSON (AJAX endpoint).
     *
     * @return  array Unused; the method emits JSON and exits.
     */
    public function countries(): array
    {
        \header('Content-Type: application/json; charset=utf-8');

        try {
            $this->model->loadCountryList();
            echo \json_encode($this->model->countries(), JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->logError((int) $e->getCode(), $e->getMessage(), $e->getFile(), (int) $e->getLine());
            \http_response_code(500);
            echo \json_encode(['error' => 'Failed to load country list']);
        }

        exit;
    }

    /**
     * Return the global/country production payload as JSON (AJAX endpoint).
     *
     * @return  array Unused; the method emits JSON and exits.
     */
    public function chartdata(): array
    {
        \header('Content-Type: application/json; charset=utf-8');

        try {
            $country = $this->trimString($_GET['country'] ?? '');
            $species = $this->trimString($_GET['species'] ?? '');
            $this->model->loadChartDataForCountry($country, $species);
            echo \json_encode($this->model->chartData(), JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->logError((int) $e->getCode(), $e->getMessage(), $e->getFile(), (int) $e->getLine());
            \http_response_code(500);
            echo \json_encode(['error' => 'Failed to load chart data']);
        }

        exit;
    }
}
