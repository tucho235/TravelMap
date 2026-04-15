<?php
/**
 * MCP Tools: POIs
 * list_pois, search_pois, create_poi, import_photos_batch, cleanup_temp_batch
 */

final class PoiTools
{
    private const MCP_TEMP_DIR = 'uploads/mcp_temp';

    public static function register(Dispatcher $d): void
    {
        $d->register('list_pois', 'Lista los puntos de interés (POIs) de un viaje.', [
            'type'       => 'object',
            'required'   => ['trip_id'],
            'properties' => [
                'trip_id' => ['type' => 'integer', 'minimum' => 1],
            ],
            'additionalProperties' => false,
        ], [self::class, 'listPois']);

        $d->register('search_pois', 'Busca POIs por texto libre, viaje o tipo.', [
            'type' => 'object',
            'properties' => [
                'query'   => ['type' => 'string', 'maxLength' => 200],
                'trip_id' => ['type' => 'integer', 'minimum' => 1],
                'type'    => ['type' => 'string', 'enum' => ['stay', 'visit', 'food', 'waypoint']],
                'limit'   => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'additionalProperties' => false,
        ], [self::class, 'searchPois']);

        $d->register('create_poi',
            'Crea un punto de interés. Si adjuntas una foto con GPS EXIF, las coordenadas y ' .
            'fecha se auto-rellenan si no las proporcionas. El output incluye suggested_place ' .
            '(ciudad sugerida por Nominatim) para que puedas decidir el título. ' .
            'Puedes pasar photo_base64 + photo_filename, o temp_photo_path de import_photos_batch.',
        [
            'type'       => 'object',
            'required'   => ['trip_id', 'type'],
            'properties' => [
                'trip_id'         => ['type' => 'integer', 'minimum' => 1],
                'title'           => ['type' => 'string', 'maxLength' => 200],
                'type'            => ['type' => 'string', 'enum' => ['stay', 'visit', 'food', 'waypoint']],
                'latitude'        => ['type' => 'number', 'minimum' => -90,  'maximum' => 90],
                'longitude'       => ['type' => 'number', 'minimum' => -180, 'maximum' => 180],
                'description'     => ['type' => 'string', 'maxLength' => 5000],
                'icon'            => ['type' => 'string', 'maxLength' => 64],
                'visit_date'      => ['type' => 'string'],
                'photo_base64'    => ['type' => 'string', 'maxLength' => 14000000],
                'photo_filename'  => ['type' => 'string', 'maxLength' => 255],
                'temp_photo_path' => ['type' => 'string', 'maxLength' => 500],
                'links' => [
                    'type' => 'array',
                    'maxItems' => 10,
                    'items' => [
                        'type'       => 'object',
                        'required'   => ['url'],
                        'properties' => [
                            'url'       => ['type' => 'string', 'maxLength' => 500],
                            'label'     => ['type' => 'string', 'maxLength' => 100],
                            'link_type' => ['type' => 'string', 'maxLength' => 40],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'additionalProperties' => false,
        ], [self::class, 'createPoi']);

        $d->register('import_photos_batch',
            'Analiza un lote de fotos JPEG: extrae GPS y fecha del EXIF, interpola coordenadas ' .
            'entre fotos vecinas, y opcionalmente hace reverse geocoding. No crea POIs — ' .
            'devuelve metadata para que decidas cuáles crear con create_poi usando temp_photo_path.',
        [
            'type'       => 'object',
            'required'   => ['photos'],
            'properties' => [
                'photos' => [
                    'type'     => 'array',
                    'minItems' => 1,
                    'maxItems' => 50,
                    'items'    => [
                        'type'       => 'object',
                        'required'   => ['photo_base64', 'filename'],
                        'properties' => [
                            'photo_base64' => ['type' => 'string', 'maxLength' => 14000000],
                            'filename'     => ['type' => 'string', 'maxLength' => 255],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'reverse_geocode' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ], [self::class, 'importPhotosBatch']);

        $d->register('cleanup_temp_batch', 'Elimina una carpeta temporal de import_photos_batch.', [
            'type'       => 'object',
            'required'   => ['token'],
            'properties' => [
                'token' => ['type' => 'string', 'maxLength' => 100],
            ],
            'additionalProperties' => false,
        ], [self::class, 'cleanupTempBatch']);
    }

    // ──────────────────────────────────────────────────────────────────────────

    public static function listPois(array $p): array
    {
        $tripId = (int)$p['trip_id'];
        self::assertTripExists($tripId);

        $pointModel = new Point();
        $rows       = $pointModel->getAll($tripId);

        return ['pois' => array_map([self::class, 'poiSummary'], $rows), 'count' => count($rows)];
    }

    public static function searchPois(array $p): array
    {
        $pointModel = new Point();
        $rows = $pointModel->search(
            $p['query']   ?? null,
            isset($p['trip_id']) ? (int)$p['trip_id'] : null,
            $p['type']    ?? null,
            (int)($p['limit'] ?? 25)
        );

        return ['pois' => array_map([self::class, 'poiSummary'], $rows), 'count' => count($rows)];
    }

    public static function createPoi(array $p): array
    {
        $tripId = (int)$p['trip_id'];
        self::assertTripExists($tripId);

        $imagePath     = null;
        $thumbnailPath = null;
        $autoFilled    = [];
        $exifData      = null;

        // ── Procesar foto ──────────────────────────────────────────────────────
        if (!empty($p['photo_base64'])) {
            if (empty($p['photo_filename'])) {
                throw new ToolException('photo_filename es obligatorio cuando se proporciona photo_base64', 'INVALID_INPUT', -32602);
            }
            $uploadResult = FileHelper::saveImageFromBase64(
                $p['photo_base64'],
                $p['photo_filename']
            );
            if (!$uploadResult['success']) {
                throw new ToolException($uploadResult['error'] ?? 'Error al guardar la foto', 'UPLOAD_FAILED');
            }
            $imagePath     = $uploadResult['path'];
            $thumbnailPath = $uploadResult['thumbnail_path'];

            // Extraer EXIF de la imagen guardada
            $fullPath = ROOT_PATH . '/' . $imagePath;
            $exifData = ExifExtractor::readFromFile($fullPath, $p['photo_filename']);

            McpLogger::info('create_poi: foto subida', [
                'path'    => $imagePath,
                'size_kb' => round(strlen($p['photo_base64']) * 0.75 / 1024),
            ]);

        } elseif (!empty($p['temp_photo_path'])) {
            // Mover desde mcp_temp/
            $movedResult = self::moveTempPhoto($p['temp_photo_path']);
            $imagePath     = $movedResult['path'];
            $thumbnailPath = $movedResult['thumbnail_path'];
            $exifData      = $movedResult['exif'];
        }

        // ── Auto-fill desde EXIF ───────────────────────────────────────────────
        $latitude  = isset($p['latitude'])  ? (float)$p['latitude']  : null;
        $longitude = isset($p['longitude']) ? (float)$p['longitude'] : null;
        $visitDate = $p['visit_date'] ?? null;

        if ($exifData) {
            if ($latitude === null && $exifData['has_gps']) {
                $latitude  = $exifData['latitude'];
                $longitude = $exifData['longitude'];
                $autoFilled['latitude']  = $latitude;
                $autoFilled['longitude'] = $longitude;
            }
            if ($visitDate === null && $exifData['has_date']) {
                $visitDate = $exifData['date'];
                $autoFilled['visit_date'] = $visitDate;
            }
        }

        if ($latitude === null || $longitude === null) {
            throw new ToolException(
                'No se pudieron determinar las coordenadas. Proporciona latitude/longitude o una foto con GPS EXIF.',
                'COORDINATES_REQUIRED', -32602
            );
        }

        // ── Suggested place via Nominatim ──────────────────────────────────────
        $suggestedPlace = null;
        try {
            $suggestedPlace = Geocoder::reverseLookup($latitude, $longitude);
        } catch (Exception $e) {
            // silencioso — no es crítico
            McpLogger::error('Geocoder falló en create_poi: ' . $e->getMessage());
        }

        // ── Crear POI ──────────────────────────────────────────────────────────
        $title = isset($p['title']) && $p['title'] !== '' ? trim($p['title']) : 'POI sin título';

        $data = [
            'trip_id'    => $tripId,
            'title'      => $title,
            'type'       => $p['type'],
            'latitude'   => $latitude,
            'longitude'  => $longitude,
            'description'=> $p['description'] ?? null,
            'icon'       => $p['icon']        ?? 'default',
            'image_path' => $imagePath,
            'visit_date' => $visitDate,
        ];

        $pointModel = new Point();
        $errors     = $pointModel->validate($data);
        if (!empty($errors)) {
            throw new ToolException('Datos de POI inválidos', 'INVALID_INPUT', -32602, ['fieldErrors' => $errors]);
        }

        $id = $pointModel->create($data);
        if (!$id) {
            throw new ToolException('No se pudo crear el POI en la base de datos', 'DB_ERROR');
        }

        if (!empty($p['links'])) {
            $linkModel = new PoiLink();
            $links = array_map(function ($l) {
                return [
                    'link_type' => $l['link_type'] ?? 'other',
                    'url'       => $l['url'],
                    'label'     => $l['label'] ?? null,
                ];
            }, $p['links']);
            $linkModel->replaceForPoi((int)$id, $links);
        }

        McpLogger::info('create_poi OK', [
            'id'      => $id,
            'trip_id' => $tripId,
            'title'   => $title,
            'lat'     => $latitude,
            'lng'     => $longitude,
        ]);

        return [
            'id'              => (int)$id,
            'title'           => $title,
            'trip_id'         => $tripId,
            'latitude'        => $latitude,
            'longitude'       => $longitude,
            'image_path'      => $imagePath,
            'thumbnail_path'  => $thumbnailPath,
            'auto_filled'     => $autoFilled ?: null,
            'suggested_place' => $suggestedPlace,
            'admin_url'       => '/admin/point_form.php?id=' . $id,
        ];
    }

    public static function importPhotosBatch(array $p): array
    {
        $doGeocode = (bool)($p['reverse_geocode'] ?? true);

        // Generar token de sesión para carpeta temporal
        $token   = 'mcp_' . preg_replace('/[^a-zA-Z0-9_]/', '_', uniqid('', true));
        $tempDir = ROOT_PATH . '/' . self::MCP_TEMP_DIR . '/' . $token;

        if (!is_dir(ROOT_PATH . '/' . self::MCP_TEMP_DIR)) {
            mkdir(ROOT_PATH . '/' . self::MCP_TEMP_DIR, 0750, true);
            // .htaccess anti-PHP
            file_put_contents(
                ROOT_PATH . '/' . self::MCP_TEMP_DIR . '/.htaccess',
                "Options -ExecCGI\nAddHandler cgi-script .php\n<FilesMatch \"\\.php$\">\n  Deny from all\n</FilesMatch>\n"
            );
        }

        mkdir($tempDir, 0750, true);

        $images = [];
        $errors = [];

        foreach ($p['photos'] as $i => $photoInput) {
            $filename = basename($photoInput['filename']);
            if ($filename === '' || strpbrk($filename, "\0\r\n") !== false) {
                $errors[] = "Foto #{$i}: nombre de archivo inválido";
                continue;
            }

            $raw64 = $photoInput['photo_base64'];
            if (strlen($raw64) > 14_000_000) {
                $errors[] = "Foto '{$filename}': supera el límite de tamaño";
                continue;
            }

            // Strip data-URL
            if (preg_match('/^data:image\/[^;]+;base64,/i', $raw64)) {
                $raw64 = preg_replace('/^data:image\/[^;]+;base64,/i', '', $raw64);
            }

            $bytes = base64_decode($raw64, true);
            if ($bytes === false) {
                $errors[] = "Foto '{$filename}': base64 inválido";
                continue;
            }

            // MIME
            $tmpCheck = tempnam(sys_get_temp_dir(), 'mcp_chk_');
            file_put_contents($tmpCheck, $bytes);
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeReal = finfo_file($finfo, $tmpCheck);
            finfo_close($finfo);
            @unlink($tmpCheck);

            $allowedMime = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($mimeReal, $allowedMime, true)) {
                $errors[] = "Foto '{$filename}': tipo MIME no permitido ({$mimeReal})";
                continue;
            }

            // Guardar en temp
            $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'jpg';
            $tmpName  = preg_replace('/[^a-zA-Z0-9_]/', '_', uniqid('tmp_', true)) . '.' . $ext;
            $tmpPath  = $tempDir . '/' . $tmpName;
            file_put_contents($tmpPath, $bytes);
            chmod($tmpPath, 0644);

            $exif = ExifExtractor::readFromFile($tmpPath, $filename);

            $images[] = [
                'original_filename' => $filename,
                'temp_filename'     => $tmpName,
                'temp_path'         => self::MCP_TEMP_DIR . '/' . $token . '/' . $tmpName,
                'has_gps'           => $exif['has_gps'],
                'has_date'          => $exif['has_date'],
                'latitude'          => $exif['latitude'],
                'longitude'         => $exif['longitude'],
                'gps_source'        => $exif['has_gps'] ? 'exif' : null,
                'date'              => $exif['date'],
                'date_source'       => $exif['date_source'],
                'timestamp'         => $exif['timestamp'],
                'gps_estimated'     => false,
                'suggested_place'   => null,
            ];
        }

        // Ordenar por timestamp
        usort($images, function ($a, $b) {
            $ta = $a['timestamp'] ?? PHP_INT_MAX;
            $tb = $b['timestamp'] ?? PHP_INT_MAX;
            return $ta <=> $tb;
        });

        // Interpolar GPS
        ExifExtractor::interpolateMissingGps($images);

        // Reverse geocoding opcional
        if ($doGeocode) {
            foreach ($images as &$img) {
                if ($img['has_gps'] && $img['latitude'] !== null) {
                    try {
                        $img['suggested_place'] = Geocoder::reverseLookup(
                            (float)$img['latitude'],
                            (float)$img['longitude']
                        );
                    } catch (Exception $e) {
                        // silencioso
                    }
                }
            }
            unset($img);
        }

        // Limpiar campos internos del output
        $output = [];
        foreach ($images as $img) {
            $out = $img;
            unset($out['timestamp'], $out['temp_filename'], $out['has_date'], $out['has_gps']);
            $output[] = $out;
        }

        $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24h

        McpLogger::info('import_photos_batch OK', [
            'token'        => $token,
            'photos_total' => count($p['photos']),
            'photos_ok'    => count($images),
            'errors'       => count($errors),
        ]);

        return [
            'token'      => $token,
            'expires_at' => $expiresAt,
            'photos'     => $output,
            'errors'     => $errors,
            'tip'        => 'Usa create_poi con temp_photo_path para crear POIs desde estas fotos',
        ];
    }

    public static function cleanupTempBatch(array $p): array
    {
        $token = $p['token'];

        // Validar token: solo alfanumérico + _
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $token)) {
            throw new ToolException('Token inválido', 'INVALID_INPUT', -32602);
        }

        $dir = ROOT_PATH . '/' . self::MCP_TEMP_DIR . '/' . $token;

        // Verificar que el directorio esté dentro de mcp_temp (realpath check)
        $baseReal = realpath(ROOT_PATH . '/' . self::MCP_TEMP_DIR);
        $dirReal  = realpath($dir);

        if ($dirReal === false || $baseReal === false || strpos($dirReal, $baseReal . '/') !== 0) {
            throw new ToolException('Ruta de token no válida', 'INVALID_INPUT', -32602);
        }

        if (!is_dir($dirReal)) {
            return ['deleted' => false, 'message' => 'El directorio temporal no existe o ya fue eliminado'];
        }

        $files = glob($dirReal . '/*');
        $count = 0;
        foreach ($files ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
                $count++;
            }
        }
        @rmdir($dirReal);

        McpLogger::info('cleanup_temp_batch OK', ['token' => $token, 'files_deleted' => $count]);

        return ['deleted' => true, 'files_deleted' => $count, 'token' => $token];
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function moveTempPhoto(string $tempRelPath): array
    {
        // Validar que está dentro de mcp_temp/ (realpath check)
        $baseReal = realpath(ROOT_PATH . '/' . self::MCP_TEMP_DIR);
        if ($baseReal === false) {
            throw new ToolException('Directorio temporal MCP no existe', 'SERVER_ERROR');
        }

        // Descomponer el path: solo el nombre de archivo, sin traversal
        $safeName = basename($tempRelPath);
        // Intentar reconstruir la ruta desde el token en el path
        $parts = explode('/', ltrim($tempRelPath, '/'));
        // Esperamos: uploads/mcp_temp/<token>/<filename>
        if (count($parts) < 4) {
            throw new ToolException('temp_photo_path inválido', 'INVALID_INPUT', -32602);
        }
        $token   = $parts[2]; // mcp_temp/<token>
        $fname   = $parts[3];

        // Validar token
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $token)) {
            throw new ToolException('Token en temp_photo_path inválido', 'INVALID_INPUT', -32602);
        }

        $fullPath = $baseReal . '/' . $token . '/' . basename($fname);
        $realFull = realpath($fullPath);

        if ($realFull === false || strpos($realFull, $baseReal . '/') !== 0) {
            throw new ToolException('temp_photo_path fuera del directorio permitido', 'INVALID_INPUT', -32602);
        }

        if (!is_file($realFull)) {
            throw new ToolException('temp_photo_path no existe o no es un archivo', 'INVALID_INPUT', -32602);
        }

        // Leer bytes, validar MIME y mover a uploads/points/
        $bytes = file_get_contents($realFull);
        $ext   = strtolower(pathinfo($fname, PATHINFO_EXTENSION)) ?: 'jpg';

        $result = FileHelper::saveImageFromBase64(
            base64_encode($bytes),
            'temp_photo.' . $ext
        );

        if (!$result['success']) {
            throw new ToolException($result['error'] ?? 'Error al procesar foto temporal', 'UPLOAD_FAILED');
        }

        // Extraer EXIF antes de borrar el temp
        $exif = ExifExtractor::readFromFile($realFull, $fname);

        @unlink($realFull);

        return [
            'path'          => $result['path'],
            'thumbnail_path'=> $result['thumbnail_path'],
            'exif'          => $exif,
        ];
    }

    private static function assertTripExists(int $tripId): void
    {
        $tripModel = new Trip();
        if (!$tripModel->getById($tripId)) {
            throw new ToolException("Viaje con id={$tripId} no encontrado", 'TRIP_NOT_FOUND');
        }
    }

    private static function poiSummary(array $poi): array
    {
        return [
            'id'         => (int)$poi['id'],
            'trip_id'    => (int)$poi['trip_id'],
            'title'      => $poi['title'],
            'type'       => $poi['type'],
            'latitude'   => (float)$poi['latitude'],
            'longitude'  => (float)$poi['longitude'],
            'visit_date' => $poi['visit_date'],
            'image_path' => $poi['image_path'],
        ];
    }
}
