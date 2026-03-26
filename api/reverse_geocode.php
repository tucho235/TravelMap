<?php
/**
 * API: Geocodificación inversa
 *
 * Convierte coordenadas (lat, lng) en un nombre de ciudad/lugar
 * usando el servicio gratuito Nominatim de OpenStreetMap.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Validate coordinates
if (!isset($_GET['lat'], $_GET['lng'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros lat y lng requeridos']);
    exit;
}

$lat = filter_var($_GET['lat'], FILTER_VALIDATE_FLOAT);
$lng = filter_var($_GET['lng'], FILTER_VALIDATE_FLOAT);

if ($lat === false || $lng === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Coordenadas inválidas']);
    exit;
}

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Coordenadas fuera de rango']);
    exit;
}

// Build Nominatim reverse geocode URL (zoom=10 = city level)
$nominatimUrl = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
    'lat'            => $lat,
    'lon'            => $lng,
    'format'         => 'json',
    'addressdetails' => 1,
    'zoom'           => 10,
]);

// Execute cURL request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,            $nominatimUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT,        10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_USERAGENT,      'TravelMap/1.0 (PHP Proxy)');
curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Accept: application/json', 'Accept-Language: es,en']);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode([
        'success' => false,
        'error'   => 'Error al conectar con el servicio de geocodificación' . ($curlError ? ': ' . $curlError : ''),
    ]);
    exit;
}

$data = json_decode($response, true);

if (!$data || isset($data['error'])) {
    echo json_encode(['success' => false, 'error' => 'Ubicación no encontrada']);
    exit;
}

// Extract the most relevant city-level name from the address components
$address = $data['address'] ?? [];

$city = $address['city']
    ?? $address['town']
    ?? $address['village']
    ?? $address['municipality']
    ?? $address['county']
    ?? $address['state']
    ?? null;

if (!$city) {
    echo json_encode(['success' => false, 'error' => 'No se encontró nombre de ciudad para estas coordenadas']);
    exit;
}

echo json_encode([
    'success'      => true,
    'city'         => $city,
    'display_name' => $data['display_name'] ?? $city,
    'country'      => $address['country'] ?? null,
]);
