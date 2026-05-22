<?php

declare(strict_types=1);

namespace Tfish\Controller;

class Email
{
    private object $model;
    private object $viewModel;

    public function __construct(object $model, object $viewModel)
    {
        $this->model = $model;
        $this->viewModel = $viewModel;
    }

    public function cancel(): array
    {
        $this->viewModel->displayCancel();

        return [];
    }

    public function display(): array
    {
        $this->viewModel->displayEdit();

        return [];
    }

    public function update(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->viewModel->displayEdit();

            return [];
        }

        $this->viewModel->displayUpdate();

        return [];
    }

    public function testEmail(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->viewModel->displayEdit();

            return [];
        }

        $this->viewModel->displayTestEmail();

        return [];
    }
}
