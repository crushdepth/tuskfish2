<?php

declare(strict_types=1);

namespace Tfish\FishStat\Controller;

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

    public function display(): array
    {
        $cacheParams = ['page' => 'fishstat'];

        if (!empty($_SESSION['id'])) {
            $cacheParams['loggedIn'] = '1';
        }

        $this->model->loadCountryList();
        $this->viewModel->displayGlobal();

        return $cacheParams;
    }

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
