<?php

declare(strict_types=1);

namespace Tfish\FishStat\Controller;

/**
 * \Tfish\FishStat\Controller\Producers class file.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     FishStat
 */

/**
 * Controller for the aquaculture producers page (/producers/).
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     FishStat
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 */
class Producers
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
     * Render the producers page.
     *
     * @return  array Cache parameters.
     */
    public function display(): array
    {
        $species = $this->trimString($_GET['species'] ?? '');
        $year = (int) ($_GET['year'] ?? 0);
        $country = $this->trimString($_GET['country'] ?? '');

        // Parameterised (dashboard/deep-link) views are rendered fresh: country names are not safe
        // cache-key values, so caching them risks collisions. The bare page stays cached.
        $cacheParams = ($species !== '' || $year !== 0 || $country !== '') ? [] : ['page' => 'producers'];

        if (!empty($_SESSION['id']) && !empty($cacheParams)) {
            $cacheParams['loggedIn'] = '1';
        }

        $this->model->loadSpeciesList();
        $this->viewModel->displayProducers($species, $year, $country);

        return $cacheParams;
    }

    /**
     * Return the producers payload as JSON (AJAX endpoint).
     *
     * @return  array Unused; the method emits JSON and exits.
     */
    public function producersdata(): array
    {
        \header('Content-Type: application/json; charset=utf-8');

        try {
            $species = $this->trimString($_GET['species'] ?? '');
            $year = (int) ($_GET['year'] ?? 0);
            $this->model->loadProducersData($species, $year);
            echo \json_encode($this->model->producersData(), JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->logError((int) $e->getCode(), $e->getMessage(), $e->getFile(), (int) $e->getLine());
            \http_response_code(500);
            echo \json_encode(['error' => 'Failed to load producers data']);
        }

        exit;
    }
}
