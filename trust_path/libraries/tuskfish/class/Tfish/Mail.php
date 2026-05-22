<?php

declare(strict_types=1);

namespace Tfish;

use PHPMailer\PHPMailer\PHPMailer;

class Mail
{
    use \Tfish\Traits\EmailCheck;

    private Entity\Preference $preference;

    public function __construct(Entity\Preference $preference)
    {
        $this->preference = $preference;

        require_once TFISH_LIBRARIES_PATH . 'phpmailer/Exception.php';
        require_once TFISH_LIBRARIES_PATH . 'phpmailer/PHPMailer.php';
        require_once TFISH_LIBRARIES_PATH . 'phpmailer/SMTP.php';
    }

    public function send(string $to, string $subject, string $body): bool
    {
        if (!$this->isEmail($to)) {
            throw new \InvalidArgumentException(TFISH_ERROR_NOT_EMAIL);
        }

        $pref = $this->preference;

        if (empty($pref->smtpHost())) {
            throw new \RuntimeException('SMTP host is not configured.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $pref->smtpHost();
        $mail->Port = $pref->smtpPort();

        switch ($pref->smtpEncryption()) {
            case 'tls':
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                break;
            case 'ssl':
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                break;
            case 'none':
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
                break;
        }

        if (!empty($pref->smtpUser())) {
            $mail->SMTPAuth = true;
            $mail->Username = $pref->smtpUser();
            $mail->Password = $pref->smtpPassword();
        }

        $mail->setFrom($pref->siteEmail(), $pref->siteName());
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        $mail->CharSet = 'UTF-8';

        return $mail->send();
    }
}
