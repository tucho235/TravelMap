<?php
/**
 * MCP Tools: Stats
 * get_stats
 */

final class StatsTools
{
    public static function register(Dispatcher $d): void
    {
        $d->register('get_stats', 'Devuelve estadísticas globales: total de viajes, POIs, distancia recorrida y desglose por tipo de transporte.', [
            'type' => 'object',
            'properties' => [
                'unit' => ['type' => 'string', 'enum' => ['km', 'mi']],
            ],
            'additionalProperties' => false,
        ], [self::class, 'getStats']);
    }

    // ──────────────────────────────────────────────────────────────────────────

    public static function getStats(array $p): array
    {
        $unit   = ($p['unit'] ?? 'km') === 'mi' ? 'mi' : 'km';
        $factor = $unit === 'mi' ? 0.000621371 : 0.001; // meters to km or mi

        $db = getDB();

        try {
            // Conteos generales
            $tripCount = (int)$db->query('SELECT COUNT(*) FROM trips')->fetchColumn();
            $poiCount  = (int)$db->query('SELECT COUNT(*) FROM points_of_interest')->fetchColumn();

            // Distancia total y por transporte
            $stmt = $db->query('
                SELECT transport_type,
                       SUM(distance_meters) AS total_meters,
                       COUNT(*)             AS route_count
                FROM routes
                GROUP BY transport_type
                ORDER BY total_meters DESC
            ');
            $byTransport = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalMeters = 0;
            $breakdown   = [];
            foreach ($byTransport as $row) {
                $meters       = (int)$row['total_meters'];
                $totalMeters += $meters;
                $breakdown[]  = [
                    'transport_type' => $row['transport_type'],
                    'route_count'    => (int)$row['route_count'],
                    'distance'       => round($meters * $factor, 1),
                    'unit'           => $unit,
                ];
            }

            // Viaje más reciente
            $latestStmt  = $db->query('SELECT id, title, start_date, end_date FROM trips ORDER BY start_date DESC, id DESC LIMIT 1');
            $latestTrip  = $latestStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            return [
                'trip_count'       => $tripCount,
                'poi_count'        => $poiCount,
                'total_distance'   => round($totalMeters * $factor, 1),
                'unit'             => $unit,
                'by_transport'     => $breakdown,
                'latest_trip'      => $latestTrip,
            ];

        } catch (PDOException $e) {
            McpLogger::error('get_stats DB error: ' . $e->getMessage());
            throw new ToolException('Error al consultar estadísticas', 'DB_ERROR');
        }
    }
}
