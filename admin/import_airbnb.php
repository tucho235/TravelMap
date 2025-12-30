<?php
/**
 * Importar Estadías desde Airbnb CSV
 * 
 * Permite importar puntos de interés (tipo: stay) desde un CSV exportado de Airbnb
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/Point.php';

$conn = getDB();
$tripModel = new Trip();
$pointModel = new Point();

$trips = $tripModel->getAll('start_date DESC');
$message = '';
$messageType = '';
$importResults = [];

/**
 * Parse Airbnb date format and extract the first date
 * Format examples: "Nov 14 – 18, 2025", "Mar 30 – Apr 4, 2018"
 * Note: Airbnb uses THIN SPACE (U+2009) and EN DASH (U+2013)
 */
function parseAirbnbDate($dateString) {
    // Remove extra spaces
    $dateString = trim($dateString);
    
    // Normalize all whitespace types (including thin space U+2009) to regular space
    $normalized = preg_replace('/[\s\x{2009}\x{00A0}]+/u', ' ', $dateString);
    
    // Replace various dash types (en-dash, em-dash, minus, hyphen) with regular hyphen
    $normalized = preg_replace('/[\x{2013}\x{2014}\x{2212}\x{2010}\x{2011}–—−-]+/u', '-', $normalized);
    
    // Extract year from the end first
    if (!preg_match('/(\d{4})$/', $normalized, $yearMatch)) {
        return null;
    }
    $year = $yearMatch[1];
    
    // Pattern: Month Day - ... Year
    // Match: "Nov 14 - 18, 2025" or "Mar 30 - Apr 4, 2018"
    if (preg_match('/^([A-Za-z]+)\s+(\d{1,2})\s*-/', $normalized, $matches)) {
        $month = $matches[1];
        $day = $matches[2];
        
        // Build date string and parse
        $dateStr = "$month $day, $year";
        $timestamp = strtotime($dateStr);
        
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
    }
    
    return null;
}

/**
 * Find a trip that contains the given date
 */
function findTripByDate($date, $trips) {
    if (!$date) return null;
    
    $visitDate = strtotime($date);
    
    foreach ($trips as $trip) {
        if (empty($trip['start_date']) || empty($trip['end_date'])) {
            continue;
        }
        
        $start = strtotime($trip['start_date']);
        $end = strtotime($trip['end_date']);
        
        // Check if the visit date falls within the trip date range
        if ($visitDate >= $start && $visitDate <= $end) {
            return $trip;
        }
    }
    
    return null;
}

