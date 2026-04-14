<?php
// backend/notifications/send_email.php
// PHPMailer Gmail SMTP email notification sender

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// PHPMailer autoload — supports both Composer and manual install
$phpmailerPaths = [
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php',
];

$composerAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    // Manual include fallback
    $base = __DIR__ . '/../../vendor/phpmailer/phpmailer/src/';
    if (file_exists($base . 'PHPMailer.php')) {
        require_once $base . 'PHPMailer.php';
        require_once $base . 'SMTP.php';
        require_once $base . 'Exception.php';
    } else {
        // PHPMailer not installed — log and return false gracefully
        error_log('[EmailNotify] PHPMailer not found. Run: composer require phpmailer/phpmailer');
        function sendEmailNotification(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
            error_log("[EmailNotify] PHPMailer missing — skipped email to $toEmail | Subject: $subject");
            return false;
        }
        return;
    }
}

// ── Gmail SMTP Configuration ──────────────────────────────────
define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);
define('MAIL_USERNAME',   'urekmazino723@gmail.com');   // ← Replace with actual Gmail
define('MAIL_PASSWORD',   'uxhz qnwk wdnk gpaz');        // ← Gmail App Password (not account password)
define('MAIL_FROM_EMAIL', 'urekmazino723@gmail.com');
define('MAIL_FROM_NAME',  'Polomolok Water District');
define('MAIL_REPLY_TO',   'urekmazino723@gmail.com');

/**
 * Send an HTML email via Gmail SMTP.
 *
 * @param string $toEmail   Recipient email address
 * @param string $toName    Recipient display name
 * @param string $subject   Email subject
 * @param string $htmlBody  Full HTML body
 * @param string $plainText Optional plain-text fallback
 * @return bool             True on success, false on failure
 */
function sendEmailNotification(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $plainText = ''
): bool {
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log("[EmailNotify] Invalid or missing email address: '$toEmail'");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        // Suppress debug output in production
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;

        // Sender & recipient
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainText ?: strip_tags($htmlBody);

        $mail->send();
        error_log("[EmailNotify] Email sent to $toEmail | Subject: $subject");
        return true;

    } catch (Exception $e) {
        error_log("[EmailNotify] Mailer Error to $toEmail: {$mail->ErrorInfo}");
        return false;
    }
}

// ── Email Template Builder ────────────────────────────────────

/**
 * Build a branded HTML email template.
 *
 * @param string $title    Heading inside the email
 * @param string $body     Inner HTML content (paragraphs, tables, etc.)
 * @param string $cta      Optional call-to-action button label
 * @param string $ctaUrl   Optional CTA button URL
 * @return string          Complete HTML email string
 */
function buildEmailTemplate(
    string $title,
    string $body,
    string $cta    = '',
    string $ctaUrl = ''
): string {
    $ctaBlock = '';
    if ($cta && $ctaUrl) {
        $ctaBlock = "
        <div style='text-align:center;margin:28px 0 8px'>
            <a href='" . htmlspecialchars($ctaUrl, ENT_QUOTES) . "'
               style='background:#0057ff;color:#fff;padding:12px 28px;border-radius:8px;
                      text-decoration:none;font-weight:600;font-size:14px;display:inline-block'>
                " . htmlspecialchars($cta) . "
            </a>
        </div>";
    }

    return "<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1.0'>
  <title>" . htmlspecialchars($title) . "</title>
</head>
<body style='margin:0;padding:0;background:#f0f4f8;font-family:Arial,sans-serif'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#f0f4f8;padding:32px 16px'>
    <tr><td align='center'>
      <table width='600' cellpadding='0' cellspacing='0'
             style='background:#ffffff;border-radius:12px;overflow:hidden;
                    box-shadow:0 4px 24px rgba(0,0,0,0.08);max-width:600px;width:100%'>

        <!-- Header -->
        <tr>
          <td style='background:linear-gradient(135deg,#0057ff,#00d4ff);padding:28px 32px;text-align:center'>
            <div style='font-size:28px;margin-bottom:8px'>💧</div>
            <div style='color:#fff;font-size:20px;font-weight:700;letter-spacing:-0.01em'>
              Polomolok Water District
            </div>
            <div style='color:rgba(255,255,255,0.75);font-size:12px;margin-top:4px;letter-spacing:0.05em'>
              Consumer Notification System
            </div>
          </td>
        </tr>

        <!-- Title bar -->
        <tr>
          <td style='background:#f8faff;border-bottom:2px solid #e8effe;
                     padding:16px 32px;text-align:center'>
            <div style='color:#0057ff;font-size:16px;font-weight:700;
                        text-transform:uppercase;letter-spacing:0.06em'>
              " . htmlspecialchars($title) . "
            </div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style='padding:28px 32px;color:#333;font-size:14px;line-height:1.6'>
            $body
            $ctaBlock
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style='background:#f8faff;border-top:1px solid #e8effe;
                     padding:16px 32px;text-align:center'>
            <p style='margin:0;color:#888;font-size:11px;line-height:1.6'>
              Polomolok Water District &nbsp;|&nbsp; Municipal Compound, Polomolok, South Cotabato<br>
              Tel: (083) 123-4567 &nbsp;|&nbsp; Email: support@polomolok.gov.ph<br>
              <span style='color:#bbb'>This is an automated notification. Please do not reply to this email.</span>
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>";
}

