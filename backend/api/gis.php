<?php
// backend/api/gis.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

requireAuth();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_pipelines':        getPipelines();        break;
    case 'get_infrastructure':   getInfrastructure();   break;
    case 'get_parcels':          getParcels();          break;
    case 'get_consumers_geo':    getConsumersGeo();     break;
    case 'heatmap_data':         getHeatmapData();      break;
    case 'proximity_analysis':   proximityAnalysis();   break;
    case 'emergency_incidents':  getEmergencyIncidents(); break;
    case 'add_emergency':        addEmergencyIncident(); break;
    case 'resolve_emergency':    resolveEmergency();    break;
    case 'pipeline_history':     getPipelineHistory();  break;
    case 'save_pipeline':        savePipeline();        break;
    case 'save_infrastructure':  saveInfrastructure();  break;
    default: jsonResponse(['error' => 'Unknown action'], 400);
}

function getPipelines(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT id, name, material, diameter_mm, status, installation_date,
                path_geojson, barangay, notes, created_at
         FROM pipelines ORDER BY id ASC"
    );
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        if ($r['path_geojson']) {
            $r['path_geojson'] = json_decode($r['path_geojson'], true);
        }
    }
    jsonResponse(['data' => $rows]);
}

function getInfrastructure(): void {
    $type = $_GET['type'] ?? '';
    $db   = getDB();
    $sql  = "SELECT id, type, name, latitude, longitude, address, barangay, status,
                    installation_date, last_inspection, notes
             FROM infrastructure";
    $params = [];
    if ($type) {
        $sql .= " WHERE type = ?";
        $params[] = $type;
    }
    $sql .= " ORDER BY type, name";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function getParcels(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT id, parcel_code, owner_name, address, barangay, area_sqm,
                boundary_geojson, notes FROM parcels ORDER BY id ASC"
    );
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        if ($r['boundary_geojson']) {
            $r['boundary_geojson'] = json_decode($r['boundary_geojson'], true);
        }
    }
    jsonResponse(['data' => $rows]);
}

function getConsumersGeo(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT c.id, c.account_id, c.name, c.type, c.status,
                c.latitude, c.longitude, c.barangay,
                cr.consumption_m3 as latest_consumption
         FROM consumers c
         LEFT JOIN (
             SELECT consumer_id, consumption_m3
             FROM consumption_records
             WHERE (consumer_id, billing_month) IN (
                 SELECT consumer_id, MAX(billing_month)
                 FROM consumption_records GROUP BY consumer_id
             )
         ) cr ON cr.consumer_id = c.id
         WHERE c.latitude IS NOT NULL AND c.longitude IS NOT NULL"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function getHeatmapData(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT c.latitude, c.longitude, cr.consumption_m3 as intensity
         FROM consumers c
         JOIN consumption_records cr ON cr.consumer_id = c.id
         WHERE c.latitude IS NOT NULL AND c.longitude IS NOT NULL
           AND cr.billing_month = (
               SELECT MAX(billing_month) FROM consumption_records
               WHERE consumer_id = c.id
           )"
    );
    $points = [];
    foreach ($stmt->fetchAll() as $row) {
        $points[] = [
            floatval($row['latitude']),
            floatval($row['longitude']),
            floatval($row['intensity']) / 100.0 // normalize
        ];
    }
    jsonResponse(['points' => $points]);
}

function proximityAnalysis(): void {
    $lat    = floatval($_GET['lat'] ?? 0);
    $lng    = floatval($_GET['lng'] ?? 0);
    $radius = floatval($_GET['radius'] ?? 0.5); // km

    if (!$lat || !$lng) {
        jsonResponse(['error' => 'Coordinates required'], 422);
    }

    $db = getDB();
    // Haversine formula in MySQL
    $haversine = "(6371 * ACOS(
        COS(RADIANS(?)) * COS(RADIANS(latitude)) *
        COS(RADIANS(longitude) - RADIANS(?)) +
        SIN(RADIANS(?)) * SIN(RADIANS(latitude))
    ))";

    $stmt = $db->prepare(
        "SELECT id, type, name, latitude, longitude, status,
                $haversine AS distance_km
         FROM infrastructure
         WHERE latitude IS NOT NULL AND longitude IS NOT NULL
           AND $haversine < ?
         ORDER BY distance_km ASC
         LIMIT 50"
    );
    $stmt->execute([$lat, $lng, $lat, $lat, $lng, $lat, $radius]);
    $infra = $stmt->fetchAll();

    $stmt2 = $db->prepare(
        "SELECT id, account_id, name, type, status, latitude, longitude,
                $haversine AS distance_km
         FROM consumers
         WHERE latitude IS NOT NULL AND longitude IS NOT NULL
           AND $haversine < ?
         ORDER BY distance_km ASC
         LIMIT 100"
    );
    $stmt2->execute([$lat, $lng, $lat, $lat, $lng, $lat, $radius]);
    $consumers = $stmt2->fetchAll();

    jsonResponse([
        'center'    => ['lat' => $lat, 'lng' => $lng],
        'radius_km' => $radius,
        'infrastructure' => $infra,
        'consumers'      => $consumers,
    ]);
}

