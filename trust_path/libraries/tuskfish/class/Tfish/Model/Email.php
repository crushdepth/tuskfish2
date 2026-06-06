<?php

declare(strict_types=1);

namespace Tfish\Model;

class Email
{
    private \Tfish\Database $database;
    private \Tfish\Entity\Preference $preference;

    public function __construct(
        \Tfish\Database $database,
        \Tfish\Entity\Preference $preference
        )
    {
        $this->database = $database;
        $this->preference = $preference;
    }

    public function update(): bool
    {
        if (!isset($_POST['preference']) || !\is_array($_POST['preference'])) {
            return false;
        }

        $input = $_POST['preference'];

        if (!empty($input['smtpPassword'])) {
            // New password entered — use it regardless of clear checkbox.
        } elseif (!empty($input['clearSmtpPassword'])) {
            $input['smtpPassword'] = '';
        } else {
            $input['smtpPassword'] = $this->preference->smtpPassword();
        }

        $this->preference->setSmtpHost((string) ($input['smtpHost'] ?? ''));
        $this->preference->setSmtpPort((int) ($input['smtpPort'] ?? 587));
        $this->preference->setSmtpEncryption((string) ($input['smtpEncryption'] ?? 'tls'));
        $this->preference->setSmtpUser((string) ($input['smtpUser'] ?? ''));
        $this->preference->setSmtpPassword((string) ($input['smtpPassword'] ?? ''));

        return $this->writeSmtpPreferences();
    }

    private function writeSmtpPreferences(): bool
    {
        $smtpKeys = [
            'smtpHost' => (string) $this->preference->smtpHost(),
            'smtpPort' => (string) $this->preference->smtpPort(),
            'smtpEncryption' => (string) $this->preference->smtpEncryption(),
            'smtpUser' => (string) $this->preference->smtpUser(),
            'smtpPassword' => $this->preference->smtpPasswordForStorage(),
        ];

        $existingKeys = $this->existingPreferenceKeys();

        foreach ($smtpKeys as $key => $value) {
            if (\in_array($key, $existingKeys, true)) {
                $sql = "UPDATE `preference` SET `value` = :value WHERE `title` = :title";
            } else {
                $sql = "INSERT INTO `preference` (`title`, `value`) VALUES (:title, :value)";
            }

            $statement = $this->database->preparedStatement($sql);
            $statement->bindValue(':title', $key, $this->database->setType($key));
            $statement->bindValue(':value', $value, $this->database->setType($value));

            $result = $this->database->executeTransaction($statement);

            if (!$result) {
                throw new \RuntimeException(TFISH_ERROR_INSERTION_FAILED);
            }

            unset($sql, $key, $value, $statement);
        }

        return true;
    }

    private function existingPreferenceKeys(): array
    {
        $sql = "SELECT `title` FROM `preference`";
        $statement = $this->database->preparedStatement($sql);
        $statement->execute();

        $keys = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $keys[] = $row['title'];
        }

        return $keys;
    }

    public function testEmail(): string
    {
        $mail = new \Tfish\Mail($this->preference);

        try {
            $result = $mail->send(
                $this->preference->siteEmail(),
                'Tuskfish test email',
                'This is a test email from your Tuskfish installation.'
            );

            return $result ? '' : 'Unknown error.';
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
