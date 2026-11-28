<?php

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends email with PHPMailer, from do_not_reply@richmondsunlight.com: through SMTP_HOST if it's set,
 * and otherwise with PHP's mail(). In production, SMTP_HOST is SES, which it logs in to (with
 * AWS_SES_SMTP_USERNAME and AWS_SES_SMTP_PASSWORD) over STARTTLS. In Docker, it's Mailpit, with
 * no login and no TLS.
 */
class PhpMailerMailer implements Mailer
{
    /**
     * Why the last message couldn't be sent, if it couldn't.
     */
    public string $error = '';

    public function __construct()
    {
        // PHPMailer itself, not Composer's autoloader, which would include functions.inc.php a
        // second time. (rs-machine's autoloader has already loaded it.)
        if (!class_exists(PHPMailer::class)) {
            foreach (['Exception', 'PHPMailer', 'SMTP'] as $file) {
                require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/' . $file . '.php';
            }
        }
    }

    public function send(string $to, string $to_name, string $subject, string $text, string $html, array $headers = []): bool
    {
        $mail = new PHPMailer(true);
        try {
            if (defined('SMTP_HOST') && SMTP_HOST !== '') {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->Port = defined('SMTP_PORT') ? (int) SMTP_PORT : 25;
                $username = defined('AWS_SES_SMTP_USERNAME') ? (string) AWS_SES_SMTP_USERNAME : '';
                if ($username !== '') {
                    $mail->SMTPAuth = true;
                    $mail->Username = $username;
                    $mail->Password = defined('AWS_SES_SMTP_PASSWORD') ? (string) AWS_SES_SMTP_PASSWORD : '';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $mail->SMTPAutoTLS = false;
                }
            }
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom('do_not_reply@richmondsunlight.com', 'Richmond Sunlight');
            $mail->addAddress($to, $to_name);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text;
            foreach ($headers as $name => $value) {
                $mail->addCustomHeader($name, $value);
            }
            $this->error = '';
            return $mail->send();
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }
}
