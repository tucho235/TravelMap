<?php
/**
 * MCP Tools: Routes
 * list_routes, create_route
 */

final class RouteTools
{
    private const ALLOWED_TRANSPORT = ['plane', 'car', 'bike', 'walk', 'ship', 'train', 'bus', 'aerial'];

    public static function register(Dispatcher $d): void
    {
        $d->register('list_routes', 'Lista las rutas de un viaje.', [
            'type'       => 'object',
            'required'   => ['trip_id'],
            'properties' => [
                'trip_id' => ['type' => 'integer', 'minimum' => 1],
            ],
            'additionalProperties' => false,
        ], [self::class, 'listRoutes']);

        $d->register('create_route',
            'Crea una ruta para un viaje. Proporciona EXACTAMENTE UNA fuente de geometría: ' .
            'geojson_data (GeoJSON como string), brouter_csv_text (contenido CSV de BRouter) ' .
            'o brouter_csv_base64 (CSV de BRouter codificado en base64).',
        [
            'type'       => 'object',
            'required'   => ['trip_id', 'transport_type'],
            'properties' => [
                'trip_id'            => ['type' => 'integer', 'minimum' => 1],
                'transport_type'     => ['type' => 'string', 'enum' => self::ALLOWED_TRANSPORT],
                'name'               => ['type' => 'string', 'maxLength' => 200],
                'description'        => ['type' => 'string', 'maxLength' => 5000],
                'is_round_trip'      => ['type' => 'boolean'],
                'color'              => ['type' => 'string', 'pattern' => '/^#[0-9A-Fa-f]{6}$/'],
                'start_datetime'     => ['type' => 'string'],
                'end_datetime'       => ['type' => 'string'],
                'geojson_data'       => ['type' => 'string', 'maxLength' => 5000000],
                'brouter_csv_text'   => ['type' => 'string', 'maxLength' => 5242880],
                'brouter_csv_base64' => ['type' => 'string', 'maxLength' => 7340032],
                'links' => [
                    'type'     => 'array',
                    'maxItems' => 10,
                    'items'    => [
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
        ], [self::class, 'createRoute']);

        $d->register('update_route',
            'Actualiza los metadatos de una ruta existente. Solo se modifican los campos proporcionados. ' .
            'La geometría (geojson) no se puede cambiar desde esta tool. ' .
            'Para actualizar los links proporciona el array completo (reemplaza los existentes).',
        [
            'type'       => 'object',
            'required'   => ['id'],
            'properties' => [
                'id'             => ['type' => 'integer', 'minimum' => 1],
                'name'           => ['type' => 'string', 'maxLength' => 200],
                'description'    => ['type' => 'string', 'maxLength' => 5000],
                'transport_type' => ['type' => 'string', 'enum' => self::ALLOWED_TRANSPORT],
                'color'          => ['type' => 'string', 'pattern' => '/^#[0-9A-Fa-f]{6}$/'],
                'is_round_trip'  => ['type' => 'boolean'],
                'start_datetime' => ['type' => 'string'],
                'end_datetime'   => ['type' => 'string'],
                'links' => [
                    'type'     => 'array',
                    'maxItems' => 10,
                    'items'    => [
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
        ], [self::class, 'updateRoute']);
    }

    // ──────────────────────────────────────────────────────────────────────────

    public static function listRoutes(array $p): array
    {
        $tripId = (int)$p['trip_id'];
        self::assertTripExists($tripId);

        $routeModel = new Route();
        $rows       = $routeModel->getByTripId($tripId);

        $routes = [];
        foreach ($rows as $r) {
            $routes[] = [
                'id'              => (int)$r['id'],
                'name'            => $r['name'],
                'transport_type'  => $r['transport_type'],
                'distance_meters' => (int)$r['distance_meters'],
                'distance_km'     => round((int)$r['distance_meters'] / 1000, 2),
                'is_round_trip'   => (bool)$r['is_round_trip'],
                'start_datetime'  => $r['start_datetime'] ?? null,
                'end_datetime'    => $r['end_datetime']   ?? null,
                'color'           => $r['color'],
                'description'     => $r['description'],
            ];
        }

        return ['routes' => $routes, 'count' => count($routes)];
    }

    public static function createRoute(array $p): array
    {
        $tripId = (int)$p['trip_id'];
        self::assertTripExists($tripId);

        // Validar que exactamente una fuente de geometría esté presente
        $hasCsvBase64 = isset($p['brouter_csv_base64']) && $p['brouter_csv_base64'] !== '';
        $hasCsvText   = isset($p['brouter_csv_text'])   && $p['brouter_csv_text']   !== '';
        $hasGeojson   = isset($p['geojson_data'])        && $p['geojson_data']        !== '';

        $sourceCount = (int)$hasCsvBase64 + (int)$hasCsvText + (int)$hasGeojson;
        if ($sourceCount === 0) {
            throw new ToolException(
                'Debes proporcionar exactamente una fuente de geometría: geojson_data, brouter_csv_text o brouter_csv_base64',
                'INVALID_INPUT', -32602
            );
        }
        if ($sourceCount > 1) {
            throw new ToolException(
                'Solo se permite una fuente de geometría a la vez (geojson_data, brouter_csv_text o brouter_csv_base64)',
                'INVALID_INPUT', -32602
            );
        }

        $geojsonData    = null;
        $waypointsCount = null;
        $distanceKm     = null;

        if ($hasCsvBase64) {
            // Validar y decodificar base64
            $raw = $p['brouter_csv_base64'];
            if (strlen($raw) > 7_340_032) {
                throw new ToolException('El archivo CSV base64 supera el límite de 7 MB', 'FILE_TOO_LARGE');
            }
            $bytes = base64_decode($raw, true);
            if ($bytes === false) {
                throw new ToolException('La cadena brouter_csv_base64 no es base64 válida', 'INVALID_BASE64');
            }
            if (strlen($bytes) > 5 * 1024 * 1024) {
                throw new ToolException('El CSV decodificado supera 5 MB', 'FILE_TOO_LARGE');
            }
            $result = self::parseBRouterBytes($bytes);
            $geojsonData    = $result['geojson_data'];
            $waypointsCount = $result['waypoints_count'];
            $distanceKm     = $result['distance_km'];

        } elseif ($hasCsvText) {
            $bytes = $p['brouter_csv_text'];
            if (strlen($bytes) > 5 * 1024 * 1024) {
                throw new ToolException('El CSV de texto supera 5 MB', 'FILE_TOO_LARGE');
            }
            $result = self::parseBRouterBytes($bytes);
            $geojsonData    = $result['geojson_data'];
            $waypointsCount = $result['waypoints_count'];
            $distanceKm     = $result['distance_km'];

        } else {
            // geojson_data
            $decoded = json_decode($p['geojson_data'], true);
            if ($decoded === null) {
                throw new ToolException('geojson_data no es JSON válido', 'INVALID_INPUT', -32602);
            }
            $type = $decoded['type'] ?? '';
            if (!in_array($type, ['Feature', 'FeatureCollection'], true)) {
                throw new ToolException('geojson_data debe ser un Feature o FeatureCollection GeoJSON', 'INVALID_INPUT', -32602);
            }
            $geojsonData = $p['geojson_data'];
            // Intentar extraer waypoints
            $coords = $decoded['geometry']['coordinates'] ?? $decoded['features'][0]['geometry']['coordinates'] ?? [];
            $waypointsCount = count($coords);
        }

        $data = [
            'trip_id'        => $tripId,
            'transport_type' => $p['transport_type'],
            'geojson_data'   => $geojsonData,
            'is_round_trip'  => isset($p['is_round_trip']) ? (int)(bool)$p['is_round_trip'] : 0,
            'name'           => $p['name']           ?? null,
            'description'    => $p['description']    ?? null,
            'color'          => $p['color']          ?? '#3388ff',
            'start_datetime' => $p['start_datetime'] ?? null,
            'end_datetime'   => $p['end_datetime']   ?? null,
        ];

        $routeModel = new Route();
        $id = $routeModel->create($data);
        if (!$id) {
            throw new ToolException('No se pudo crear la ruta en la base de datos', 'DB_ERROR');
        }

        // Leer distancia calculada por el modelo (Haversine)
        $created = $routeModel->getById((int)$id);
        $distanceMeters = $created ? (int)$created['distance_meters'] : 0;

        if (!empty($p['links'])) {
            $linkModel = new Link();
            $links = array_map(function ($l) {
                return [
                    'link_type' => $l['link_type'] ?? 'other',
                    'url'       => $l['url'],
                    'label'     => $l['label'] ?? null,
                ];
            }, $p['links']);
            $linkModel->replaceForRoute((int)$id, $links);
        }

        McpLogger::info('create_route OK', [
            'id'       => $id,
            'trip_id'  => $tripId,
            'transport'=> $data['transport_type'],
            'dist_m'   => $distanceMeters,
            'waypoints'=> $waypointsCount,
        ]);

        return [
            'id'              => (int)$id,
            'trip_id'         => $tripId,
            'transport_type'  => $data['transport_type'],
            'distance_meters' => $distanceMeters,
            'distance_km'     => round($distanceMeters / 1000, 2),
            'waypoints_count' => $waypointsCount,
        ];
    }

    public static function updateRoute(array $p): array
    {
        $id = (int)$p['id'];
        $routeModel = new Route();
        $current = $routeModel->getById($id);
        if (!$current) {
            throw new ToolException("Ruta con id={$id} no encontrada", 'ROUTE_NOT_FOUND');
        }

        // Mezclar campos actuales con los proporcionados; la geometría nunca cambia
        $data = [
            'transport_type' => $p['transport_type'] ?? $current['transport_type'],
            'name'           => array_key_exists('name', $p)           ? $p['name']           : $current['name'],
            'description'    => array_key_exists('description', $p)    ? $p['description']    : $current['description'],
            'color'          => $p['color']          ?? $current['color'],
            'is_round_trip'  => array_key_exists('is_round_trip', $p)  ? (int)(bool)$p['is_round_trip'] : (int)$current['is_round_trip'],
            'start_datetime' => array_key_exists('start_datetime', $p) ? $p['start_datetime'] : $current['start_datetime'],
            'end_datetime'   => array_key_exists('end_datetime', $p)   ? $p['end_datetime']   : $current['end_datetime'],
            'geojson_data'   => $current['geojson_data'],
            'image_path'     => $current['image_path'],
        ];

        if (!$routeModel->update($id, $data)) {
            throw new ToolException('No se pudo actualizar la ruta', 'DB_ERROR');
        }

        if (array_key_exists('links', $p)) {
            $linkModel = new Link();
            $links = array_map(function ($l) {
                return [
                    'link_type' => $l['link_type'] ?? 'other',
                    'url'       => $l['url'],
                    'label'     => $l['label'] ?? null,
                ];
            }, $p['links']);
            $linkModel->replaceForRoute($id, $links);
        }

        $updated = $routeModel->getById($id);
        McpLogger::info('update_route OK', ['id' => $id]);

        return [
            'id'              => $id,
            'name'            => $updated['name'],
            'transport_type'  => $updated['transport_type'],
            'distance_meters' => (int)$updated['distance_meters'],
            'distance_km'     => round((int)$updated['distance_meters'] / 1000, 2),
            'admin_url'       => '/admin/route_form.php?id=' . $id,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function parseBRouterBytes(string $bytes): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mcp_brouter_');
        if ($tmpFile === false) {
            throw new ToolException('No se pudo crear archivo temporal para el CSV', 'SERVER_ERROR');
        }
        try {
            file_put_contents($tmpFile, $bytes);
            $result = BRouterParser::parseFromFile($tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        if (!$result['success']) {
            throw new ToolException($result['error'] ?? 'Error al parsear el CSV de BRouter', 'PARSE_FAILED');
        }

        return $result;
    }

    private static function assertTripExists(int $tripId): void
    {
        $tripModel = new Trip();
        if (!$tripModel->getById($tripId)) {
            throw new ToolException("Viaje con id={$tripId} no encontrado", 'TRIP_NOT_FOUND');
        }
    }
}
