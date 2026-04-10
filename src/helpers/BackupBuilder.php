<?php
/**
 * BackupBuilder — TravelMap
 *
 * Lógica compartida para construir archivos de backup (JSON o ZIP).
 * Usada tanto por admin/backup.php (web) como por bin/travelmap-backup.php (CLI).
 *
 * No emite headers, no redirige, no usa variables superglobales HTTP.
 * Lanza RuntimeException en caso de error para que el llamador decida cómo manejarlo.
 */
class BackupBuilder
{
    private \PDO   $db;
    private string $rootPath;
    private string $version;

    public function __construct(\PDO $db, string $rootPath, string $version = '1.0.0')
    {
        $this->db       = $db;
        $this->rootPath = rtrim($rootPath, '/');
        $this->version  = $version;
    }

    /**
     * Genera un archivo de backup y lo guarda en $opts['output_dir'].
     *
     * @param array $opts {
     *   bool   include_trips    Exportar tabla trips
     *   bool   include_routes   Exportar tabla routes
     *   bool   include_points   Exportar tabla points_of_interest
     *   bool   include_tags     Exportar tabla trip_tags
     *   bool   include_settings Exportar tabla settings
     *   bool   include_images   Incluir imágenes (produce ZIP; si no hay imágenes, produce JSON)
     *   string output_dir       Directorio destino (por defecto ROOT_PATH/backups)
     * }
     * @return string Ruta absoluta del archivo generado
     * @throws \RuntimeException Si no se puede crear el directorio, abrir el ZIP, etc.
     */
    public function create(array $opts): string
    {
        $includeTrips    = (bool)($opts['include_trips']    ?? true);
        $includeRoutes   = (bool)($opts['include_routes']   ?? true);
        $includePoints   = (bool)($opts['include_points']   ?? true);
        $includeTags     = (bool)($opts['include_tags']     ?? true);
        $includeSettings = (bool)($opts['include_settings'] ?? true);
        $includeImages   = (bool)($opts['include_images']   ?? true);
        $outputDir       = $opts['output_dir'] ?? ($this->rootPath . '/backups');

        // Asegurar que el directorio destino existe
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                throw new \RuntimeException("No se pudo crear el directorio de backup: {$outputDir}");
            }
        }

        // Armar payload base
        $backup = [
            'version'           => '1.0',
            'exported_at'       => date('c'),
            'travelmap_version' => $this->version,
            'includes'          => [],
            'data'              => [],
        ];

        try {
            if ($includeTrips) {
                $stmt = $this->db->query('SELECT * FROM trips ORDER BY id');
                $backup['data']['trips'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $backup['includes'][] = 'trips';
            }

            if ($includeRoutes) {
                $stmt = $this->db->query('SELECT * FROM routes ORDER BY id');
                $backup['data']['routes'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $backup['includes'][] = 'routes';
            }

            if ($includePoints) {
                $stmt = $this->db->query('SELECT * FROM points_of_interest ORDER BY id');
                $backup['data']['points_of_interest'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $backup['includes'][] = 'points';
            }

            if ($includeTags) {
                $stmt = $this->db->query('SELECT * FROM trip_tags ORDER BY id');
                $backup['data']['trip_tags'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $backup['includes'][] = 'tags';
            }

            if ($includeSettings) {
                $stmt = $this->db->query(
                    'SELECT setting_key, setting_value, setting_type, description FROM settings ORDER BY setting_key'
                );
                $backup['data']['settings'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $backup['includes'][] = 'settings';
            }
        } catch (\PDOException $e) {
            throw new \RuntimeException('Error al consultar la base de datos: ' . $e->getMessage(), 0, $e);
        }

        $timestamp   = date('Y-m-d_His');
        $uploadsDir  = $this->rootPath . '/uploads/points';
        $hasImages   = $includeImages && is_dir($uploadsDir) && $this->countImages($uploadsDir) > 0;

        if ($hasImages) {
            return $this->buildZip($backup, $timestamp, $outputDir, $uploadsDir);
        }

        return $this->buildJson($backup, $timestamp, $outputDir);
    }

    // -------------------------------------------------------------------------
    // Métodos privados
    // -------------------------------------------------------------------------

    private function countImages(string $dir): int
    {
        $files = glob($dir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        return $files ? count($files) : 0;
    }

    private function buildJson(array $backup, string $timestamp, string $outputDir): string
    {
        $filename = "backup_{$timestamp}.json";
        $path     = $outputDir . '/' . $filename;
        $content  = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException("No se pudo escribir el archivo JSON: {$path}");
        }

        return $path;
    }

    private function buildZip(array $backup, string $timestamp, string $outputDir, string $uploadsDir): string
    {
        $filename = "backup_{$timestamp}.zip";
        $path     = $outputDir . '/' . $filename;

        $zip = new \ZipArchive();
        $result = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new \RuntimeException("No se pudo crear el archivo ZIP: {$path} (código {$result})");
        }

        $zip->addFromString('data.json', json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $images = glob($uploadsDir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE) ?: [];
        foreach ($images as $img) {
            $zip->addFile($img, 'uploads/points/' . basename($img));
        }

        $thumbsDir = $uploadsDir . '/thumbs';
        if (is_dir($thumbsDir)) {
            $thumbs = glob($thumbsDir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE) ?: [];
            foreach ($thumbs as $thumb) {
                $zip->addFile($thumb, 'uploads/points/thumbs/' . basename($thumb));
            }
        }

        $zip->close();

        return $path;
    }
}
