<?php
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $err['message'],
            'file'  => $err['file'],
            'line'  => $err['line'],
        ]);
    }
});
ob_start();

ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

requireAuth();
requireRole('Admin');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit',
        UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by PHP extension',
    ];
    $code = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    jsonResponse(['error' => $uploadErrors[$code] ?? 'Upload error'], 422);
}

$mimeOk = in_array($_FILES['csv_file']['type'], [
    'text/csv', 'application/csv', 'text/plain',
    'application/vnd.ms-excel', 'text/comma-separated-values'
]);
if (!$mimeOk && !str_ends_with(strtolower($_FILES['csv_file']['name']), '.csv')) {
    jsonResponse(['error' => 'Only CSV files are accepted'], 422);
}

$tmpPath  = $_FILES['csv_file']['tmp_name'];
$filename = basename($_FILES['csv_file']['name']);
$db       = getDB();

// ── Log import record ──────────────────────────────────────
$logStmt = $db->prepare(
    "INSERT INTO csv_imports (filename, table_target, status, uploaded_by) VALUES (?,?,?,?)"
);
$logStmt->execute([$filename, 'consumers', 'Processing', $_SESSION['user_id']]);
$importId = $db->lastInsertId();

// ── Open and parse headers ─────────────────────────────────
$handle = fopen($tmpPath, 'r');
if (!$handle) {
    jsonResponse(['error' => 'Cannot read uploaded file'], 500);
}

$rawHeaders = fgetcsv($handle);
if (!$rawHeaders) {
    fclose($handle);
    jsonResponse(['error' => 'Empty or invalid CSV — could not read header row'], 422);
}

$headers = array_map(function ($h) {
    return trim(str_replace("\xEF\xBB\xBF", '', $h));
}, $rawHeaders);

// ── Validate required columns ──────────────────────────────
$requiredCols = ['ACCOUNTID', 'ACCOUNT_NAME'];
$missing = array_filter($requiredCols, fn($col) => !in_array($col, $headers));
if ($missing) {
    fclose($handle);
    jsonResponse([
        'error'    => 'Required columns missing: ' . implode(', ', $missing),
        'found'    => $headers,
        'required' => $requiredCols,
    ], 422);
}

// ── Prepare consumers upsert ───────────────────────────────
$upsertConsumer = $db->prepare(
    "INSERT INTO consumers
       (account_id, account_no, name, type, status, address, zone,
        contact_no, meter_brand, meter_number, installation_date)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
       account_no        = VALUES(account_no),
       name              = VALUES(name),
       type              = VALUES(type),
       status            = VALUES(status),
       address           = VALUES(address),
       zone              = VALUES(zone),
       contact_no        = VALUES(contact_no),
       meter_brand       = VALUES(meter_brand),
       meter_number      = VALUES(meter_number),
       installation_date = VALUES(installation_date),
       updated_at        = NOW()"
);

// ── Prepare water_meters upsert ────────────────────────────
// meter_no has UNIQUE KEY — ON DUPLICATE KEY UPDATE handles deduplication.
// latitude/longitude/status left as defaults since billing CSV has no coords.
$upsertMeter = $db->prepare(
    "INSERT INTO water_meters
       (consumer_id, meter_no, brand, installation_date, status)
     VALUES (?,?,?,?,'active')
     ON DUPLICATE KEY UPDATE
       brand             = VALUES(brand),
       installation_date = VALUES(installation_date)"
);

// ── Prepare consumer_id lookup ─────────────────────────────
$lookupConsumer = $db->prepare(
    "SELECT id FROM consumers WHERE account_id = ? LIMIT 1"
);

// ── Process rows ───────────────────────────────────────────
$total      = 0;
$imported   = 0;
$skipped    = 0;
$errors     = [];
$batchSize  = 500;
$batchCount = 0;

$db->beginTransaction();

