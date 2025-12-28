<?php
/**
 * Configuración Global del Proyecto
 * 
 * Define constantes para rutas y URLs utilizadas en toda la aplicación
 */

// Configuración de errores (en producción cambiar a 0)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ruta raíz del proyecto (ajustar según tu instalación)
define('ROOT_PATH', dirname(__DIR__));

// URL base del proyecto (ajustar según tu servidor)
// Para XAMPP típicamente es: http://localhost/TravelMap
// Detectar automáticamente o configurar manualmente
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$folder = '/TravelMap'; // Cambiar si tu carpeta tiene otro nombre

define('BASE_URL', $protocol . '://' . $host . $folder);

// Rutas de directorios importantes
define('CONFIG_PATH', ROOT_PATH . '/config');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('SRC_PATH', ROOT_PATH . '/src');

// URLs de recursos
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// Valores por defecto para configuraciones
$defaultTimezone = 'America/Argentina/Buenos_Aires';
$defaultMaxUploadSize = 8 * 1024 * 1024; // 8MB
$defaultSessionLifetime = 3600 * 24; // 24 horas

// Intentar cargar configuraciones dinámicas desde la base de datos
try {
    require_once CONFIG_PATH . '/db.php';
    require_once SRC_PATH . '/models/Settings.php';
    
    // Obtener conexión a la base de datos
    $conn = getDB();
    
    // Inicializar el modelo de configuraciones
    $settingsModel = new Settings($conn);
    
    // Obtener configuraciones de la base de datos
    $timezone = $settingsModel->get('timezone', $defaultTimezone);
    $maxUploadSize = $settingsModel->get('max_upload_size', $defaultMaxUploadSize);
    $sessionLifetime = $settingsModel->get('session_lifetime', $defaultSessionLifetime);
} catch (Exception $e) {
    // Si hay algún error al cargar configuraciones, usar valores por defecto
    error_log('Error al cargar configuraciones: ' . $e->getMessage());
    $timezone = $defaultTimezone;
    $maxUploadSize = $defaultMaxUploadSize;
    $sessionLifetime = $defaultSessionLifetime;
}

// Aplicar configuraciones
date_default_timezone_set($timezone);

// Configuración de archivos
define('MAX_UPLOAD_SIZE', $maxUploadSize);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Configuración de sesión
define('SESSION_LIFETIME', $sessionLifetime);

