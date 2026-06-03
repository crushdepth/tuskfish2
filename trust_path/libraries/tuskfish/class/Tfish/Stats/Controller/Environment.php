<?php

declare(strict_types=1);

namespace Tfish\Stats\Controller;

/**
 * \Tfish\Stats\Controller\Environment class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

/**
 * Controller for the aquaculture production by environment page (/environment/).
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 */
class Environment
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
     * Render the environment page.
     *
     * @return  array Cache parameters.
     */
    public function display(): array
    {
        $country = $this->trimString($_GET['country'] ?? '');

        // Parameterised (dashboard) views are rendered fresh: country names are not safe cache-key
        // values, so caching them risks collisions. The bare page stays cached.
        $cacheParams = $country !== '' ? [] : ['page' => 'environment'];

        if (!empty($_SESSION['id']) && !empty($cacheParams)) {
            $cacheParams['loggedIn'] = '1';
        }

        $this->model->loadCountryList();
        $this->viewModel->displayEnvironment($country);

        return $cacheParams;
    }

    /**
     * Return the production-by-environment payload as JSON (AJAX endpoint).
     *
     * @return  array Unused; the method emits JSON and exits.
     */
    public function environmentdata(): array
    {
        \header('Content-Type: application/json; charset=utf-8');

        try {
            $country = $this->trimString($_GET['country'] ?? '');
            $this->model->loadEnvironmentData($country);
            echo \json_encode($this->model->environmentData(), JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->logError((int) $e->getCode(), $e->getMessage(), $e->getFile(), (int) $e->getLine());
            \http_response_code(500);
            echo \json_encode(['error' => 'Failed to load environment data']);
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
