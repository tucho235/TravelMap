#!/usr/bin/env php
<?php
/**
 * travelmap-backup.php — CLI de backups para TravelMap
 *
 * SEGURIDAD:
 *   - Solo funciona desde CLI (PHP_SAPI === 'cli').
 *   - No usa $_GET, $_POST, $_REQUEST ni ninguna variable HTTP.
 *   - El directorio bin/ tiene .htaccess que deniega todo acceso web.
 *   - Aplicar permisos tras instalar:
 *       chmod 0750 bin/
 *       chmod 0700 bin/travelmap-backup.php
 *     Con eso www-data no puede leer este archivo aunque .htaccess falle.
 *
 * USO:
 *   php bin/travelmap-backup.php create [--no-images] [--only=trips,routes,...] [--output=/ruta]
 *   php bin/travelmap-backup.php list
 *   php bin/travelmap-backup.php help
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

// ── Parseo temprano de argumentos (antes del bootstrap) ──────────────────────
$args    = array_slice($argv, 1);
$command = $args[0] ?? 'help';

/**
 * Parsea flags del tipo --nombre o --nombre=valor desde $args.
 * Devuelve ['flags' => [...], 'values' => ['key' => 'val', ...]].
 */
function parseArgs(array $args): array
{
    $flags  = [];
    $values = [];
    foreach (array_slice($args, 1) as $arg) {
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
if ($command === 'help') {
    cmdHelp();
    exit(0);
}

// ── Bootstrap (sin includes/auth.php — emite header() y mataría el CLI) ──────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/helpers/BackupBuilder.php';

// ── Despacho de comandos ─────────────────────────────────────────────────────
match ($command) {
    'create' => cmdCreate($args),
    'list'   => cmdList(),
    default  => (function () use ($command) {
        fwrite(STDERR, '[ERROR] Comando desconocido: ' . $command . PHP_EOL);
        cmdHelp();
        exit(1);
    })(),
};

exit(0);

// ── Implementación de comandos ───────────────────────────────────────────────

function cmdCreate(array $args): void
{
    $parsed    = parseArgs($args);
    $flags     = $parsed['flags'];
    $values    = $parsed['values'];
    $backupDir = ROOT_PATH . '/backups';

    // --output=<ruta>
    if (isset($values['output'])) {
        $requestedDir = $values['output'];
        // Resolver ../  y symlinks usando realpath() del directorio padre
        // (el dir destino puede no existir aún, así que resolvemos su padre)
        $parentDir = is_dir($requestedDir) ? $requestedDir : dirname($requestedDir);
        $resolvedParent = realpath($parentDir);
        if ($resolvedParent === false) {
            fail("--output: el directorio padre no existe o no es accesible: {$parentDir}");
        }
        // Reconstruir ruta final con el padre resuelto
        $backupDir = is_dir($requestedDir)
            ? $resolvedParent
            : $resolvedParent . '/' . basename($requestedDir);
    }

    // --no-images
    $includeImages = !in_array('no-images', $flags, true);

    // --only=trips,routes,points,tags,settings
    $validSections = ['trips', 'routes', 'points', 'tags', 'settings'];
    $activeSections = $validSections; // por defecto, todas
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

        // Leer versión desde version.php si existe
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
        error_log('travelmap-backup create: ' . $e->getMessage());
        fail('No se pudo crear el backup: ' . $e->getMessage());
    }
}

function cmdList(): void
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

    // Ordenar por fecha descendente
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

function cmdHelp(): void
{
    $script = basename(__FILE__);
    echo <<<HELP
    TravelMap — CLI de backups

    USO:
      php bin/{$script} <comando> [opciones]

    COMANDOS:
      create            Crea un nuevo backup (por defecto: todos los datos + imágenes en ZIP)
      list              Lista los backups existentes en ROOT_PATH/backups
      help              Muestra esta ayuda

    OPCIONES DE create:
      --no-images       Excluye imágenes; produce un archivo JSON en lugar de ZIP
      --only=<lista>    Incluye solo las secciones indicadas (separadas por coma):
                          trips, routes, points, tags, settings
      --output=<ruta>   Directorio destino (default: ROOT_PATH/backups)

    EJEMPLOS:
      # Backup completo (datos + imágenes)
      php bin/{$script} create

      # Solo datos, sin imágenes
      php bin/{$script} create --no-images

      # Solo viajes y rutas
      php bin/{$script} create --no-images --only=trips,routes

      # Backup con destino personalizado
      php bin/{$script} create --output=/mnt/nas/travelmap

      # Listar backups existentes
      php bin/{$script} list

    CRON (ejemplo semanal — domingos 03:00):
      0 3 * * 0  /usr/bin/php /ruta/a/TravelMap/bin/{$script} create >> /var/log/travelmap-backup.log 2>&1

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
