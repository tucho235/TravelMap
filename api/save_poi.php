<?php
/**
 * API Endpoint - Save POI from Map Editor
 * 
 * Creates a new point of interest from the map editor interface
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Point.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $pointModel = new Point();

    $data = [
        'trip_id'    => isset($input['trip_id']) ? (int)$input['trip_id'] : null,
        'title'      => trim($input['title'] ?? ''),
        'description' => '',
        'type'       => $input['type'] ?? 'visit',
        'icon'       => 'default',
        'latitude'   => $input['latitude'] ?? '',
        'longitude'  => $input['longitude'] ?? '',
        'visit_date' => null,
        'image_path' => null
    ];

    // Build visit_date from optional date + time
    if (!empty($input['visit_date'])) {
        $time_part = !empty($input['visit_time']) ? $input['visit_time'] . ':00' : '00:00:00';
        $data['visit_date'] = $input['visit_date'] . ' ' . $time_part;
    }

    // Validate
    $errors = $pointModel->validate($data);
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    $new_id = $pointModel->create($data);
    if ($new_id) {
        echo json_encode([
            'success' => true, 
            'id' => (int)$new_id,
            'point' => [
                'id' => (int)$new_id,
                'title' => $data['title'],
                'latitude' => (float)$data['latitude'],
                'longitude' => (float)$data['longitude'],
                'type' => $data['type']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error creating point']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