// ── Specific notification senders ─────────────────────────────

/**
 * Notify consumer when their request is submitted.
 */
function sendRequestSubmittedEmail(
    string $toEmail,
    string $consumerName,
    int    $requestId,
    string $requestType,
    string $subject,
    string $details,
    string $portalUrl = ''
): bool {
    $emailSubject = "Request #{$requestId} Submitted — {$requestType}";

    $body = "
        <p>Dear <strong>" . htmlspecialchars($consumerName) . "</strong>,</p>
        <p>Your service request has been received and is now under review by our team.</p>
        <table style='width:100%;border-collapse:collapse;margin:16px 0'>
          <tr style='background:#f0f4f8'>
            <td style='padding:10px 14px;font-weight:700;color:#555;width:160px;
                       border:1px solid #e0e8f4'>Reference No.</td>
            <td style='padding:10px 14px;border:1px solid #e0e8f4;font-weight:700;
                       color:#0057ff'>#" . str_pad($requestId, 5, '0', STR_PAD_LEFT) . "</td>
          </tr>
          <tr>
            <td style='padding:10px 14px;font-weight:700;color:#555;border:1px solid #e0e8f4'>Request Type</td>
            <td style='padding:10px 14px;border:1px solid #e0e8f4'>" . htmlspecialchars($requestType) . "</td>
          </tr>
          <tr style='background:#f0f4f8'>
            <td style='padding:10px 14px;font-weight:700;color:#555;border:1px solid #e0e8f4'>Subject</td>
            <td style='padding:10px 14px;border:1px solid #e0e8f4'>" . htmlspecialchars($subject) . "</td>
          </tr>
          <tr>
            <td style='padding:10px 14px;font-weight:700;color:#555;border:1px solid #e0e8f4'>Status</td>
            <td style='padding:10px 14px;border:1px solid #e0e8f4'>
              <span style='background:#e8effe;color:#0057ff;padding:3px 10px;
                           border-radius:20px;font-size:12px;font-weight:700'>Submitted</span>
            </td>
          </tr>
          " . ($details ? "
          <tr style='background:#f0f4f8'>
            <td style='padding:10px 14px;font-weight:700;color:#555;border:1px solid #e0e8f4'>Details</td>
            <td style='padding:10px 14px;border:1px solid #e0e8f4'>" . nl2br(htmlspecialchars($details)) . "</td>
          </tr>" : "") . "
        </table>
        <p style='color:#555;font-size:13px'>
          We will notify you as soon as there is an update on your request.
          For urgent concerns, please call <strong>(083) 123-4567</strong>.
        </p>";

    $html = buildEmailTemplate('Request Submitted', $body, 'Track My Request', $portalUrl);
    return sendEmailNotification($toEmail, $consumerName, $emailSubject, $html);
}

/**
 * Notify consumer when request status changes.
 */
