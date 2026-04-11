#!/usr/bin/env php
<?php
/**
 * travelmap.php — CLI principal de TravelMap
 *
 * SEGURIDAD:
 *   - Solo funciona desde CLI (PHP_SAPI === 'cli').
 *   - No usa $_GET, $_POST, $_REQUEST ni ninguna variable HTTP.
 *   - El directorio bin/ tiene .htaccess que deniega todo acceso web.
 *   - Aplicar permisos tras instalar:
 *       chmod 0750 bin/
 *       chmod 0700 bin/travelmap.php
 *     Con eso www-data no puede leer este archivo aunque .htaccess falle.
 *
 * USO:
 *   php bin/travelmap.php <modulo> <operacion> [opciones]
 *   php bin/travelmap.php help
 *
 * MÓDULOS DISPONIBLES:
 *   backup   Gestión de backups (create, list)
 *
 * Ver docs/BACKUP_CLI.md para ejemplos de cron y configuración de nginx.
 */

// ── Capa 1: solo CLI ─────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    if (function_exists('http_response_code')) {
        http_response_code(403);
    }
    exit('Forbidden' . PHP_EOL);
}

// ── Capa 2: umask restrictivo para los archivos que crearemos ────────────────
umask(0077);

// ── Parseo de argumentos ─────────────────────────────────────────────────────
$args      = array_slice($argv, 1);
$module    = $args[0] ?? 'help';
$operation = $args[1] ?? 'help';

/**
 * Parsea flags del tipo --nombre o --nombre=valor desde $args.
 * Ignora los dos primeros elementos (modulo y operacion).
 * Devuelve ['flags' => [...], 'values' => ['key' => 'val', ...]].
 */
function parseArgs(array $args): array
{
    $flags  = [];
    $values = [];
    foreach (array_slice($args, 2) as $arg) {
        if (strpos($arg, '--') !== 0) {
            continue;
        }
        $arg = substr($arg, 2);
        if (strpos($arg, '=') !== false) {
            [$key, $val] = explode('=', $arg, 2);
            $values[$key] = $val;
        } else {
            $flags[] = $arg;
        }
    }
    return ['flags' => $flags, 'values' => $values];
}

/**
 * Imprime en stderr y termina con código de error.
 */
function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, '[ERROR] ' . $message . PHP_EOL);
    exit($code);
}

/**
 * Imprime en stdout.
 */
function info(string $message): void
{
    echo $message . PHP_EOL;
}

// ── Despacho anticipado: help no requiere DB ─────────────────────────────────
if ($module === 'help') {
    cmdHelp();
    exit(0);
}

// ── Bootstrap (sin includes/auth.php — emite header() y mataría el CLI) ──────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// ── Despacho de módulos ──────────────────────────────────────────────────────
match ($module) {
    'backup' => dispatchBackup($operation, $args),
    default  => (function () use ($module) {
        fwrite(STDERR, '[ERROR] Módulo desconocido: ' . $module . PHP_EOL);
        cmdHelp();
        exit(1);
    })(),
};

exit(0);

// ── Módulo: backup ───────────────────────────────────────────────────────────

function dispatchBackup(string $operation, array $args): void
{
    require_once __DIR__ . '/../src/helpers/BackupBuilder.php';

    match ($operation) {
        'create' => backupCreate($args),
        'list'   => backupList(),
        default  => (function () use ($operation) {
            fwrite(STDERR, '[ERROR] Operación desconocida para backup: ' . $operation . PHP_EOL);
            cmdHelpBackup();
            exit(1);
        })(),
    };
}

