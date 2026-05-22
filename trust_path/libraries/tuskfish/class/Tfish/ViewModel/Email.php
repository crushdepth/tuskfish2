<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

class Email implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;
    use \Tfish\Traits\Viewable;

    private object $model;
    private \Tfish\Entity\Preference $preference;
    private string $response = '';
    private string $backUrl = '';

    public function __construct(object $model, \Tfish\Entity\Preference $preference)
    {
        $this->pageTitle = TFISH_PREFERENCE_MAIL_SETTINGS;
        $this->model = $model;
        $this->theme = 'admin';
        $this->preference = $preference;
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    public function displayCancel(): void
    {
        \header('Location: ' . TFISH_EMAIL_URL);
        exit;
    }

    public function displayEdit(): void
    {
        $this->template = 'emailEdit';
    }

    public function displayUpdate(): void
    {
        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        if ($this->model->update()) {
            $this->response = TFISH_PREFERENCES_WERE_UPDATED;
            $this->backUrl = TFISH_EMAIL_URL;
            $this->template = 'response';
        } else {
            $this->response = TFISH_PREFERENCES_UPDATE_FAILED;
            $this->backUrl = TFISH_EMAIL_URL;
            $this->template = 'response';
        }
    }

    public function displayTestEmail(): void
    {
        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        $error = $this->model->testEmail();

        if ($error === '') {
            $this->response = TFISH_PREFERENCE_TEST_EMAIL_SENT;
        } else {
            $this->response = TFISH_PREFERENCE_TEST_EMAIL_FAILED
                . $this->trimString($error);
        }

        $this->backUrl = TFISH_EMAIL_URL;
        $this->template = 'response';
    }

    public function backUrl(): string
    {
        return $this->backUrl;
    }

    public function preference(): \Tfish\Entity\Preference
    {
        return $this->preference;
    }

    public function response(): string
    {
        return $this->response;
    }
}
