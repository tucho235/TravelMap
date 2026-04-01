<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/Point.php';
require_once __DIR__ . '/../src/models/Route.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trip_name = trim($_POST['trip_name'] ?? '');

    if (empty($trip_name)) {
        $error = "Debes ingresar un nombre para el viaje.";
    } elseif (!isset($_FILES['gpx_file']) || $_FILES['gpx_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Debes seleccionar un archivo GPX válido.";
    } else {
        $file = $_FILES['gpx_file'];
        $tmp_name = $file['tmp_name'];

        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'gpx') {
            $error = "El archivo debe tener extensión .gpx";
        } else {
            $xml = simplexml_load_file($tmp_name);
            if (!$xml) {
                $error = "El archivo GPX no es válido o está corrupto.";
            } else {
                // Crear el viaje
                $tripModel = new Trip();
                $trip_id = $tripModel->create([
                    'title'       => $trip_name,
                    'description' => 'Importado desde OpenRailRouting / GraphHopper GPX',
                    'start_date'  => date('Y-m-d'),
                    'end_date'    => date('Y-m-d'),
                    'color_hex'   => '#3388ff',
                    'status'      => 'draft'
                ]);

                if (!$trip_id) {
                    $error = "Error al crear el viaje.";
                } else {
                    $pointModel = new Point();
                    $routeModel = new Route();

                    $wpt_count = 0;
                    $trk_count = 0;

                    // === Importar waypoints (wpt) como puntos de interés ===
                    if (isset($xml->wpt)) {
                        foreach ($xml->wpt as $wpt) {
                            $lat = (float)$wpt['lat'];
                            $lon = (float)$wpt['lon'];
                            $name = (string)($wpt->name ?? 'Punto importado ' . ($wpt_count + 1));
                            $desc = (string)($wpt->desc ?? '');

                            $result = $pointModel->create([
                                'trip_id'     => $trip_id,
                                'title'       => $name,
                                'description' => $desc,
                                'type'        => 'waypoint',      // ← SOLUCIÓN: valor obligatorio
                                'latitude'    => $lat,
                                'longitude'   => $lon
                            ]);

                            if ($result) $wpt_count++;
                        }
                    }

                    // === Importar trackpoints (trkpt) como ruta ===
                    $coordinates = [];
                    if (isset($xml->trk->trkseg->trkpt)) {
                        foreach ($xml->trk->trkseg->trkpt as $trkpt) {
                            $lat = (float)$trkpt['lat'];
                            $lon = (float)$trkpt['lon'];
                            $coordinates[] = [$lon, $lat];
                        }
                    }

                    if (count($coordinates) >= 2) {
                        $geojson = json_encode([
                            "type" => "Feature",
                            "properties" => [],
                            "geometry" => [
                                "type" => "LineString",
                                "coordinates" => $coordinates
                            ]
                        ]);

                        $result = $routeModel->create([
                            'trip_id'        => $trip_id,
                            'name'           => 'Ruta principal',
                            'transport_type' => 'train',        // ← SOLUCIÓN: valor obligatorio
                            'geojson_data'   => $geojson,
                            'color'          => '#3388ff',
                            'is_round_trip'  => 0
                        ]);

                        if ($result) $trk_count = count($coordinates);
                    }

                    $success = "¡Importación completada con éxito!<br>
                                <strong>Viaje:</strong> $trip_name (ID: $trip_id)<br>
                                <strong>Puntos de interés importados:</strong> $wpt_count<br>
                                <strong>Puntos de ruta importados:</strong> $trk_count";
                }
            }
        }
    }
}
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-4">
    <h2>Importar GPX desde OpenRailRouting / GraphHopper</h2>
    <p class="text-muted">Los <code>&lt;wpt&gt;</code> se guardan como puntos de interés (type = waypoint) y los <code>&lt;trkpt&gt;</code> como ruta LineString (transport_type = train).</p>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="trip_name" class="form-label">Nombre del viaje <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="trip_name" name="trip_name" required>
        </div>

        <div class="mb-3">
            <label for="gpx_file" class="form-label">Archivo GPX <span class="text-danger">*</span></label>
            <input type="file" class="form-control" id="gpx_file" name="gpx_file" accept=".gpx" required>
        </div>

        <button type="submit" class="btn btn-primary">Importar GPX ahora</button>
        <a href="trips.php" class="btn btn-secondary">Volver a Viajes</a>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
