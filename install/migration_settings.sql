-- ============================================
-- Script de Migración para Sistema de Configuración
-- ============================================
-- Este script agrega la tabla de configuraciones y sus datos iniciales
-- a una instalación existente de TravelMap
-- Fecha: 2025-12-28
-- ============================================

USE travelmap;

-- ============================================
-- Tabla: settings
-- Descripción: Almacena configuraciones del sistema
-- ============================================
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Datos iniciales para settings
-- ============================================
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('max_upload_size', '8388608', 'number', 'Tamaño máximo de carga en bytes (8MB por defecto)'),
('session_lifetime', '86400', 'number', 'Tiempo de vida de la sesión en segundos (24 horas por defecto)'),
('timezone', 'America/Argentina/Buenos_Aires', 'string', 'Zona horaria del sistema'),
('map_cluster_enabled', 'true', 'boolean', 'Habilitar clustering de puntos en el mapa público'),
('map_cluster_max_radius', '30', 'number', 'Radio máximo del cluster en píxeles'),
('map_cluster_disable_at_zoom', '15', 'number', 'Nivel de zoom donde se desactiva el clustering'),
('transport_color_plane', '#FF4444', 'string', 'Color para rutas en avión'),
('transport_color_ship', '#00AAAA', 'string', 'Color para rutas en barco'),
('transport_color_car', '#4444FF', 'string', 'Color para rutas en auto'),
('transport_color_train', '#FF8800', 'string', 'Color para rutas en tren'),
('transport_color_walk', '#44FF44', 'string', 'Color para rutas caminando')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ============================================
-- Verificación
-- ============================================
SELECT 'Migración completada exitosamente' AS resultado;
SELECT COUNT(*) AS total_configuraciones FROM settings;