function sendRequestStatusEmail(
    string $toEmail,
    string $consumerName,
    int    $requestId,
    string $requestType,
    string $newStatus,
    string $resolutionNotes = '',
    string $portalUrl       = ''
): bool {
    $statusColors = [
        'Submitted'    => ['bg' => '#e8effe', 'color' => '#0057ff'],
        'Under Review' => ['bg' => '#fff8e8', 'color' => '#ca8a04'],
        'In Progress'  => ['bg' => '#e8f4fe', 'color' => '#0ea5e9'],
        'Resolved'     => ['bg' => '#e8fef4', 'color' => '#16a34a'],
        'Closed'       => ['bg' => '#f0f0f0', 'color' => '#71717a'],
    ];
    $sc = $statusColors[$newStatus] ?? ['bg' => '#f0f0f0', 'color' => '#555'];

    $emailSubject = "Request #{$requestId} Update — {$newStatus}";

    $body = "
        <p>Dear <strong>" . htmlspecialchars($consumerName) . "</strong>,</p>
        <p>There is an update on your service request. Please see the details below:</p>
        <table style='width:100%;border-collapse:collapse;margin:16px 0'>
          <tr style='background:#f0f4f8'>
            <td style='padding:10px 14px;font-weight:700;color:#555;width:160px;border:1px solid #e0e8f4'>Reference No.</td>
            <td style='padding:10px 14px;border:1px solid #e0e8f4;font-weight:700;color:#0057ff'>
              #" . str_pad($requestId, 5, '0', STR_PAD_LEFT) . "
            </td>
          </tr>
          <tr>
            <td style='padding:10px 14px;font-weight:700;color:#555;border:1px solid #e0e8f4'>Request Type</td>
            <td style='padding:10px 14px;border:1px solid #e0e8f4'>" . htmlspecialchars($requestType) . "</td>
          </tr>
          <tr style='background:#f0f4f8'>
            <td style='padding:10px 14px;font-weight:700;color:#555;border:1px solid #e0e8f4'>New Status</td>
            <td style='padding:10px 14px;border:1px solid #e0e8f4'>
              <span style='background:{$sc['bg']};color:{$sc['color']};padding:3px 10px;
                           border-radius:20px;font-size:12px;font-weight:700'>{$newStatus}</span>
            </td>
          </tr>
          " . ($resolutionNotes ? "
          <tr>
            <td style='padding:10px 14px;font-weight:700;color:#555;border:1px solid #e0e8f4'>Resolution Notes</td>
            <td style='padding:10px 14px;border:1px solid #e0e8f4'>" . nl2br(htmlspecialchars($resolutionNotes)) . "</td>
          </tr>" : "") . "
        </table>
        <p style='color:#555;font-size:13px'>
          " . ($newStatus === 'Resolved'
                ? "Your request has been resolved. If you have further concerns, please don't hesitate to contact us."
                : "We are working on your request and will keep you informed of any further updates."
             ) . "
        </p>";

    $html = buildEmailTemplate('Request Status Update', $body, 'View My Request', $portalUrl);
    return sendEmailNotification($toEmail, $consumerName, $emailSubject, $html);
}

/**
 * Notify consumer of a water interruption affecting their area.
 */
function sendInterruptionEmail(
    string $toEmail,
    string $consumerName,
    string $title,
    string $description,
    string $affectedBarangays,
    string $startDatetime,
    string $endDatetime = ''
): bool {
    $emailSubject = "Water Service Advisory: {$title}";

    $body = "
        <p>Dear <strong>" . htmlspecialchars($consumerName) . "</strong>,</p>
        <p>Please be advised of a scheduled water service interruption that may affect your area.</p>
        <table style='width:100%;border-collapse:collapse;margin:16px 0'>
          <tr style='background:#fff8e8'>
            <td style='padding:10px 14px;font-weight:700;color:#555;width:160px;border:1px solid #f0d080'>Advisory</td>
            <td style='padding:10px 14px;border:1px solid #f0d080;font-weight:700;color:#ca8a04'>
              " . htmlspecialchars($title) . "
            </td>
          </tr>
          <tr>
            <td style='padding:10px 14px;font-weight:700;color:#555;border:1px solid #f0d080'>Affected Areas</td>
            <td style='padding:10px 14px;border:1px solid #f0d080'>" . htmlspecialchars($affectedBarangays) . "</td>
          </tr>
          <tr style='background:#fff8e8'>
            <td style='padding:10px 14px;font-weight:700;color:#555;border:1px solid #f0d080'>Start</td>
            <td style='padding:10px 14px;border:1px solid #f0d080'>" . htmlspecialchars($startDatetime) . "</td>
          </tr>
          " . ($endDatetime ? "
          <tr>
            <td style='padding:10px 14px;font-weight:700;color:#555;border:1px solid #f0d080'>Expected End</td>
            <td style='padding:10px 14px;border:1px solid #f0d080'>" . htmlspecialchars($endDatetime) . "</td>
          </tr>" : "") . "
          " . ($description ? "
          <tr style='background:#fff8e8'>
            <td style='padding:10px 14px;font-weight:700;color:#555;border:1px solid #f0d080'>Details</td>
            <td style='padding:10px 14px;border:1px solid #f0d080'>" . nl2br(htmlspecialchars($description)) . "</td>
          </tr>" : "") . "
        </table>
        <p style='color:#555;font-size:13px'>
          We apologize for any inconvenience this may cause. Our team will work to restore
          normal water supply as quickly as possible.<br><br>
          For emergencies, please call our hotline: <strong>(083) 999-0000</strong> (available 24/7).
        </p>";

    $html = buildEmailTemplate('Water Service Advisory', $body);
    return sendEmailNotification($toEmail, $consumerName, $emailSubject, $html);
}