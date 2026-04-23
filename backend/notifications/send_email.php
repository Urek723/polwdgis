<?php
// backend/notifications/send_email.php
// PHPMailer Gmail SMTP email notification sender (SAFE VERSION)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ── Load PHPMailer (Composer or Manual) ───────────────────────

$composerAutoload = __DIR__ . '/../../vendor/autoload.php';

if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    $base = __DIR__ . '/../../vendor/phpmailer/phpmailer/src/';
    
    if (file_exists($base . 'PHPMailer.php')) {
        require_once $base . 'PHPMailer.php';
        require_once $base . 'SMTP.php';
        require_once $base . 'Exception.php';
    } else {
        error_log('[EmailNotify] PHPMailer not found.');

        // SAFE fallback (no crash)
        if (!function_exists('sendEmailNotification')) {
            function sendEmailNotification(
                string $toEmail,
                string $toName,
                string $subject,
                string $htmlBody,
                string $plainText = ''
            ): bool {
                error_log("[EmailNotify] PHPMailer missing — email skipped: $toEmail | $subject");
                return false;
            }
        }

        return;
    }
}

// ── Gmail SMTP Configuration ──────────────────────────────────
define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);
define('MAIL_USERNAME',   'urekmazino723@gmail.com'); // change if needed
define('MAIL_PASSWORD',   'uxhz qnwk wdnk gpaz');      // app password
define('MAIL_FROM_EMAIL', 'urekmazino723@gmail.com');
define('MAIL_FROM_NAME',  'Polomolok Water District');
define('MAIL_REPLY_TO',   'urekmazino723@gmail.com');

// ── MAIN EMAIL FUNCTION (SAFE) ────────────────────────────────
if (!function_exists('sendEmailNotification')) {
    function sendEmailNotification(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $plainText = ''
    ): bool {

        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("[EmailNotify] Invalid email: $toEmail");
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
            $mail->CharSet    = 'UTF-8';
            $mail->SMTPDebug  = SMTP::DEBUG_OFF;

            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $plainText ?: strip_tags($htmlBody);

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("[EmailNotify] Mail error: {$mail->ErrorInfo}");
            return false;
        }
    }
}

// ── TEMPLATE BUILDER ─────────────────────────────────────────
if (!function_exists('buildEmailTemplate')) {
    function buildEmailTemplate(
        string $title,
        string $body,
        string $cta = '',
        string $ctaUrl = ''
    ): string {

        $ctaBlock = '';
        if ($cta && $ctaUrl) {
            $ctaBlock = "
            <div style='text-align:center;margin:20px'>
                <a href='{$ctaUrl}' style='background:#0057ff;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none'>
                    {$cta}
                </a>
            </div>";
        }

        return "
        <html>
        <body style='font-family:Arial;background:#f4f6f9;padding:20px'>
            <div style='max-width:600px;margin:auto;background:#fff;padding:20px;border-radius:10px'>
                <h2 style='color:#0057ff'>{$title}</h2>
                {$body}
                {$ctaBlock}
                <hr>
                <small>Polomolok Water District</small>
            </div>
        </body>
        </html>";
    }
}

// ── REQUEST SUBMITTED EMAIL ──────────────────────────────────
if (!function_exists('sendRequestSubmittedEmail')) {
    function sendRequestSubmittedEmail(
        string $toEmail,
        string $consumerName,
        int $requestId,
        string $requestType,
        string $subject,
        string $details
    ): bool {

        $body = "
        <p>Hello <b>{$consumerName}</b>,</p>
        <p>Your request has been submitted successfully.</p>
        <p><b>Reference:</b> #{$requestId}</p>
        <p><b>Type:</b> {$requestType}</p>
        <p><b>Details:</b><br>" . nl2br(htmlspecialchars($details)) . "</p>";

        $html = buildEmailTemplate("Request Submitted", $body);

        return sendEmailNotification($toEmail, $consumerName, "Request Submitted", $html);
    }
}