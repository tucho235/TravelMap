<?php
/**
 * Modelo: Route
 * 
 * Gestiona las operaciones CRUD para rutas de viajes
 */

class Route {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Obtener todas las rutas de un viaje
     * 
     * @param int $trip_id ID del viaje
     * @return array Lista de rutas
     */
    public function getByTripId($trip_id) {
        try {
            $stmt = $this->db->prepare('
                SELECT * FROM routes 
                WHERE trip_id = ? 
                ORDER BY created_at ASC
            ');
            $stmt->execute([$trip_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error al obtener rutas: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener una ruta por ID
     * 
     * @param int $id ID de la ruta
     * @return array|null Datos de la ruta o null si no existe
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare('SELECT * FROM routes WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Error al obtener ruta: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear una nueva ruta
     * 
     * @param array $data Datos de la ruta
     * @return int|false ID de la ruta creada o false si falla
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO routes (trip_id, transport_type, geojson_data, color)
                VALUES (?, ?, ?, ?)
            ');
            
            $result = $stmt->execute([
                $data['trip_id'],
                $data['transport_type'],
                $data['geojson_data'],
                $data['color'] ?? '#3388ff'
            ]);

            return $result ? $this->db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log('Error al crear ruta: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar una ruta existente
     * 
     * @param int $id ID de la ruta
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     */
    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare('
                UPDATE routes 
                SET transport_type = ?, geojson_data = ?, color = ?
                WHERE id = ?
            ');
            
            return $stmt->execute([
                $data['transport_type'],
                $data['geojson_data'],
                $data['color'] ?? '#3388ff',
                $id
            ]);
        } catch (PDOException $e) {
            error_log('Error al actualizar ruta: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar una ruta
     * 
     * @param int $id ID de la ruta
     * @return bool True si se eliminó correctamente
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare('DELETE FROM routes WHERE id = ?');
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log('Error al eliminar ruta: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar todas las rutas de un viaje
     * 
     * @param int $trip_id ID del viaje
     * @return bool True si se eliminaron correctamente
     */
    public function deleteByTripId($trip_id) {
        try {
            $stmt = $this->db->prepare('DELETE FROM routes WHERE trip_id = ?');
            return $stmt->execute([$trip_id]);
        } catch (PDOException $e) {
            error_log('Error al eliminar rutas del viaje: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Guardar múltiples rutas desde GeoJSON
     * 
     * @param int $trip_id ID del viaje
     * @param array $routes_data Array de rutas con datos
     * @return bool True si se guardó correctamente
     */
    public function saveMultipleRoutes($trip_id, $routes_data) {
        try {
            // Eliminar rutas existentes del viaje
            $this->deleteByTripId($trip_id);
            
            // Insertar nuevas rutas
            foreach ($routes_data as $route) {
                $this->create([
                    'trip_id' => $trip_id,
                    'transport_type' => $route['transport_type'] ?? 'car',
                    'geojson_data' => $route['geojson_data'],
                    'color' => $route['color'] ?? $this->getColorByTransport($route['transport_type'] ?? 'car')
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Error al guardar múltiples rutas: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener color según tipo de transporte
     * 
     * @param string $transport_type Tipo de transporte
     * @return string Color hexadecimal
     */
    public static function getColorByTransport($transport_type) {
        $colors = [
            'plane' => '#FF4444',    // Rojo
            'car' => '#4444FF',      // Azul
            'walk' => '#44FF44',     // Verde
            'ship' => '#00AAAA',     // Cyan
            'train' => '#FF8800'     // Naranja
        ];
        return $colors[$transport_type] ?? '#3388ff';
    }

    /**
     * Obtener tipos de transporte disponibles
     * 
     * @return array Array asociativo de tipos
     */
    public static function getTransportTypes() {
        return [
            'plane' => 'Avión',
            'car' => 'Automóvil',
            'walk' => 'A pie',
            'ship' => 'Barco',
            'train' => 'Tren'
        ];
    }

    /**
     * Obtener icono según tipo de transporte
     * 
     * @param string $transport_type Tipo de transporte
     * @return string Nombre del icono
     */
    public static function getIconByTransport($transport_type) {
        $icons = [
            'plane' => '✈️',
            'car' => '🚗',
            'walk' => '🚶',
            'ship' => '🚢',
            'train' => '🚆'
        ];
        return $icons[$transport_type] ?? '🚗';
    }
}
