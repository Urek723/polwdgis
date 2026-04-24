<?php
// backend/consumption/check_high_consumption.php
// Detection logic for >10m³ consumption + notification + email trigger

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

$emailHelper = __DIR__ . '/../notifications/send_email.php';
if (file_exists($emailHelper)) {
    require_once $emailHelper;
}

/**
 * Check all consumption records with consumption_m3 > 10
 * and fire notifications + email for those not yet notified.
 *
 * @param PDO|null $db  Pass existing connection or null to get one.
 * @return array        ['checked' => int, 'notified' => int]
 */
function checkHighConsumption(?PDO $db = null): array {
    if (!$db) $db = getDB();

    $stmt = $db->query(
        "SELECT cr.*, c.name AS consumer_name, c.account_no, c.barangay, c.email AS consumer_email,
                c.id AS consumer_id_fk,
                ca.email AS auth_email
         FROM consumption_records cr
         JOIN consumers c ON c.id = cr.consumer_id
         LEFT JOIN consumers_auth ca ON ca.account_number = c.account_no
         WHERE cr.consumption_m3 > 10
         ORDER BY cr.billing_month DESC"
    );
    $records = $stmt->fetchAll();

    $checked  = count($records);
    $notified = 0;

    foreach ($records as $rec) {
        $consumerId   = (int)$rec['consumer_id'];
        $consumption  = floatval($rec['consumption_m3']);
        $month        = date('F Y', strtotime($rec['billing_month']));
        $name         = $rec['consumer_name'];

        // Check if notification already exists for this consumer+month
        $exists = $db->prepare(
            "SELECT id FROM notifications
             WHERE consumer_id = ?
               AND type = 'alert'
               AND title = 'High Water Consumption'
               AND message LIKE ?
             LIMIT 1"
        );
        $exists->execute([$consumerId, "%$month%"]);
        if ($exists->fetch()) continue;

        // Insert system notification
        $message = "Dear {$name}, your water consumption of {$consumption} m³ for {$month} "
                 . "has exceeded the 10 m³ threshold. Please check for possible leaks.";

        $db->prepare(
            "INSERT INTO notifications (consumer_id, type, title, message, is_read)
             VALUES (?, 'alert', 'High Water Consumption', ?, 0)"
        )->execute([$consumerId, $message]);

        // Send email (non-fatal)
        $email = $rec['auth_email'] ?: $rec['consumer_email'];
        if ($email && function_exists('sendEmailNotification')) {
            try {
                $html = "
                <html><body style='font-family:Arial;background:#f4f6f9;padding:20px'>
                <div style='max-width:600px;margin:auto;background:#fff;padding:24px;border-radius:10px'>
                  <h2 style='color:#ff4d6d'>⚠️ High Water Consumption Alert</h2>
                  <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
                  <p>Your water consumption for <strong>{$month}</strong> has exceeded the 10 m³ threshold.</p>
                  <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                    <tr><td style='padding:8px;border:1px solid #ddd;color:#555'>Consumer Name</td><td style='padding:8px;border:1px solid #ddd'><strong>" . htmlspecialchars($name) . "</strong></td></tr>
                    <tr><td style='padding:8px;border:1px solid #ddd;color:#555'>Billing Month</td><td style='padding:8px;border:1px solid #ddd'><strong>{$month}</strong></td></tr>
                    <tr><td style='padding:8px;border:1px solid #ddd;color:#555'>Consumption</td><td style='padding:8px;border:1px solid #ddd'><strong style='color:#ff4d6d'>{$consumption} m³</strong></td></tr>
                  </table>
                  <p>Please check your property for possible leaks or excessive water usage.</p>
                  <p>For concerns, contact us at <strong>(083) 123-4567</strong>.</p>
                  <hr><small>Polomolok Water District</small>
                </div></body></html>";

                sendEmailNotification($email, $name, 'High Water Consumption Alert', $html);
            } catch (Throwable $e) {
                error_log('[HighConsumption] Email failed for ' . $email . ': ' . $e->getMessage());
            }
        }

        $notified++;
    }

    return ['checked' => $checked, 'notified' => $notified];
}

/**
 * Hook: call this right after saving a consumption record.
 * Pass the consumer_id and consumption_m3 value.
 */
function checkSingleConsumerConsumption(PDO $db, int $consumerId, float $consumptionM3, string $billingMonth): void {
    if ($consumptionM3 <= 10) return;

    $stmt = $db->prepare(
        "SELECT c.name, c.account_no, c.email AS consumer_email,
                ca.email AS auth_email
         FROM consumers c
         LEFT JOIN consumers_auth ca ON ca.account_number = c.account_no
         WHERE c.id = ? LIMIT 1"
    );
    $stmt->execute([$consumerId]);
    $consumer = $stmt->fetch();
    if (!$consumer) return;

    $month = date('F Y', strtotime($billingMonth));
    $name  = $consumer['name'];

    // Avoid duplicate notifications
    $exists = $db->prepare(
        "SELECT id FROM notifications
         WHERE consumer_id = ? AND type = 'alert' AND title = 'High Water Consumption'
           AND message LIKE ? LIMIT 1"
    );
    $exists->execute([$consumerId, "%$month%"]);
    if ($exists->fetch()) return;

    $message = "Dear {$name}, your water consumption of {$consumptionM3} m³ for {$month} "
             . "has exceeded the 10 m³ threshold. Please check for possible leaks.";

    $db->prepare(
        "INSERT INTO notifications (consumer_id, type, title, message, is_read)
         VALUES (?, 'alert', 'High Water Consumption', ?, 0)"
    )->execute([$consumerId, $message]);

    $email = $consumer['auth_email'] ?: $consumer['consumer_email'];
    if ($email && function_exists('sendEmailNotification')) {
        try {
            $html = "
            <html><body style='font-family:Arial;background:#f4f6f9;padding:20px'>
            <div style='max-width:600px;margin:auto;background:#fff;padding:24px;border-radius:10px'>
              <h2 style='color:#ff4d6d'>⚠️ High Water Consumption Alert</h2>
              <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
              <p>Your water consumption for <strong>{$month}</strong> has exceeded 10 m³.</p>
              <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                <tr><td style='padding:8px;border:1px solid #ddd;color:#555'>Consumer</td><td style='padding:8px;border:1px solid #ddd'><strong>" . htmlspecialchars($name) . "</strong></td></tr>
                <tr><td style='padding:8px;border:1px solid #ddd;color:#555'>Month</td><td style='padding:8px;border:1px solid #ddd'><strong>{$month}</strong></td></tr>
                <tr><td style='padding:8px;border:1px solid #ddd;color:#555'>Consumption</td><td style='padding:8px;border:1px solid #ddd'><strong style='color:#ff4d6d'>{$consumptionM3} m³</strong></td></tr>
              </table>
              <p>Contact us at <strong>(083) 123-4567</strong> for assistance.</p>
              <hr><small>Polomolok Water District</small>
            </div></body></html>";

            sendEmailNotification($email, $name, 'High Water Consumption Alert', $html);
        } catch (Throwable $e) {
            error_log('[HighConsumption] Email failed: ' . $e->getMessage());
        }
    }
}

// Allow direct CLI / cron execution
if (PHP_SAPI === 'cli' || (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__)) {
    require_once __DIR__ . '/../../config/database.php';
    $result = checkHighConsumption();
    echo "Checked: {$result['checked']} records, Notified: {$result['notified']} new alerts\n";
}
