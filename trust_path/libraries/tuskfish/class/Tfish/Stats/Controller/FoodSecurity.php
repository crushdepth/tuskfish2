<?php

declare(strict_types=1);

namespace Tfish\Stats\Controller;

/**
 * \Tfish\Stats\Controller\FoodSecurity class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

/**
 * Controller for the food security page (/insecurity/).
 *
 * Mirrors the Consumption controller: the country/species/year parameters do not filter this map;
 * they are carried only so the persisted dashboard selection survives a visit here. A request
 * carrying any of them is rendered fresh (not cached) so the embedded carry-state seed is never
 * served stale, matching the other parameterised Stats pages.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 */
class FoodSecurity
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
     * Render the food security page.
     *
     * @return  array Cache parameters.
     */
    public function display(): array
    {
        $country = $this->trimString($_GET['country'] ?? '');
        $species = $this->trimString($_GET['species'] ?? '');
        $year = $this->trimString($_GET['year'] ?? '');

        $cacheParams = ($country !== '' || $species !== '' || $year !== '') ? [] : ['page' => 'insecurity'];

        if (!empty($_SESSION['id']) && !empty($cacheParams)) {
            $cacheParams['loggedIn'] = '1';
        }

        $this->model->loadCountryList();
        $this->viewModel->displayFoodSecurity($country, $species, $year);

        return $cacheParams;
    }

    /**
     * Return the food security payload as JSON (AJAX endpoint; reserved for the dynamic version).
     *
     * @return  array Unused; the method emits JSON and exits.
     */
    public function foodsecuritydata(): array
    {
        \header('Content-Type: application/json; charset=utf-8');

        try {
            $country = $this->trimString($_GET['country'] ?? '');
            $this->model->loadFoodSecurityData($country);
            echo \json_encode($this->model->foodSecurityData(), JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->logError((int) $e->getCode(), $e->getMessage(), $e->getFile(), (int) $e->getLine());
            \http_response_code(500);
            echo \json_encode(['error' => 'Failed to load food security data']);
        }

        exit;
    }

    /**
     * Return the list of countries as JSON (AJAX endpoint; reserved for the dynamic version).
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
