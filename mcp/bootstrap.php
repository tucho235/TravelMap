<?php
/**
 * MCP Server Bootstrap
 *
 * Carga configuración, base de datos, helpers y modelos necesarios.
 * Configura error handler seguro (nunca expone stack traces al cliente).
 */

// Apagar output de errores (stdout es estrictamente JSON-RPC)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Definir ROOT_PATH si no está definido (cuando se ejecuta via CLI)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Cargar configuración central (define constantes, timezone, etc.)
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/db.php';

// Helpers
require_once ROOT_PATH . '/src/helpers/FileHelper.php';
require_once ROOT_PATH . '/src/helpers/BRouterParser.php';
require_once ROOT_PATH . '/src/helpers/ExifExtractor.php';
require_once ROOT_PATH . '/src/helpers/Geocoder.php';

// Modelos
require_once ROOT_PATH . '/src/models/Settings.php';
require_once ROOT_PATH . '/src/models/Trip.php';
require_once ROOT_PATH . '/src/models/Route.php';
require_once ROOT_PATH . '/src/models/Point.php';
require_once ROOT_PATH . '/src/models/TripTag.php';
require_once ROOT_PATH . '/src/models/Link.php';

// Asegurar carpeta de logs
$logDir = ROOT_PATH . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}
