<?php
/**
 * MCP Tools: Location
 * search_location
 */

final class LocationTools
{
    public static function register(Dispatcher $d): void
    {
        $d->register('search_location',
            'Busca coordenadas de un lugar por nombre usando OpenStreetMap Nominatim. ' .
            'Devuelve hasta 5 candidatos ordenados por relevancia, cada uno con lat/lng y nombre completo. ' .
            'Si el array results está vacío (lugar muy específico, nombre en otro idioma, etc.) ' .
            'usa tu herramienta WebSearch para buscar las coordenadas en internet y luego llama a ' .
            'esta tool con un nombre más preciso, o pasa las coordenadas directamente a create_poi.',
        [
            'type'       => 'object',
            'required'   => ['query'],
            'properties' => [
                'query' => [
                    'type'        => 'string',
                    'minLength'   => 2,
                    'maxLength'   => 200,
                    'description' => 'Nombre del lugar, dirección o punto de interés a buscar.',
                ],
                'limit' => [
                    'type'    => 'integer',
                    'minimum' => 1,
                    'maximum' => 10,
                    'description' => 'Número máximo de resultados. Por defecto 5.',
                ],
            ],
            'additionalProperties' => false,
        ], [self::class, 'searchLocation']);
    }

    public static function searchLocation(array $p): array
    {
        $query = trim($p['query'] ?? '');
        if (strlen($query) < 2) {
            throw new ToolException('La búsqueda debe tener al menos 2 caracteres', 'INVALID_INPUT', -32602);
        }

        $limit   = min((int)($p['limit'] ?? 5), 10);
        $results = Geocoder::forwardLookup($query, $limit);

        $response = [
            'query'   => $query,
            'results' => $results,
            'count'   => count($results),
            'source'  => 'nominatim',
        ];

        if (empty($results)) {
            $response['hint'] = 'No se encontraron resultados en Nominatim. Prueba con un nombre más específico o usa WebSearch para obtener las coordenadas.';
        }

        return $response;
    }
}
