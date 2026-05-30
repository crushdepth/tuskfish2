<?php

declare(strict_types=1);

namespace Tfish\FishStat\Controller;

/**
 * \Tfish\FishStat\Controller\Species class file.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     FishStat
 */

/**
 * Controller for the aquaculture species and environment profile page (/species/).
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     FishStat
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 */
class Species
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
     * Render the species profile page.
     *
     * @return  array Cache parameters.
     */
    public function display(): array
    {
        $country = $this->trimString($_GET['country'] ?? '');
        $year = (int) ($_GET['year'] ?? 0);

        // Parameterised (dashboard) views are rendered fresh: country names are not safe cache-key
        // values, so caching them risks collisions. The bare page stays cached.
        $cacheParams = ($country !== '' || $year !== 0) ? [] : ['page' => 'species'];

        if (!empty($_SESSION['id']) && !empty($cacheParams)) {
            $cacheParams['loggedIn'] = '1';
        }

        $this->model->loadCountryList();
        $this->viewModel->displaySpecies($country, $year);

        return $cacheParams;
    }

    /**
     * Return the combined species/environment payload as JSON (AJAX endpoint).
     *
     * @return  array Unused; the method emits JSON and exits.
     */
    public function speciesdata(): array
    {
        \header('Content-Type: application/json; charset=utf-8');

        try {
            $country = $this->trimString($_GET['country'] ?? '');
            $year = (int) ($_GET['year'] ?? 0);
            $this->model->loadSpeciesData($country, $year);
            echo \json_encode($this->model->speciesData(), JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->logError((int) $e->getCode(), $e->getMessage(), $e->getFile(), (int) $e->getLine());
            \http_response_code(500);
            echo \json_encode(['error' => 'Failed to load species data']);
        }

        exit;
    }

    /**
     * Return the list of countries that report aquaculture production as JSON (AJAX endpoint).
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
}