/**
 * Geocode a city name using Nominatim via our proxy
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

// Handle clear action via GET
if (isset($_GET['clear'])) {
    unset($_SESSION['airbnb_preview']);
    unset($_SESSION['airbnb_csv']);
    header('Location: import_airbnb.php');
    exit;
}

// Process import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'preview') {
        // Preview CSV data
        $csvData = $_POST['csv_data'] ?? '';
        
        if (empty(trim($csvData))) {
            $message = 'Por favor, pega el contenido del CSV';
            $messageType = 'warning';
        } else {
            // Parse CSV
            $lines = explode("\n", trim($csvData));
            $header = str_getcsv(array_shift($lines), ',', '"', '');
            
            $previewData = [];
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                
                $row = str_getcsv($line, ',', '"', '');
                if (count($row) >= 2) {
                    $destination = $row[0] ?? '';
                    $dates = $row[1] ?? '';
                    $firstDate = parseAirbnbDate($dates);
                    
                    // Find matching trip by date
                    $matchingTrip = findTripByDate($firstDate, $trips);
                    
                    $previewData[] = [
                        'destination' => $destination,
                        'dates' => $dates,
                        'first_date' => $firstDate,
                        'first_date_formatted' => $firstDate ? date('d/m/Y', strtotime($firstDate)) : 'No detectada',
                        'trip_id' => $matchingTrip ? $matchingTrip['id'] : null,
                        'trip_title' => $matchingTrip ? $matchingTrip['title'] : null
                    ];
                }
            }
            
            $_SESSION['airbnb_preview'] = $previewData;
            $_SESSION['airbnb_csv'] = $csvData;
        }
        
    } elseif ($_POST['action'] === 'prepare_import') {
        // Prepare import data for AJAX processing
        header('Content-Type: application/json');
        
        $fallbackTripId = $_POST['trip_id'] ?? null;
        $createNewTrip = isset($_POST['create_new_trip']) && $_POST['create_new_trip'] === '1';
        $newTripTitle = $_POST['new_trip_title'] ?? 'Airbnb Trips';
        $useAutoDetect = isset($_POST['use_auto_detect']) && $_POST['use_auto_detect'] === '1';
        $selectedIndices = json_decode($_POST['selected_indices'] ?? '[]', true);
        
        if ($createNewTrip) {
            $fallbackTripId = $tripModel->create([
                'title' => $newTripTitle,
                'description' => 'Estadías importadas desde Airbnb',
                'color_hex' => '#FF5A5F',
                'status' => 'draft'
            ]);
            
            if (!$fallbackTripId) {
                echo json_encode(['error' => 'Error al crear el viaje']);
                exit;
            }
        }
        
        if (!isset($_SESSION['airbnb_preview'])) {
            echo json_encode(['error' => 'No hay datos de preview']);
            exit;
        }
        
        $previewData = $_SESSION['airbnb_preview'];
        $itemsToImport = [];
        
        foreach ($selectedIndices as $index) {
            if (!isset($previewData[$index])) continue;
            
            $row = $previewData[$index];
            $rowTripId = null;
            
            if ($useAutoDetect && !empty($row['trip_id'])) {
                $rowTripId = $row['trip_id'];
            } elseif ($fallbackTripId) {
                $rowTripId = $fallbackTripId;
            }
            
            if (!$rowTripId) continue;
            
            $itemsToImport[] = [
                'destination' => $row['destination'],
                'dates' => $row['dates'],
                'first_date' => $row['first_date'],
                'trip_id' => $rowTripId
            ];
        }
        
        // Clear session after preparing
        unset($_SESSION['airbnb_preview']);
        unset($_SESSION['airbnb_csv']);
        
        echo json_encode([
            'success' => true,
            'items' => $itemsToImport,
            'total' => count($itemsToImport)
        ]);
        exit;
    }
}

$previewData = $_SESSION['airbnb_preview'] ?? null;
$csvData = $_SESSION['airbnb_csv'] ?? '';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="h3">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" color="#ff385c" fill="none" stroke="#ff385c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="me-2">
                <path d="M12 18.7753C10.3443 16.7754 9 15.5355 9 13.5C9 11.4645 10.5033 10 12.0033 10C13.5033 10 15 11.4645 15 13.5C15 15.5355 13.6557 16.7754 12 18.7753ZM12 18.7753C10 21.3198 6.02071 21.4621 4.34969 20.302C2.67867 19.1419 2.65485 16.7398 3.75428 14.1954C4.85371 11.651 6.31925 8.5977 9.25143 4.52665C10.2123 3.45799 10.8973 3 11.9967 3M12 18.7753C14 21.3198 17.9793 21.4621 19.6503 20.302C21.3213 19.1419 21.3451 16.7398 20.2457 14.1954C19.1463 11.651 17.6807 8.5977 14.7486 4.52665C13.7877 3.45799 13.1027 3 12.0033 3" />
            </svg>
            Importar Estadías de Airbnb
        </h1>
        <p class="text-muted">
            Importa tus estadías desde el CSV exportado de Airbnb. Se creará un punto de interés tipo "Alojamiento" para cada destino.
        </p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($importResults)): ?>
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-list-check"></i> Resultados de la Importación</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Destino</th>
                            <th>Estado</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($importResults as $result): ?>
                            <tr class="<?= $result['status'] === 'success' ? 'table-success' : 'table-danger' ?>">
                                <td><?= htmlspecialchars($result['destination']) ?></td>
                                <td>
                                    <?php if ($result['status'] === 'success'): ?>
                                        <span class="badge bg-success">✓ Importado</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ Error</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($result['message']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($previewData === null): ?>
    <!-- Step 1: Paste CSV Data -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-1-circle me-2" viewBox="0 0 16 16">
                    <path d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0M9.283 4.002V12H7.971V5.338h-.065L6.072 6.656V5.385l1.899-1.383z"/>
                </svg>
                Paso 1: Pegar datos CSV de Airbnb
            </h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="preview">
                
                <div class="mb-3">
                    <label for="csv_data" class="form-label">
                        Contenido del CSV
                        <small class="text-muted">(incluyendo la cabecera Destination,Dates)</small>
                    </label>
                    <textarea 
                        class="form-control font-monospace" 
                        id="csv_data" 
                        name="csv_data" 
                        rows="12"
                        placeholder='Destination,Dates
"Alicante","Nov 14 – 18, 2025"
"Skopje","Sep 19 – 24, 2025"
...'
                        required
                    ><?= htmlspecialchars($csvData) ?></textarea>
                </div>
                
                <div class="card bg-light border-info mb-3">
                    <div class="card-body">
                        <h6 class="card-title">Cómo obtener el CSV:</h6>
                        <ol class="mb-2 small">
                            <li>Ve a <a href="https://www.airbnb.com/users/profile/past-trips" target="_blank">airbnb.com/trips</a>. Asegurate de cargar todos los viajes previamente.</li>
                            <li>Abre la consola del navegador (F12 → Console)</li>
                            <li>
                                Pega el script de extracción:
                                <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="copy-script-btn" title="Copiar script">
                                    <i class="bi bi-clipboard"></i> Copiar Script
                                </button>
                            </li>
                            <li>Haz clic en el botón rosa que aparece en la página</li>
                            <li>Pega aquí el CSV copiado</li>
                        </ol>
                        <details class="mt-2">
                            <summary class="small text-muted" style="cursor: pointer;">Ver código del script</summary>
                            <pre class="bg-dark text-light p-2 rounded mt-2 small" style="max-height: 200px; overflow: auto;" id="extraction-script">(function() {
  const btn = document.createElement('button');
  btn.textContent = '📋 Copy Trips CSV';
  btn.style.cssText = `position:fixed;top:20px;right:20px;z-index:99999;padding:12px 24px;background:#FF385C;color:white;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.2);`;
  document.body.appendChild(btn);

  btn.addEventListener('click', () => {
    // Mobile selectors
    const destM = '.t19lfnmc.atm_9s_1ulexfb.t13cgbdw.atm_c8_1h3mmnw.atm_fr_b3emyl.atm_7l_hfv0h6.atm_cs_tj69uu.atm_g3_f6fqlb.atm_ti_150s2i2.dir.dir-ltr';
    const dateM = '.s1ejsf9d.atm_9s_1ulexfb.atm_fr_b3emyl.s1p4un0a.atm_gi_idpfg4.atm_c8_1gcojkr.atm_g3_exct8b.atm_cs_1dh25pa.atm_7l_xeyu1p.dir.dir-ltr';
    // Desktop selectors
    const destD = '.t1q95j6x.atm_c8_cvmmj6.atm_g3_1obqfcl.atm_fr_frkw1s.atm_cs_ml5b3k.atm_ti_150s2i2.atm_lo_1gqzj4n.dir.dir-ltr';
    const dateD = '.scurj2v.atm_lo_1l7b3ar.atm_c8_1gcojkr.atm_g3_exct8b.atm_cs_1dh25pa.atm_7l_xeyu1p.dir.dir-ltr';

    let dests = document.querySelectorAll(destM);
    let dates = document.querySelectorAll(dateM);
    if (dests.length === 0) { dests = document.querySelectorAll(destD); dates = document.querySelectorAll(dateD); }

    const trips = [];
    for (let i = 0; i < Math.min(dests.length, dates.length); i++) {
      const dest = dests[i]?.textContent?.trim() || '';
      const dt = dates[i]?.textContent?.trim() || '';
      if (dest || dt) trips.push({ destination: dest, dates: dt });
    }

    let csv = 'Destination,Dates\n';
    trips.forEach(t => csv += `"${t.destination.replace(/"/g,'""')}","${t.dates.replace(/"/g,'""')}"\n`);

    navigator.clipboard.writeText(csv).then(() => {
      btn.textContent = `✅ ${trips.length} copied!`;
      btn.style.background = '#00A699';
      setTimeout(() => { btn.textContent = '📋 Copy Trips CSV'; btn.style.background = '#FF385C'; }, 2000);
    });
  });
})();</pre>
                        </details>
                    </div>
                </div>
                
                <script>
                document.getElementById('copy-script-btn')?.addEventListener('click', function() {
                    const script = document.getElementById('extraction-script').textContent;
                    navigator.clipboard.writeText(script).then(() => {
                        this.innerHTML = '<i class="bi bi-check"></i> ¡Copiado!';
                        this.classList.remove('btn-outline-primary');
                        this.classList.add('btn-success');
                        setTimeout(() => {
                            this.innerHTML = '<i class="bi bi-clipboard"></i> Copiar Script';
                            this.classList.remove('btn-success');
                            this.classList.add('btn-outline-primary');
                        }, 2000);
                    });
                });
                </script>
                
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-eye"></i> Vista Previa
                </button>
            </form>
        </div>
    </div>
    
<?php else: ?>
    <!-- Step 2: Preview and Import -->
    <form method="POST">
        <input type="hidden" name="action" value="import">
        
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-2-circle me-2" viewBox="0 0 16 16">
                        <path d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0M6.646 6.24v.07H5.375v-.064c0-1.213.879-2.402 2.637-2.402 1.582 0 2.613.949 2.613 2.215 0 1.002-.6 1.667-1.287 2.43l-.096.107-1.974 2.22v.077h3.498V12H5.422v-.832l2.97-3.293c.434-.475.903-1.008.903-1.705 0-.744-.557-1.236-1.313-1.236-.843 0-1.336.615-1.336 1.306"/>
                    </svg>
                    Paso 2: Seleccionar Viaje y Confirmar Importación
                </h5>
            </div>
            <div class="card-body">
                <?php
                // Count how many have auto-detected trips
                $autoDetectedCount = count(array_filter($previewData, fn($r) => !empty($r['trip_id'])));
                ?>
                
                <?php if ($autoDetectedCount > 0): ?>
                <div class="alert alert-success mb-4">
                    <i class="bi bi-check-circle"></i>
                    <strong><?= $autoDetectedCount ?> de <?= count($previewData) ?></strong> estadías tienen un viaje detectado automáticamente por fecha.
                </div>
                
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="use_auto_detect" value="1" id="use_auto_detect" checked>
                            <label class="form-check-label fw-bold" for="use_auto_detect">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#ff385c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                                    <path d="M12 18.7753C10.3443 16.7754 9 15.5355 9 13.5C9 11.4645 10.5033 10 12.0033 10C13.5033 10 15 11.4645 15 13.5C15 15.5355 13.6557 16.7754 12 18.7753ZM12 18.7753C10 21.3198 6.02071 21.4621 4.34969 20.302C2.67867 19.1419 2.65485 16.7398 3.75428 14.1954C4.85371 11.651 6.31925 8.5977 9.25143 4.52665C10.2123 3.45799 10.8973 3 11.9967 3M12 18.7753C14 21.3198 17.9793 21.4621 19.6503 20.302C21.3213 19.1419 21.3451 16.7398 20.2457 14.1954C19.1463 11.651 17.6807 8.5977 14.7486 4.52665C13.7877 3.45799 13.1027 3 12.0033 3" />
                                </svg>
                                Usar viajes detectados automáticamente por fecha
                            </label>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row mb-4" id="fallback-trip-section">
                    <div class="col-md-6">
                        <label class="form-label fw-bold"><?= $autoDetectedCount > 0 ? 'Viaje de respaldo (para estadías sin viaje detectado)' : 'Asignar a Viaje Existente' ?></label>
                        <select class="form-select" name="trip_id" id="trip_id">
                            <option value="">-- Seleccionar viaje --</option>
                            <?php foreach ($trips as $trip): ?>
                                <option value="<?= $trip['id'] ?>">
                                    <?= htmlspecialchars($trip['title']) ?>
                                    <?php if ($trip['start_date']): ?>
                                        (<?= date('d/m/Y', strtotime($trip['start_date'])) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">O Crear Nuevo Viaje</label>
                        <div class="input-group">
                            <div class="input-group-text">
                                <input type="checkbox" class="form-check-input mt-0" name="create_new_trip" value="1" id="create_new_trip">
                            </div>
                            <input type="text" class="form-control" name="new_trip_title" id="new_trip_title" 
                                   value="Estadías Airbnb" placeholder="Nombre del nuevo viaje" disabled>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning mb-4">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Nota:</strong> La geocodificación usa OpenStreetMap (Nominatim) y tiene un límite de 1 petición por segundo. 
                    La importación puede tardar varios minutos dependiendo del número de destinos.
                </div>
                
                <h6 class="mb-3">Vista previa: <?= count($previewData) ?> estadías encontradas</h6>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="select_all" checked>
                                    </div>
                                </th>
                                <th>Destino</th>
                                <th>Fechas Originales</th>
                                <th>Fecha de Visita</th>
                                <th>Viaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewData as $index => $row): ?>
                                <tr data-index="<?= $index ?>" 
                                    data-destination="<?= htmlspecialchars($row['destination']) ?>"
                                    data-dates="<?= htmlspecialchars($row['dates']) ?>"
                                    data-first-date="<?= htmlspecialchars($row['first_date'] ?? '') ?>"
                                    data-auto-trip-id="<?= htmlspecialchars($row['trip_id'] ?? '') ?>">
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input row-check" type="checkbox" 
                                                   name="import_<?= $index ?>" value="1" checked>
                                        </div>
                                    </td>
                                    <td>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#ff385c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                                            <path d="M12 18.7753C10.3443 16.7754 9 15.5355 9 13.5C9 11.4645 10.5033 10 12.0033 10C13.5033 10 15 11.4645 15 13.5C15 15.5355 13.6557 16.7754 12 18.7753ZM12 18.7753C10 21.3198 6.02071 21.4621 4.34969 20.302C2.67867 19.1419 2.65485 16.7398 3.75428 14.1954C4.85371 11.651 6.31925 8.5977 9.25143 4.52665C10.2123 3.45799 10.8973 3 11.9967 3M12 18.7753C14 21.3198 17.9793 21.4621 19.6503 20.302C21.3213 19.1419 21.3451 16.7398 20.2457 14.1954C19.1463 11.651 17.6807 8.5977 14.7486 4.52665C13.7877 3.45799 13.1027 3 12.0033 3" />
                                        </svg>
                                        <strong><?= htmlspecialchars($row['destination']) ?></strong>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($row['dates']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($row['first_date']): ?>
                                            <span class="badge bg-success"><?= $row['first_date_formatted'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">No detectada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['trip_id'])): ?>
                                            <span class="badge bg-primary"><?= htmlspecialchars($row['trip_title']) ?></span>
                                        <?php else: ?>
                                            <select class="form-select form-select-sm row-trip-select" style="min-width: 150px;">
                                                <option value="">-- Seleccionar --</option>
                                                <?php foreach ($trips as $trip): ?>
                                                    <option value="<?= $trip['id'] ?>">
                                                        <?= htmlspecialchars($trip['title']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="?clear=1" class="btn btn-outline-secondary" id="btn-back">
                        <i class="bi bi-arrow-counterclockwise"></i> Empezar de nuevo
                    </a>
                    <button type="button" class="btn btn-success btn-lg" id="btn-import">
                        <i class="bi bi-cloud-upload"></i> Importar Estadías Seleccionadas
                    </button>
                </div>
            </div>
            
            <!-- Progress section (hidden by default) -->
            <div class="card-body d-none" id="progress-section">
                <h5 class="mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#ff385c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="me-2">
                        <path d="M12 18.7753C10.3443 16.7754 9 15.5355 9 13.5C9 11.4645 10.5033 10 12.0033 10C13.5033 10 15 11.4645 15 13.5C15 15.5355 13.6557 16.7754 12 18.7753ZM12 18.7753C10 21.3198 6.02071 21.4621 4.34969 20.302C2.67867 19.1419 2.65485 16.7398 3.75428 14.1954C4.85371 11.651 6.31925 8.5977 9.25143 4.52665C10.2123 3.45799 10.8973 3 11.9967 3M12 18.7753C14 21.3198 17.9793 21.4621 19.6503 20.302C21.3213 19.1419 21.3451 16.7398 20.2457 14.1954C19.1463 11.651 17.6807 8.5977 14.7486 4.52665C13.7877 3.45799 13.1027 3 12.0033 3" />
                    </svg>
                    Importando estadías...
                </h5>
                <div class="progress mb-3" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                         role="progressbar" 
                         id="import-progress" 
                         style="width: 0%">
                        0%
                    </div>
                </div>
                <p class="mb-2"><strong>Procesando:</strong> <span id="current-destination">-</span></p>
                <p class="text-muted small mb-3">
                    <span id="import-stats">0 / 0</span> procesados 
                    (<span id="success-count" class="text-success">0</span> nuevos, 
                    <span id="skip-count" class="text-warning">0</span> duplicados,
                    <span id="fail-count" class="text-danger">0</span> fallidos)
                </p>
                <div id="import-log" class="border rounded p-2 bg-light" style="max-height: 200px; overflow-y: auto; font-size: 0.85em;">
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle new trip input based on checkbox
    const createNewTripCheckbox = document.getElementById('create_new_trip');
    const newTripTitleInput = document.getElementById('new_trip_title');
    const tripIdSelect = document.getElementById('trip_id');
    
    if (createNewTripCheckbox) {
        createNewTripCheckbox.addEventListener('change', function() {
            newTripTitleInput.disabled = !this.checked;
            if (this.checked) {
                tripIdSelect.value = '';
                tripIdSelect.disabled = true;
            } else {
                tripIdSelect.disabled = false;
            }
        });
    }
    
    // Select all checkbox
    const selectAll = document.getElementById('select_all');
    const rowChecks = document.querySelectorAll('.row-check');
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rowChecks.forEach(check => {
                check.checked = this.checked;
            });
        });
        
        rowChecks.forEach(check => {
            check.addEventListener('change', function() {
                const allChecked = Array.from(rowChecks).every(c => c.checked);
                const someChecked = Array.from(rowChecks).some(c => c.checked);
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            });
        });
    }
    
    // AJAX Import with progress bar
    const btnImport = document.getElementById('btn-import');
    const progressSection = document.getElementById('progress-section');
    const progressBar = document.getElementById('import-progress');
    const currentDest = document.getElementById('current-destination');
    const importStats = document.getElementById('import-stats');
    const successCount = document.getElementById('success-count');
    const skipCount = document.getElementById('skip-count');
    const failCount = document.getElementById('fail-count');
    const importLog = document.getElementById('import-log');
    
    if (btnImport) {
        btnImport.addEventListener('click', async function() {
            // Build items from table rows directly
            const items = [];
            const fallbackTripId = tripIdSelect ? tripIdSelect.value : '';
            const useAutoDetect = document.getElementById('use_auto_detect');
            const useAuto = useAutoDetect && useAutoDetect.checked;
            
            document.querySelectorAll('tbody tr').forEach(row => {
                const checkbox = row.querySelector('.row-check');
                if (!checkbox || !checkbox.checked) return;
                
                const destination = row.dataset.destination;
                const dates = row.dataset.dates;
                const firstDate = row.dataset.firstDate || null;
                const autoTripId = row.dataset.autoTripId;
                
                // Determine trip: auto-detected, per-row dropdown, or fallback
                let tripId = null;
                
                if (useAuto && autoTripId) {
                    tripId = autoTripId;
                } else {
                    // Check for per-row dropdown
                    const rowSelect = row.querySelector('.row-trip-select');
                    if (rowSelect && rowSelect.value) {
                        tripId = rowSelect.value;
                    } else if (fallbackTripId) {
                        tripId = fallbackTripId;
                    }
                }
                
                if (tripId) {
                    items.push({ destination, dates, first_date: firstDate, trip_id: tripId });
                }
            });
            
            if (items.length === 0) {
                alert('No hay estadías con viaje asignado para importar. Selecciona un viaje para cada fila o usa el viaje de respaldo.');
                return;
            }
            
            // Hide form, show progress
            document.querySelector('.table-responsive').classList.add('d-none');
            document.querySelector('.card-footer').classList.add('d-none');
            const fallbackSection = document.getElementById('fallback-trip-section');
            if (fallbackSection) fallbackSection.classList.add('d-none');
            const autoAlert = document.querySelector('.alert-success');
            if (autoAlert) autoAlert.classList.add('d-none');
            progressSection.classList.remove('d-none');
            
            const total = items.length;
            let success = 0;
            let failed = 0;
            let skipped = 0;
            
            // Process each item with delay
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                currentDest.textContent = item.destination;
                
                try {
                    const response = await fetch('<?= BASE_URL ?>/api/import_airbnb_point.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(item)
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        if (result.skipped) {
                            skipped++;
                            addLog(`⊘ ${item.destination}: ${result.message}`, 'warning');
                        } else {
                            success++;
                            addLog(`✓ ${item.destination}`, 'success');
                        }
                    } else {
                        failed++;
                        addLog(`✗ ${item.destination}: ${result.message}`, 'danger');
                    }
                } catch (e) {
                    failed++;
                    addLog(`✗ ${item.destination}: Error de red`, 'danger');
                }
                
                // Update progress
                const pct = Math.round(((i + 1) / total) * 100);
                progressBar.style.width = pct + '%';
                progressBar.textContent = pct + '%';
                importStats.textContent = `${i + 1} / ${total}`;
                successCount.textContent = success;
                skipCount.textContent = skipped;
                failCount.textContent = failed;
                
                // Wait 1.2 seconds between requests (Nominatim rate limit)
                if (i < items.length - 1) {
                    await sleep(1200);
                }
            }
            
            // Done
            progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
            currentDest.textContent = '¡Completado!';
            const summary = `📊 Resumen: ${success} importados, ${skipped} duplicados, ${failed} fallidos`;
            addLog(summary, 'info');
            
            // Show link to points
            const doneHtml = `
                <div class="mt-3">
                    <a href="points.php" class="btn btn-primary">
                        <i class="bi bi-geo-alt"></i> Ver Puntos de Interés
                    </a>
                    <a href="import_airbnb.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-arrow-repeat"></i> Importar más
                    </a>
                </div>
            `;
            progressSection.insertAdjacentHTML('beforeend', doneHtml);
        });
    }
    
    function addLog(message, type) {
        const line = document.createElement('div');
        line.className = `text-${type}`;
        line.textContent = message;
        importLog.appendChild(line);
        importLog.scrollTop = importLog.scrollHeight;
    }
    
    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
