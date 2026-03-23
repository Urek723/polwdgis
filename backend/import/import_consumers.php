<?php
/**
 * GIS Consumer CSV Import Handler
 * backend/import/import_consumers.php
 *
 * Accepts CSV with columns:
 *   id, y (UTM Northing), x (UTM Easting), z (Elevation),
 *   ACCOUNTID, customer_n, zone, cust_type, status,
 *   met_brand, met_no, address, barangay, municipal,
 *   account_no, contact_no
 *
 * Converts UTM EPSG:32651 → WGS84 EPSG:4326 before storing.
 * Duplicate ACCOUNTID rows are UPDATED, not duplicated.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/utm_converter.php';

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

// Strip BOM and trim
$headers = array_map(function ($h) {
    return trim(str_replace("\xEF\xBB\xBF", '', $h));
}, $rawHeaders);

// ── Validate required columns ──────────────────────────────
$requiredCols = ['ACCOUNTID', 'customer_n', 'x', 'y'];
$missing = array_filter($requiredCols, fn($col) => !in_array($col, $headers));
if ($missing) {
    fclose($handle);
    jsonResponse([
        'error'   => 'Required columns missing: ' . implode(', ', $missing),
        'found'   => $headers,
        'required'=> $requiredCols,
    ], 422);
}

// ── Prepare upsert statement ───────────────────────────────
$upsert = $db->prepare(
    "INSERT INTO consumers
       (account_id, account_no, name, type, status, address, barangay, municipal,
        zone, contact_no, meter_brand, meter_number,
        x_utm, y_utm, elevation, latitude, longitude)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
       account_no   = VALUES(account_no),
       name         = VALUES(name),
       type         = VALUES(type),
       status       = VALUES(status),
       address      = VALUES(address),
       barangay     = VALUES(barangay),
       municipal    = VALUES(municipal),
       zone         = VALUES(zone),
       contact_no   = VALUES(contact_no),
       meter_brand  = VALUES(meter_brand),
       meter_number = VALUES(meter_number),
       x_utm        = VALUES(x_utm),
       y_utm        = VALUES(y_utm),
       elevation    = VALUES(elevation),
       latitude     = VALUES(latitude),
       longitude    = VALUES(longitude),
       updated_at   = NOW()"
);

// ── Process rows ───────────────────────────────────────────
$total    = 0;
$imported = 0;
$skipped  = 0;
$errors   = [];

while (($row = fgetcsv($handle)) !== false) {
    $total++;

    // Skip completely empty rows
    if (count(array_filter($row, 'strlen')) === 0) {
        continue;
    }

    if (count($row) !== count($headers)) {
        $errors[] = "Row $total: column count mismatch (got " . count($row) . ", expected " . count($headers) . ")";
        $skipped++;
        continue;
    }

    $data = array_combine($headers, $row);

    // ── Coordinates ────────────────────────────────────────
    $xRaw = trim($data['x'] ?? '');
    $yRaw = trim($data['y'] ?? '');
    $zRaw = trim($data['z'] ?? '');

    if ($xRaw === '' || $yRaw === '') {
        $errors[] = "Row $total: missing x or y coordinate — skipped";
        $skipped++;
        continue;
    }

    $xFloat = floatval($xRaw);
    $yFloat = floatval($yRaw);

    if (!isValidUtm51N($xFloat, $yFloat)) {
        $errors[] = "Row $total: UTM coordinates out of Zone 51N range (x=$xFloat, y=$yFloat)";
        $skipped++;
        continue;
    }

    // ── Convert UTM → WGS84 ───────────────────────────────
    try {
        $wgs84 = utmToWgs84($xFloat, $yFloat, 51, true);
        $lat   = round($wgs84['latitude'], 8);
        $lng   = round($wgs84['longitude'], 8);
    } catch (Throwable $e) {
        $errors[] = "Row $total: coordinate conversion failed — " . $e->getMessage();
        $skipped++;
        continue;
    }

    // ── Account ID ────────────────────────────────────────
    $accountId = trim($data['ACCOUNTID'] ?? '');
    if ($accountId === '') {
        $errors[] = "Row $total: missing ACCOUNTID — skipped";
        $skipped++;
        continue;
    }

    // ── Map cust_type → ENUM ──────────────────────────────
    $rawType = strtolower(trim($data['cust_type'] ?? ''));
    $type = match (true) {
        str_contains($rawType, 'com') => 'Commercial',
        str_contains($rawType, 'gov') => 'Government',
        default                        => 'Residential',
    };

    // ── Map status → ENUM ─────────────────────────────────
    $rawStatus = strtolower(trim($data['status'] ?? ''));
    $status = match (true) {
        str_contains($rawStatus, 'dis') => 'Disconnected',
        str_contains($rawStatus, 'pen') => 'Pending',
        default                          => 'Active',
    };

    // ── Elevation ─────────────────────────────────────────
    $elevation = ($zRaw !== '' && is_numeric($zRaw)) ? floatval($zRaw) : null;

    // ── Insert / Update ───────────────────────────────────
    try {
        $upsert->execute([
            $accountId,
            trim($data['account_no']  ?? ''),
            trim($data['customer_n']  ?? '') ?: 'Unknown',
            $type,
            $status,
            trim($data['address']     ?? ''),
            trim($data['barangay']    ?? ''),
            trim($data['municipal']   ?? ''),
            trim($data['zone']        ?? ''),
            trim($data['contact_no']  ?? ''),
            trim($data['met_brand']   ?? ''),
            trim($data['met_no']      ?? ''),
            $xFloat,
            $yFloat,
            $elevation,
            $lat,
            $lng,
        ]);
        $imported++;
    } catch (PDOException $e) {
        $errors[] = "Row $total (ACCOUNTID=$accountId): DB error — " . $e->getMessage();
        $skipped++;
    }
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
    $_SESSION['user_id'], 'csv_import_gis', 'consumers',
    (string)$importId,
    "GIS import '$filename': $imported imported, $skipped skipped of $total total"
);

jsonResponse([
    'success'    => true,
    'import_id'  => $importId,
    'total_rows' => $total,
    'imported'   => $imported,
    'skipped'    => $skipped,
    'errors'     => array_slice($errors, 0, 15),
]);
