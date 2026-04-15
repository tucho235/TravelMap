<?php
/**
 * Migration 021: Polymorphic Links Table
 *
 * Consolida poi_links y route_links en una única tabla polimórfica `links`.
 * Agrega la columna entity_type (ENUM 'poi','route','trip') y entity_id en lugar
 * de las FK específicas por entidad. Copia los datos existentes y elimina las
 * tablas redundantes.
 */
class Migration_021_polymorphic_links
{
    public static function id(): string
    {
        return '021_polymorphic_links';
    }

    public static function description(): string
    {
        return 'Tabla links polimórfica: consolida poi_links y route_links';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW TABLES LIKE 'links'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        // 1. Crear tabla links
        $db->exec("
            CREATE TABLE IF NOT EXISTS links (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_type ENUM('poi', 'route', 'trip') NOT NULL,
                entity_id   INT UNSIGNED NOT NULL,
                link_type   ENUM(
                                'website', 'google_maps', 'instagram', 'facebook',
                                'twitter', 'tripadvisor', 'booking', 'airbnb',
                                'youtube', 'wikipedia', 'google_photos', 'other'
                            ) NOT NULL DEFAULT 'website',
                url         VARCHAR(500) NOT NULL,
                label       VARCHAR(100) DEFAULT NULL,
                sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_entity (entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. Copiar links de POIs si la tabla existe
        $stmt = $db->query("SHOW TABLES LIKE 'poi_links'");
        if ($stmt->fetchColumn()) {
            $db->exec("
                INSERT INTO links (entity_type, entity_id, link_type, url, label, sort_order, created_at)
                SELECT 'poi', poi_id, link_type, url, label, sort_order, created_at
                FROM poi_links
            ");
        }

        // 3. Copiar links de rutas si la tabla existe
        $stmt = $db->query("SHOW TABLES LIKE 'route_links'");
        if ($stmt->fetchColumn()) {
            $db->exec("
                INSERT INTO links (entity_type, entity_id, link_type, url, label, sort_order, created_at)
                SELECT 'route', route_id, link_type, url, label, sort_order, created_at
                FROM route_links
            ");
        }

        // 4. Eliminar tablas redundantes
        $db->exec("DROP TABLE IF EXISTS poi_links");
        $db->exec("DROP TABLE IF EXISTS route_links");
    }
}