function getEmergencyIncidents(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT ei.*, u.name as reporter_name
         FROM emergency_incidents ei
         LEFT JOIN users u ON u.id = ei.reported_by
         WHERE ei.status != 'Resolved'
         ORDER BY ei.created_at DESC"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function addEmergencyIncident(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $required = ['type', 'title', 'severity', 'latitude', 'longitude'];
    foreach ($required as $f) {
        if (empty($data[$f])) {
            jsonResponse(['error' => "Field '$f' is required"], 422);
        }
    }

    $db   = getDB();
    $stmt = $db->prepare(
        "INSERT INTO emergency_incidents
         (type, title, description, severity, latitude, longitude, location_text, reported_by)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $data['type'], $data['title'], $data['description'] ?? '',
        $data['severity'], $data['latitude'], $data['longitude'],
        $data['location_text'] ?? '', $_SESSION['user_id']
    ]);
    $id = $db->lastInsertId();
    logActivity($_SESSION['user_id'], 'add_emergency', 'emergency_incidents', (string)$id, $data['title']);
    jsonResponse(['success' => true, 'id' => $id]);
}

function resolveEmergency(): void {
    requireRole('Admin', 'Staff');
    $id    = intval($_POST['id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    if (!$id) jsonResponse(['error' => 'ID required'], 422);

    $db   = getDB();
    $stmt = $db->prepare(
        "UPDATE emergency_incidents SET status='Resolved', response_notes=?, resolved_at=NOW()
         WHERE id=?"
    );
    $stmt->execute([$notes, $id]);
    logActivity($_SESSION['user_id'], 'resolve_emergency', 'emergency_incidents', (string)$id, $notes);
    jsonResponse(['success' => true]);
}

function getPipelineHistory(): void {
    $pid  = intval($_GET['pipeline_id'] ?? 0);
    if (!$pid) jsonResponse(['error' => 'pipeline_id required'], 422);
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT ph.*, u.name as changed_by_name
         FROM pipeline_history ph
         LEFT JOIN users u ON u.id = ph.changed_by
         WHERE ph.pipeline_id = ?
         ORDER BY ph.changed_at DESC"
    );
    $stmt->execute([$pid]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function savePipeline(): void {
    requireRole('Admin');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    if (!empty($data['id'])) {
        $stmt = $db->prepare(
            "UPDATE pipelines SET name=?,material=?,diameter_mm=?,status=?,
             installation_date=?,path_geojson=?,barangay=?,notes=?
             WHERE id=?"
        );
        $stmt->execute([
            $data['name'] ?? '', $data['material'] ?? 'PVC',
            $data['diameter_mm'] ?? null, $data['status'] ?? 'active',
            $data['installation_date'] ?? null,
            json_encode($data['path_geojson'] ?? null),
            $data['barangay'] ?? '', $data['notes'] ?? '',
            $data['id']
        ]);
        // log history
        $h = $db->prepare(
            "INSERT INTO pipeline_history (pipeline_id,changed_by,change_type,reason)
             VALUES (?,?,?,?)"
        );
        $h->execute([$data['id'], $_SESSION['user_id'], 'other', $data['reason'] ?? 'Updated']);
        jsonResponse(['success' => true]);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO pipelines (name,material,diameter_mm,status,installation_date,path_geojson,barangay,notes,created_by)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['name'] ?? '', $data['material'] ?? 'PVC',
            $data['diameter_mm'] ?? null, $data['status'] ?? 'active',
            $data['installation_date'] ?? null,
            json_encode($data['path_geojson'] ?? null),
            $data['barangay'] ?? '', $data['notes'] ?? '',
            $_SESSION['user_id']
        ]);
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
    }
}

function saveInfrastructure(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    if (!empty($data['id'])) {
        $stmt = $db->prepare(
            "UPDATE infrastructure SET type=?,name=?,latitude=?,longitude=?,
             address=?,barangay=?,status=?,installation_date=?,last_inspection=?,notes=?
             WHERE id=?"
        );
        $stmt->execute([
            $data['type'], $data['name'], $data['latitude'], $data['longitude'],
            $data['address'] ?? '', $data['barangay'] ?? '', $data['status'] ?? 'active',
            $data['installation_date'] ?? null, $data['last_inspection'] ?? null,
            $data['notes'] ?? '', $data['id']
        ]);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO infrastructure (type,name,latitude,longitude,address,barangay,status,installation_date,last_inspection,notes,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['type'], $data['name'], $data['latitude'], $data['longitude'],
            $data['address'] ?? '', $data['barangay'] ?? '', $data['status'] ?? 'active',
            $data['installation_date'] ?? null, $data['last_inspection'] ?? null,
            $data['notes'] ?? '', $_SESSION['user_id']
        ]);
    }
    jsonResponse(['success' => true]);
}