function backupCreate(array $args): void
{
    $parsed    = parseArgs($args);
    $flags     = $parsed['flags'];
    $values    = $parsed['values'];
    $backupDir = ROOT_PATH . '/backups';

    // --output=<ruta>
    if (isset($values['output'])) {
        $requestedDir = $values['output'];
        $parentDir    = is_dir($requestedDir) ? $requestedDir : dirname($requestedDir);
        $resolvedParent = realpath($parentDir);
        if ($resolvedParent === false) {
            fail("--output: el directorio padre no existe o no es accesible: {$parentDir}");
        }
        $backupDir = is_dir($requestedDir)
            ? $resolvedParent
            : $resolvedParent . '/' . basename($requestedDir);
    }

    // --no-images
    $includeImages = !in_array('no-images', $flags, true);

    // --only=trips,routes,points,tags,settings
    $validSections  = ['trips', 'routes', 'points', 'tags', 'settings'];
    $activeSections = $validSections;
    if (isset($values['only'])) {
        $requested = array_map('trim', explode(',', $values['only']));
        $unknown   = array_diff($requested, $validSections);
        if ($unknown) {
            fail('Secciones desconocidas en --only: ' . implode(', ', $unknown) .
                 '. Válidas: ' . implode(', ', $validSections));
        }
        $activeSections = $requested;
    }

    try {
        $db      = getDB();
        $version = defined('APP_VERSION') ? APP_VERSION : '1.0.0';

        $versionFile = ROOT_PATH . '/version.php';
        if (is_file($versionFile) && !isset($version)) {
            require $versionFile;
        }

        $builder = new BackupBuilder($db, ROOT_PATH, $version ?? '1.0.0');

        $path = $builder->create([
            'include_trips'    => in_array('trips',    $activeSections, true),
            'include_routes'   => in_array('routes',   $activeSections, true),
            'include_points'   => in_array('points',   $activeSections, true),
            'include_tags'     => in_array('tags',     $activeSections, true),
            'include_settings' => in_array('settings', $activeSections, true),
            'include_images'   => $includeImages,
            'output_dir'       => $backupDir,
        ]);

        $size = filesize($path);
        info('[OK] Backup creado: ' . $path);
        info('     Tamaño: ' . formatBytes($size));
        info('     Tipo:   ' . strtoupper(pathinfo($path, PATHINFO_EXTENSION)));

    } catch (\Exception $e) {
        error_log('travelmap backup create: ' . $e->getMessage());
        fail('No se pudo crear el backup: ' . $e->getMessage());
    }
}

function backupList(): void
{
    $backupDir = ROOT_PATH . '/backups';

    if (!is_dir($backupDir)) {
        info('No existe el directorio de backups: ' . $backupDir);
        return;
    }

    $files = glob($backupDir . '/*.{json,zip}', GLOB_BRACE) ?: [];
    if (!$files) {
        info('No hay backups en ' . $backupDir);
        return;
    }

    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

    info(str_pad('FECHA', 20) . str_pad('TIPO', 6) . str_pad('TAMAÑO', 12) . 'ARCHIVO');
    info(str_repeat('-', 72));
    foreach ($files as $file) {
        $date     = date('Y-m-d H:i:s', filemtime($file));
        $type     = strtoupper(pathinfo($file, PATHINFO_EXTENSION));
        $size     = formatBytes(filesize($file));
        $filename = basename($file);
        info(str_pad($date, 20) . str_pad($type, 6) . str_pad($size, 12) . $filename);
    }
    info('');
    info('Total: ' . count($files) . ' backup(s)');
}

// ── Ayuda ────────────────────────────────────────────────────────────────────

function cmdHelp(): void
{
    $script = basename(__FILE__);
    echo <<<HELP
    TravelMap CLI

    USO:
      php bin/{$script} <modulo> <operacion> [opciones]
      php bin/{$script} help

    MÓDULOS:
      backup   Gestión de backups

    Para ayuda de un módulo específico:
      php bin/{$script} backup help

    HELP;
}

function cmdHelpBackup(): void
{
    $script = basename(__FILE__);
    echo <<<HELP
    TravelMap CLI — módulo backup

    USO:
      php bin/{$script} backup <operacion> [opciones]

    OPERACIONES:
      create            Crea un nuevo backup (por defecto: todos los datos + imágenes en ZIP)
      list              Lista los backups existentes en ROOT_PATH/backups

    OPCIONES DE create:
      --no-images       Excluye imágenes; produce un archivo JSON en lugar de ZIP
      --only=<lista>    Incluye solo las secciones indicadas (separadas por coma):
                          trips, routes, points, tags, settings
      --output=<ruta>   Directorio destino (default: ROOT_PATH/backups)

    EJEMPLOS:
      # Backup completo (datos + imágenes)
      php bin/{$script} backup create

      # Solo datos, sin imágenes
      php bin/{$script} backup create --no-images

      # Solo viajes y rutas
      php bin/{$script} backup create --no-images --only=trips,routes

      # Backup con destino personalizado
      php bin/{$script} backup create --output=/mnt/nas/travelmap

      # Listar backups existentes
      php bin/{$script} backup list

    CRON (ejemplo semanal — domingos 03:00):
      0 3 * * 0  /usr/bin/php /ruta/a/TravelMap/bin/{$script} backup create >> /var/log/travelmap.log 2>&1

    Ver docs/BACKUP_CLI.md para más detalles sobre seguridad y nginx.

    HELP;
}

// ── Utilidades ────────────────────────────────────────────────────────────────

function formatBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}