while (($row = fgetcsv($handle)) !== false) {
    $total++;

    if (count(array_filter($row, 'strlen')) === 0) {
        continue;
    }

    if (count($row) !== count($headers)) {
        $errors[] = "Row $total: column count mismatch (got " . count($row) . ", expected " . count($headers) . ")";
        $skipped++;
        continue;
    }

    $data = array_combine($headers, $row);
    $data = array_map('trim', $data);

    // ── Required field validation ──────────────────────────
    $accountId = $data['ACCOUNTID']    ?? '';
    $name      = $data['ACCOUNT_NAME'] ?? '';

    if ($accountId === '') {
        $errors[] = "Row $total: missing ACCOUNTID — skipped";
        $skipped++;
        continue;
    }
    if ($name === '') {
        $errors[] = "Row $total (ACCOUNTID=$accountId): missing ACCOUNT_NAME — skipped";
        $skipped++;
        continue;
    }

    // ── Field mapping ──────────────────────────────────────
    $accountNo   = $data['ACCOUNTNO']    ?? '';
    $address     = $data['ADDRESS1']     ?? '';
    $zone        = $data['ZONE_DESC']    ?? '';
    $contactInfo = $data['ADDRESS2']     ?? '';
    $meterBrand  = $data['METER_BRAND']  ?? '';
    $meterNumber = $data['METER_NUMBER'] ?? '';
    $installDate = normalizeDate($data['INSTALL_DATE'] ?? '');

    // CATEGORY_DESC → type ENUM
    $rawCategory = strtolower($data['CATEGORY_DESC'] ?? '');
    $type = match (true) {
        str_contains($rawCategory, 'com') => 'Commercial',
        str_contains($rawCategory, 'gov') => 'Government',
        default                            => 'Residential',
    };

    // STATUS_CODE → status ENUM
    $rawStatus = strtolower($data['STATUS_CODE'] ?? '');
    $status = match (true) {
        str_contains($rawStatus, 'dis') => 'Disconnected',
        str_contains($rawStatus, 'pen') => 'Pending',
        default                          => 'Active',
    };

    // ── Insert / Update consumer ───────────────────────────
    try {
        $upsertConsumer->execute([
            $accountId,
            $accountNo,
            $name ?: 'Unknown',
            $type,
            $status,
            $address,
            $zone,
            $contactInfo,
            $meterBrand,
            $meterNumber,
            $installDate,
        ]);

        // ── Insert / Update water_meter ────────────────────
        // Only insert meter if meter_number is present
        if ($meterNumber !== '') {
            $lookupConsumer->execute([$accountId]);
            $consumer = $lookupConsumer->fetch();

            if ($consumer) {
                $upsertMeter->execute([
                    $consumer['id'],
                    $meterNumber,
                    $meterBrand ?: null,
                    $installDate,
                ]);
            }
        }

        $imported++;
        $batchCount++;

        if ($batchCount >= $batchSize) {
            $db->commit();
            $db->beginTransaction();
            $batchCount = 0;
        }

    } catch (PDOException $e) {
        $errors[] = "Row $total (ACCOUNTID=$accountId): DB error — " . $e->getMessage();
        $skipped++;
    }
}

// Commit remaining rows
if ($db->inTransaction()) {
    $db->commit();
}

fclose($handle);

// ── Finalise import log ───────────────────────────────────
$errText = implode('; ', array_slice($errors, 0, 30));
$db->prepare(
    "UPDATE csv_imports
     SET total_rows=?, imported_rows=?, failed_rows=?, status=?, error_log=?
     WHERE id=?"
)->execute([$total, $imported, $skipped, 'Completed', $errText, $importId]);

logActivity(
    $_SESSION['user_id'], 'csv_import_billing', 'consumers',
    (string)$importId,
    "Billing import '$filename': $imported imported, $skipped skipped of $total total"
);

jsonResponse([
    'success'    => true,
    'import_id'  => $importId,
    'total_rows' => $total,
    'imported'   => $imported,
    'skipped'    => $skipped,
    'errors'     => array_slice($errors, 0, 15),
]);

// ── Helper: normalize date to YYYY-MM-DD ──────────────────
function normalizeDate(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '' || strtolower($raw) === 'null') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }
    $ts = strtotime($raw);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }
    return null;
}