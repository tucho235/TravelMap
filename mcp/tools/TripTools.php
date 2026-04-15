<?php
/**
 * MCP Tools: Trips
 * list_trips, search_trips, get_trip, create_trip
 */

final class TripTools
{
    public static function register(Dispatcher $d): void
    {
        $d->register('list_trips', 'Lista viajes almacenados. Usa search_trips para búsqueda por texto.', [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
                'limit'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
                'order'  => ['type' => 'string', 'enum' => ['recent', 'oldest', 'start_date_desc', 'start_date_asc', 'title']],
            ],
            'additionalProperties' => false,
        ], [self::class, 'listTrips']);

        $d->register('search_trips', 'Busca viajes por texto libre en título/descripción, tag o rango de fechas.', [
            'type' => 'object',
            'properties' => [
                'query'     => ['type' => 'string', 'maxLength' => 200],
                'tag'       => ['type' => 'string', 'maxLength' => 60],
                'date_from' => ['type' => 'string'],
                'date_to'   => ['type' => 'string'],
                'status'    => ['type' => 'string', 'enum' => ['draft', 'published']],
                'limit'     => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'additionalProperties' => false,
        ], [self::class, 'searchTrips']);

        $d->register('get_trip', 'Obtiene un viaje completo con sus rutas, POIs y tags.', [
            'type'       => 'object',
            'required'   => ['id'],
            'properties' => [
                'id'              => ['type' => 'integer', 'minimum' => 1],
                'include_geojson' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ], [self::class, 'getTrip']);

        $d->register('create_trip', 'Crea un nuevo viaje. Devuelve el id creado.', [
            'type'       => 'object',
            'required'   => ['title'],
            'properties' => [
                'title'       => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                'description' => ['type' => 'string', 'maxLength' => 5000],
                'start_date'  => ['type' => 'string'],
                'end_date'    => ['type' => 'string'],
                'color_hex'   => ['type' => 'string', 'pattern' => '/^#[0-9A-Fa-f]{6}$/'],
                'status'      => ['type' => 'string', 'enum' => ['draft', 'published']],
                'tags'        => ['type' => 'array', 'maxItems' => 20, 'items' => ['type' => 'string', 'maxLength' => 60]],
            ],
            'additionalProperties' => false,
        ], [self::class, 'createTrip']);
    }

    // ──────────────────────────────────────────────────────────────────────────

    public static function listTrips(array $p): array
    {
        $orderMap = [
            'recent'          => 'created_at DESC',
            'oldest'          => 'created_at ASC',
            'start_date_desc' => 'start_date DESC',
            'start_date_asc'  => 'start_date ASC',
            'title'           => 'title ASC',
        ];
        $orderBy = $orderMap[$p['order'] ?? 'recent'] ?? 'created_at DESC';
        $status  = $p['status'] ?? null;
        $limit   = min((int)($p['limit'] ?? 50), 200);

        $tripModel = new Trip();
        $rows      = $tripModel->getAll($orderBy, $status);
        $rows      = array_slice($rows, 0, $limit);

        $trips = [];
        foreach ($rows as $row) {
            $trips[] = self::tripSummary($row, $tripModel);
        }

        return ['trips' => $trips, 'count' => count($trips)];
    }

    public static function searchTrips(array $p): array
    {
        $tripModel = new Trip();
        $rows = $tripModel->search(
            $p['query']     ?? null,
            $p['tag']       ?? null,
            $p['date_from'] ?? null,
            $p['date_to']   ?? null,
            $p['status']    ?? null,
            (int)($p['limit'] ?? 25)
        );

        $trips = [];
        foreach ($rows as $row) {
            $trips[] = self::tripSummary($row, $tripModel);
        }

        return ['trips' => $trips, 'count' => count($trips)];
    }

    public static function getTrip(array $p): array
    {
        $tripModel = new Trip();
        $trip      = $tripModel->getById((int)$p['id']);

        if (!$trip) {
            throw new ToolException("Viaje con id={$p['id']} no encontrado", 'TRIP_NOT_FOUND');
        }

        $routeModel = new Route();
        $pointModel = new Point();
        $tagModel   = new TripTag();

        $routes = $routeModel->getByTripId($trip['id']);
        $pois   = $pointModel->getAll($trip['id']);
        $tags   = $tagModel->getByTripId($trip['id']);

        $includeGeojson = (bool)($p['include_geojson'] ?? false);

        $routesOut = [];
        foreach ($routes as $r) {
            $out = [
                'id'             => (int)$r['id'],
                'name'           => $r['name'],
                'transport_type' => $r['transport_type'],
                'distance_meters'=> (int)$r['distance_meters'],
                'distance_km'    => round((int)$r['distance_meters'] / 1000, 2),
                'is_round_trip'  => (bool)$r['is_round_trip'],
                'start_datetime' => $r['start_datetime'] ?? null,
                'end_datetime'   => $r['end_datetime'] ?? null,
                'color'          => $r['color'],
                'description'    => $r['description'],
            ];
            if ($includeGeojson) {
                $out['geojson_data'] = $r['geojson_data'];
            }
            $routesOut[] = $out;
        }

        $poisOut = [];
        foreach ($pois as $poi) {
            $poisOut[] = [
                'id'         => (int)$poi['id'],
                'title'      => $poi['title'],
                'type'       => $poi['type'],
                'latitude'   => (float)$poi['latitude'],
                'longitude'  => (float)$poi['longitude'],
                'visit_date' => $poi['visit_date'],
                'image_path' => $poi['image_path'],
                'description'=> $poi['description'],
            ];
        }

        return [
            'trip' => [
                'id'          => (int)$trip['id'],
                'title'       => $trip['title'],
                'description' => $trip['description'],
                'start_date'  => $trip['start_date'],
                'end_date'    => $trip['end_date'],
                'status'      => $trip['status'],
                'color_hex'   => $trip['color_hex'],
                'tags'        => $tags,
                'routes'      => $routesOut,
                'pois'        => $poisOut,
            ],
        ];
    }

    public static function createTrip(array $p): array
    {
        $tripModel = new Trip();

        $data = [
            'title'       => trim($p['title']),
            'description' => isset($p['description']) ? trim($p['description']) : null,
            'start_date'  => $p['start_date']  ?? null,
            'end_date'    => $p['end_date']    ?? null,
            'color_hex'   => $p['color_hex']   ?? '#3388ff',
            'status'      => $p['status']      ?? 'draft',
        ];

        $errors = $tripModel->validate($data);
        if (!empty($errors)) {
            throw new ToolException('Datos de viaje inválidos', 'INVALID_INPUT', -32602, ['fieldErrors' => $errors]);
        }

        $id = $tripModel->create($data);
        if (!$id) {
            throw new ToolException('No se pudo crear el viaje en la base de datos', 'DB_ERROR');
        }

        $tags = $p['tags'] ?? [];
        if (!empty($tags)) {
            $tagModel = new TripTag();
            $tagModel->sync((int)$id, $tags);
        }

        McpLogger::info("create_trip OK", ['id' => $id, 'title' => $data['title']]);

        return [
            'id'      => (int)$id,
            'title'   => $data['title'],
            'status'  => $data['status'],
            'admin_url' => '/admin/trip_form.php?id=' . $id,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function tripSummary(array $row, Trip $tripModel): array
    {
        $id = (int)$row['id'];
        return [
            'id'          => $id,
            'title'       => $row['title'],
            'description' => $row['description'],
            'start_date'  => $row['start_date'],
            'end_date'    => $row['end_date'],
            'status'      => $row['status'],
            'color_hex'   => $row['color_hex'],
            'route_count' => $tripModel->countRoutes($id),
            'poi_count'   => $tripModel->countPoints($id),
        ];
    }
}
