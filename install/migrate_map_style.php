<?php
/**
 * Migración: Agregar configuración de estilo de mapa
 * 
 * Ejecuta este script UNA SOLA VEZ desde el navegador:
 * http://localhost/TravelMap/install/migrate_map_style.php
 * 
 * Después de ejecutarlo, elimina o protege este archivo
 */

require_once __DIR__ . '/../config/db.php';

// Configuración de salida
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración - Estilo de Mapa</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .settings-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .settings-table th,
        .settings-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .settings-table th {
            background-color: #4CAF50;
            color: white;
        }
        .settings-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .style-preview {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        .style-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            width: 180px;
            text-align: center;
        }
        .style-card h4 {
            margin: 10px 0 5px;
        }
        .style-card p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗺️ Migración de Estilo de Mapa</h1>
        
        <div class="info">
            <strong>ℹ️ Información:</strong> Este script agregará la configuración para seleccionar diferentes estilos de mapa base (colores, tema oscuro, etc.).
        </div>

<?php

try {
    // Obtener conexión
    $conn = getDB();
    
    echo "<h2>Verificando configuración existente...</h2>";
    
    // Verificar si ya existe
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = 'map_style'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo '<div class="info">';
        echo '<strong>✓ La configuración ya existe</strong><br>';
        echo 'No es necesario ejecutar esta migración nuevamente.';
        echo '</div>';
        
        // Mostrar configuración actual
        $stmt = $conn->prepare("SELECT setting_key, setting_value, setting_type, description FROM settings WHERE setting_key = 'map_style'");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<h3>Configuración Actual:</h3>';
        echo '<table class="settings-table">';
        echo '<tr><th>Clave</th><th>Valor</th><th>Tipo</th><th>Descripción</th></tr>';
        foreach ($settings as $setting) {
            echo '<tr>';
            echo '<td><code>' . htmlspecialchars($setting['setting_key']) . '</code></td>';
            echo '<td><strong>' . htmlspecialchars($setting['setting_value']) . '</strong></td>';
            echo '<td>' . htmlspecialchars($setting['setting_type']) . '</td>';
            echo '<td>' . htmlspecialchars($setting['description']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
    } else {
        echo "<h2>Agregando nueva configuración...</h2>";
        
        // Insertar configuración
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            'map_style',
            'voyager',
            'string',
            'Map basemap style (positron, voyager, dark-matter, osm-liberty)'
        ]);
        
        echo '<div class="success">';
        echo '<strong>✓ Migración completada exitosamente</strong><br>';
        echo 'Se agregó la configuración de estilo de mapa con el valor por defecto: <strong>voyager</strong> (estilo colorido con verdes, marrones y agua azul).';
        echo '</div>';
        
        // Mostrar configuración insertada
        $stmt = $conn->prepare("SELECT setting_key, setting_value, setting_type, description FROM settings WHERE setting_key = 'map_style'");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<h3>Configuración Agregada:</h3>';
        echo '<table class="settings-table">';
        echo '<tr><th>Clave</th><th>Valor</th><th>Tipo</th><th>Descripción</th></tr>';
        foreach ($settings as $setting) {
            echo '<tr>';
            echo '<td><code>' . htmlspecialchars($setting['setting_key']) . '</code></td>';
            echo '<td><strong>' . htmlspecialchars($setting['setting_value']) . '</strong></td>';
            echo '<td>' . htmlspecialchars($setting['setting_type']) . '</td>';
            echo '<td>' . htmlspecialchars($setting['description']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    // Mostrar estilos disponibles
    echo '<h2>Estilos de Mapa Disponibles</h2>';
    echo '<div class="style-preview">';
    
    $styles = [
        'positron' => ['name' => 'Positron', 'desc' => 'Gris minimalista'],
        'voyager' => ['name' => 'Voyager', 'desc' => 'Colorido: verdes, marrones, azul agua'],
        'dark-matter' => ['name' => 'Dark Matter', 'desc' => 'Tema oscuro'],
        'osm-liberty' => ['name' => 'OSM Liberty', 'desc' => 'Estilo OpenStreetMap clásico']
    ];
    
    foreach ($styles as $key => $style) {
        echo '<div class="style-card">';
        echo '<h4>' . htmlspecialchars($style['name']) . '</h4>';
        echo '<p><code>' . htmlspecialchars($key) . '</code></p>';
        echo '<p>' . htmlspecialchars($style['desc']) . '</p>';
        echo '</div>';
    }
    echo '</div>';
    
    echo '<h2>Próximos pasos</h2>';
    echo '<div class="info">';
    echo '<ol>';
    echo '<li>Accede al panel de administración: <a href="../admin/">Admin Panel</a></li>';
    echo '<li>Ve a <strong>Configuración</strong> en el menú</li>';
    echo '<li>En la pestaña <strong>Mapa</strong>, selecciona el estilo que prefieras</li>';
    echo '<li><strong>¡IMPORTANTE!</strong> Elimina o protege este archivo (<code>install/migrate_map_style.php</code>)</li>';
    echo '</ol>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="error">';
    echo '<strong>❌ Error de base de datos:</strong><br>';
    echo htmlspecialchars($e->getMessage());
    echo '</div>';
} catch (Exception $e) {
    echo '<div class="error">';
    echo '<strong>❌ Error:</strong><br>';
    echo htmlspecialchars($e->getMessage());
    echo '</div>';
}

?>

    </div>
</body>
</html>
