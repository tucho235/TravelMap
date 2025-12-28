<?php
/**
 * Gestión de Configuraciones
 * 
 * Permite configurar opciones globales del sistema
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/models/Settings.php';

// Obtener conexión a la base de datos
$conn = getDB();
$settingsModel = new Settings($conn);

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $updates = [];
        
        // Configuraciones generales
        if (isset($_POST['max_upload_size'])) {
            $updates['max_upload_size'] = [
                'value' => (int)$_POST['max_upload_size'],
                'type' => 'number'
            ];
        }
        
        if (isset($_POST['session_lifetime'])) {
            $updates['session_lifetime'] = [
                'value' => (int)$_POST['session_lifetime'],
                'type' => 'number'
            ];
        }
        
        if (isset($_POST['timezone'])) {
            $updates['timezone'] = [
                'value' => $_POST['timezone'],
                'type' => 'string'
            ];
        }
        
        // Configuraciones del mapa
        if (isset($_POST['map_cluster_enabled'])) {
            $updates['map_cluster_enabled'] = [
                'value' => $_POST['map_cluster_enabled'] === '1',
                'type' => 'boolean'
            ];
        }
        
        if (isset($_POST['map_cluster_max_radius'])) {
            $updates['map_cluster_max_radius'] = [
                'value' => (int)$_POST['map_cluster_max_radius'],
                'type' => 'number'
            ];
        }
        
        if (isset($_POST['map_cluster_disable_at_zoom'])) {
            $updates['map_cluster_disable_at_zoom'] = [
                'value' => (int)$_POST['map_cluster_disable_at_zoom'],
                'type' => 'number'
            ];
        }
        
        // Colores de transporte
        $transportTypes = ['plane', 'ship', 'car', 'train', 'walk'];
        foreach ($transportTypes as $type) {
            $key = 'transport_color_' . $type;
            if (isset($_POST[$key])) {
                $updates[$key] = [
                    'value' => $_POST[$key],
                    'type' => 'string'
                ];
            }
        }
        
        // Actualizar todas las configuraciones
        if ($settingsModel->updateMultiple($updates)) {
            $_SESSION['success_message'] = 'Configuración actualizada correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al actualizar la configuración';
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
    
    header('Location: settings.php');
    exit;
}

// Obtener configuraciones actuales
$currentSettings = $settingsModel->getAllAsArray();

// Zonas horarias comunes
$timezones = [
    'America/Argentina/Buenos_Aires' => 'Buenos Aires (GMT-3)',
    'America/Mexico_City' => 'Ciudad de México (GMT-6)',
    'America/Bogota' => 'Bogotá (GMT-5)',
    'America/Lima' => 'Lima (GMT-5)',
    'America/Santiago' => 'Santiago (GMT-3)',
    'America/Sao_Paulo' => 'São Paulo (GMT-3)',
    'America/New_York' => 'Nueva York (GMT-5)',
    'America/Chicago' => 'Chicago (GMT-6)',
    'America/Los_Angeles' => 'Los Ángeles (GMT-8)',
    'Europe/Madrid' => 'Madrid (GMT+1)',
    'Europe/London' => 'Londres (GMT+0)',
    'Europe/Paris' => 'París (GMT+1)',
    'Europe/Berlin' => 'Berlín (GMT+1)',
    'Europe/Rome' => 'Roma (GMT+1)',
    'Asia/Tokyo' => 'Tokio (GMT+9)',
    'Asia/Shanghai' => 'Shanghái (GMT+8)',
    'Asia/Dubai' => 'Dubái (GMT+4)',
    'Australia/Sydney' => 'Sídney (GMT+11)',
    'Pacific/Auckland' => 'Auckland (GMT+13)',
    'UTC' => 'UTC (GMT+0)'
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="h3">
            <i class="bi bi-gear"></i> Configuración del Sistema
        </h1>
        <p class="text-muted">Gestiona las opciones globales de la aplicación</p>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['error_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<form method="POST" action="settings.php">
    <input type="hidden" name="action" value="update">
    
    <!-- Configuraciones Generales -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-sliders"></i> Configuración General</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="max_upload_size" class="form-label">
                        Tamaño Máximo de Carga (MB)
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="max_upload_size" 
                        name="max_upload_size" 
                        value="<?= htmlspecialchars(round(($currentSettings['max_upload_size'] ?? 8388608) / 1048576, 2)) ?>"
                        min="1" 
                        max="100"
                        step="0.1"
                        required
                    >
                    <small class="form-text text-muted">
                        Tamaño máximo permitido para subir imágenes
                    </small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="session_lifetime" class="form-label">
                        Duración de Sesión (horas)
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="session_lifetime" 
                        name="session_lifetime" 
                        value="<?= htmlspecialchars(round(($currentSettings['session_lifetime'] ?? 86400) / 3600, 1)) ?>"
                        min="1" 
                        max="720"
                        step="0.5"
                        required
                    >
                    <small class="form-text text-muted">
                        Tiempo que permanecerá activa una sesión de usuario
                    </small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="timezone" class="form-label">
                        Zona Horaria
                    </label>
                    <select class="form-select" id="timezone" name="timezone" required>
                        <?php 
                        $currentTimezone = $currentSettings['timezone'] ?? 'America/Argentina/Buenos_Aires';
                        foreach ($timezones as $value => $label): 
                        ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= $value === $currentTimezone ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">
                        Zona horaria utilizada para fechas y horas del sistema
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Configuraciones del Mapa -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-map"></i> Configuración del Mapa</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="form-check form-switch">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            id="map_cluster_enabled" 
                            name="map_cluster_enabled" 
                            value="1"
                            <?= ($currentSettings['map_cluster_enabled'] ?? true) ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="map_cluster_enabled">
                            Habilitar Agrupación de Puntos (Clustering)
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        Agrupa puntos cercanos en clusters cuando hay muchos marcadores
                    </small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="map_cluster_max_radius" class="form-label">
                        Radio Máximo del Cluster (píxeles)
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="map_cluster_max_radius" 
                        name="map_cluster_max_radius" 
                        value="<?= htmlspecialchars($currentSettings['map_cluster_max_radius'] ?? 30) ?>"
                        min="10" 
                        max="200"
                        required
                    >
                    <small class="form-text text-muted">
                        Distancia máxima en píxeles para agrupar puntos
                    </small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="map_cluster_disable_at_zoom" class="form-label">
                        Desactivar Clustering en Zoom
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="map_cluster_disable_at_zoom" 
                        name="map_cluster_disable_at_zoom" 
                        value="<?= htmlspecialchars($currentSettings['map_cluster_disable_at_zoom'] ?? 15) ?>"
                        min="1" 
                        max="20"
                        required
                    >
                    <small class="form-text text-muted">
                        Nivel de zoom donde se mostrarán todos los puntos individuales
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Colores de Transporte -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-palette"></i> Colores de Rutas por Tipo de Transporte</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="transport_color_plane" class="form-label">
                        ✈️ Avión
                    </label>
                    <div class="input-group">
                        <input 
                            type="color" 
                            class="form-control form-control-color" 
                            id="transport_color_plane" 
                            name="transport_color_plane" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_plane'] ?? '#FF4444') ?>"
                            title="Color para rutas en avión"
                        >
                        <input 
                            type="text" 
                            class="form-control" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_plane'] ?? '#FF4444') ?>"
                            readonly
                        >
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="transport_color_ship" class="form-label">
                        🚢 Barco
                    </label>
                    <div class="input-group">
                        <input 
                            type="color" 
                            class="form-control form-control-color" 
                            id="transport_color_ship" 
                            name="transport_color_ship" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_ship'] ?? '#00AAAA') ?>"
                            title="Color para rutas en barco"
                        >
                        <input 
                            type="text" 
                            class="form-control" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_ship'] ?? '#00AAAA') ?>"
                            readonly
                        >
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="transport_color_car" class="form-label">
                        🚗 Auto
                    </label>
                    <div class="input-group">
                        <input 
                            type="color" 
                            class="form-control form-control-color" 
                            id="transport_color_car" 
                            name="transport_color_car" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_car'] ?? '#4444FF') ?>"
                            title="Color para rutas en auto"
                        >
                        <input 
                            type="text" 
                            class="form-control" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_car'] ?? '#4444FF') ?>"
                            readonly
                        >
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="transport_color_train" class="form-label">
                        🚂 Tren
                    </label>
                    <div class="input-group">
                        <input 
                            type="color" 
                            class="form-control form-control-color" 
                            id="transport_color_train" 
                            name="transport_color_train" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_train'] ?? '#FF8800') ?>"
                            title="Color para rutas en tren"
                        >
                        <input 
                            type="text" 
                            class="form-control" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_train'] ?? '#FF8800') ?>"
                            readonly
                        >
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="transport_color_walk" class="form-label">
                        🚶 Caminando
                    </label>
                    <div class="input-group">
                        <input 
                            type="color" 
                            class="form-control form-control-color" 
                            id="transport_color_walk" 
                            name="transport_color_walk" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_walk'] ?? '#44FF44') ?>"
                            title="Color para rutas caminando"
                        >
                        <input 
                            type="text" 
                            class="form-control" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_walk'] ?? '#44FF44') ?>"
                            readonly
                        >
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle"></i> 
                <strong>Nota:</strong> Los cambios en los colores se aplicarán a las rutas existentes cuando se recargue el mapa público.
            </div>
        </div>
    </div>
    
    <!-- Botones de acción -->
    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> Guardar Configuración
        </button>
    </div>
</form>

<script>
// Sincronizar los selectores de color con los inputs de texto
document.querySelectorAll('input[type="color"]').forEach(colorInput => {
    const textInput = colorInput.nextElementSibling;
    
    colorInput.addEventListener('input', function() {
        textInput.value = this.value.toUpperCase();
    });
});

// Actualizar el valor en bytes cuando cambia el tamaño en MB
document.getElementById('max_upload_size').addEventListener('change', function() {
    const mb = parseFloat(this.value);
    const bytes = Math.round(mb * 1048576);
    // Crear un campo oculto con el valor en bytes
    let hiddenInput = document.getElementById('max_upload_size_bytes');
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'max_upload_size_bytes';
        hiddenInput.name = 'max_upload_size';
        this.parentNode.appendChild(hiddenInput);
        this.removeAttribute('name'); // Remover el name del input visible
    }
    hiddenInput.value = bytes;
});

// Actualizar el valor en segundos cuando cambia la duración en horas
document.getElementById('session_lifetime').addEventListener('change', function() {
    const hours = parseFloat(this.value);
    const seconds = Math.round(hours * 3600);
    // Crear un campo oculto con el valor en segundos
    let hiddenInput = document.getElementById('session_lifetime_seconds');
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'session_lifetime_seconds';
        hiddenInput.name = 'session_lifetime';
        this.parentNode.appendChild(hiddenInput);
        this.removeAttribute('name'); // Remover el name del input visible
    }
    hiddenInput.value = seconds;
});

// Inicializar los valores al cargar la página
document.getElementById('max_upload_size').dispatchEvent(new Event('change'));
document.getElementById('session_lifetime').dispatchEvent(new Event('change'));
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
