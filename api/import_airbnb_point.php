<?php
/**
 * API: Import single Airbnb point
 * 
 * Processes one destination at a time to avoid timeout issues
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Point.php';

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$destination = $input['destination'] ?? '';
$dates = $input['dates'] ?? '';
$firstDate = $input['first_date'] ?? null;
$tripId = $input['trip_id'] ?? null;

if (empty($destination) || empty($tripId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos requeridos']);
    exit;
}

/**
 * Geocode a city name using Nominatim
 */
function geocodeCity($cityName) {
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'format' => 'json',
        'q' => $cityName,
        'limit' => 1,
        'addressdetails' => 1
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TravelMap/1.0 (PHP Airbnb Importer)');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: es,en'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false || $httpCode !== 200) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (empty($data)) {
        return null;
    }
    
    return [
        'lat' => (float)$data[0]['lat'],
        'lon' => (float)$data[0]['lon'],
        'display_name' => $data[0]['display_name'] ?? $cityName
    ];
}

// Check for duplicates first
$conn = getDB();
$checkSql = "SELECT id FROM points_of_interest WHERE title = ? AND visit_date = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->execute([$destination, $firstDate]);
$existing = $checkStmt->fetch();

if ($existing) {
    echo json_encode([
        'success' => true,
        'destination' => $destination,
        'skipped' => true,
        'message' => 'Ya existe (ID: ' . $existing['id'] . ')'
    ]);
    exit;
}

// Geocode the city
$geo = geocodeCity($destination);

if ($geo === null) {
    echo json_encode([
        'success' => false,
        'destination' => $destination,
        'message' => 'No se pudo geocodificar'
    ]);
    exit;
}

// Create the point
$pointModel = new Point();
$pointData = [
    'trip_id' => $tripId,
    'title' => $destination,
    'description' => 'Estadía en Airbnb - ' . $dates,
    'type' => 'stay',
    'icon' => 'hotel',
    'latitude' => $geo['lat'],
    'longitude' => $geo['lon'],
    'visit_date' => $firstDate
];

$pointId = $pointModel->create($pointData);

if ($pointId) {
    echo json_encode([
        'success' => true,
        'destination' => $destination,
        'point_id' => $pointId,
        'lat' => $geo['lat'],
        'lon' => $geo['lon']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'destination' => $destination,
        'message' => 'Error al crear el punto'
    ]);
}
