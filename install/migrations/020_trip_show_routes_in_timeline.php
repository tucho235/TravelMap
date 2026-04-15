<?php
/**
 * Migration 020: Trip Show Routes In Timeline
 *
 * Agrega la columna show_routes_in_timeline a la tabla trips.
 * - NULL  → usar la configuración global (trip_timeline_show_routes)
 * - 1     → mostrar rutas en el timeline de este viaje
 * - 0     → ocultar rutas en el timeline de este viaje
 */
class Migration_020_trip_show_routes_in_timeline
{
    public static function id(): string
    {
        return '020_trip_show_routes_in_timeline';
    }

    public static function description(): string
    {
        return 'Agregar show_routes_in_timeline a la tabla trips';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW COLUMNS FROM trips LIKE 'show_routes_in_timeline'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE trips
                ADD COLUMN show_routes_in_timeline TINYINT(1) DEFAULT NULL AFTER status
        ");
    }
}
