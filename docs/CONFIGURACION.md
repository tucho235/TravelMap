# Sistema de Configuración - TravelMap

## Descripción

El sistema de configuración permite personalizar opciones globales de TravelMap desde el panel de administración, sin necesidad de editar archivos de código. Todas las configuraciones se almacenan en la base de datos y se aplican automáticamente en toda la aplicación.

## Instalación

### Para Instalaciones Nuevas

Si estás instalando TravelMap desde cero, simplemente ejecuta el archivo `database.sql` que ya incluye la tabla de configuraciones.

### Para Instalaciones Existentes

Si ya tienes TravelMap instalado y quieres agregar el sistema de configuración:

1. Ejecuta el script de migración en phpMyAdmin o tu cliente MySQL:
   ```
   install/migration_settings.sql
   ```

2. Este script creará:
   - La tabla `settings` con su estructura
   - 11 configuraciones por defecto con valores predeterminados

## Configuraciones Disponibles

### Configuración General

#### Tamaño Máximo de Carga (MB)
- **Clave**: `max_upload_size`
- **Tipo**: Número (bytes internamente)
- **Por defecto**: 8 MB (8388608 bytes)
- **Descripción**: Define el tamaño máximo permitido para subir imágenes de puntos de interés
- **Rango recomendado**: 1 MB - 100 MB

#### Duración de Sesión (horas)
- **Clave**: `session_lifetime`
- **Tipo**: Número (segundos internamente)
- **Por defecto**: 24 horas (86400 segundos)
- **Descripción**: Tiempo que permanecerá activa una sesión de usuario antes de expirar
- **Rango recomendado**: 1 hora - 720 horas (30 días)

#### Zona Horaria
- **Clave**: `timezone`
- **Tipo**: String
- **Por defecto**: `America/Argentina/Buenos_Aires`
- **Descripción**: Zona horaria utilizada para fechas y horas en todo el sistema
- **Opciones**: Incluye las zonas horarias más comunes de todo el mundo

### Configuración del Mapa

#### Habilitar Agrupación de Puntos (Clustering)
- **Clave**: `map_cluster_enabled`
- **Tipo**: Boolean
- **Por defecto**: `true`
- **Descripción**: Activa/desactiva el agrupamiento automático de puntos cercanos en el mapa público
- **Impacto**: Si está desactivado, todos los marcadores se mostrarán individualmente

#### Radio Máximo del Cluster (píxeles)
- **Clave**: `map_cluster_max_radius`
- **Tipo**: Número
- **Por defecto**: 30 píxeles
- **Descripción**: Distancia máxima en píxeles para agrupar puntos en un cluster
- **Rango recomendado**: 10 - 200 píxeles
- **Impacto**: Valores más altos = clusters más grandes con más puntos agrupados

#### Desactivar Clustering en Zoom
- **Clave**: `map_cluster_disable_at_zoom`
- **Tipo**: Número
- **Por defecto**: 15
- **Descripción**: Nivel de zoom donde se desactiva el clustering y se muestran todos los puntos individuales
- **Rango recomendado**: 1 - 20
- **Impacto**: Valores más bajos = los puntos se separan antes al hacer zoom

### Colores de Rutas por Tipo de Transporte

Personaliza los colores utilizados para cada tipo de transporte en el mapa público y el editor de rutas.

#### Color para Rutas en Avión ✈️
- **Clave**: `transport_color_plane`
- **Por defecto**: `#FF4444` (Rojo)

#### Color para Rutas en Barco 🚢
- **Clave**: `transport_color_ship`
- **Por defecto**: `#00AAAA` (Cyan)

#### Color para Rutas en Auto 🚗
- **Clave**: `transport_color_car`
- **Por defecto**: `#4444FF` (Azul)

#### Color para Rutas en Tren 🚂
- **Clave**: `transport_color_train`
- **Por defecto**: `#FF8800` (Naranja)

#### Color para Rutas Caminando 🚶
- **Clave**: `transport_color_walk`
- **Por defecto**: `#44FF44` (Verde)

## Uso desde el Panel de Administración

1. Inicia sesión en el panel de administración
2. Haz clic en **"Configuración"** en el menú de navegación
3. Modifica las opciones que desees cambiar
4. Haz clic en **"Guardar Configuración"**
5. Los cambios se aplicarán inmediatamente:
   - Los colores se actualizarán al recargar el mapa público
   - El clustering se aplicará con los nuevos valores
   - El tamaño de carga y sesión afectarán las próximas operaciones

## Arquitectura Técnica

### Base de Datos

La tabla `settings` almacena todas las configuraciones:

```sql
CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json'),
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Modelo Settings.php

El modelo `Settings` proporciona métodos para:
- `get($key, $default)`: Obtiene una configuración
- `set($key, $value, $type, $description)`: Establece una configuración
- `getAll()`: Obtiene todas las configuraciones
- `getAllAsArray()`: Obtiene configuraciones como array asociativo
- `updateMultiple($settings)`: Actualiza múltiples configuraciones
- `getTransportColors()`: Obtiene los colores de transporte
- `getMapConfig()`: Obtiene la configuración del mapa

### Integración Frontend

#### API de Configuración (`api/get_config.php`)

Endpoint JSON que devuelve las configuraciones necesarias para el cliente:

```json
{
  "success": true,
  "data": {
    "map": {
      "clusterEnabled": true,
      "maxClusterRadius": 30,
      "disableClusteringAtZoom": 15
    },
    "transportColors": {
      "plane": "#FF4444",
      "ship": "#00AAAA",
      "car": "#4444FF",
      "train": "#FF8800",
      "walk": "#44FF44"
    }
  }
}
```

#### Archivos JavaScript

Los archivos `public_map.js` y `trip_map.js` cargan la configuración automáticamente:

```javascript
// Cargar configuración al inicializar
loadConfig().always(function() {
    initMap();
    loadData();
});
```

### Flujo de Carga de Configuración

1. El usuario accede al mapa público o al editor de rutas
2. JavaScript solicita la configuración vía AJAX a `/api/get_config.php`
3. El endpoint consulta la base de datos usando el modelo `Settings`
4. Se devuelve la configuración en formato JSON
5. JavaScript aplica los valores recibidos al mapa y las rutas
6. Si falla la carga, se usan valores por defecto predefinidos

## Ventajas del Sistema

1. **Sin Editar Código**: Los administradores pueden personalizar la aplicación sin tocar archivos PHP o JavaScript
2. **Persistencia**: Las configuraciones se guardan en la base de datos
3. **Valores por Defecto**: Si falla la carga, se usan valores seguros predefinidos
4. **Caché en Memoria**: El modelo cachea las configuraciones para evitar consultas repetidas
5. **Tipado Fuerte**: Los valores se convierten automáticamente al tipo correcto (string, number, boolean, json)
6. **Extensible**: Fácil agregar nuevas configuraciones sin modificar la estructura

## Agregar Nuevas Configuraciones

### 1. Agregar en la Base de Datos

```sql
INSERT INTO settings (setting_key, setting_value, setting_type, description)
VALUES ('mi_nueva_opcion', 'valor_default', 'string', 'Descripción de la opción');
```

### 2. Agregar en la Interfaz de Administración

Edita `admin/settings.php` y agrega el campo correspondiente en el formulario.

### 3. Usar en la Aplicación

#### En PHP:
```php
$settingsModel = new Settings($conn);
$miOpcion = $settingsModel->get('mi_nueva_opcion', 'valor_default');
```

#### En JavaScript (si es necesario en el frontend):
1. Agregar al endpoint `api/get_config.php`
2. Usar en el archivo JS correspondiente

## Consideraciones de Seguridad

- Solo los usuarios autenticados pueden acceder a `/admin/settings.php`
- Las configuraciones públicas están disponibles vía API sin autenticación (solo lectura)
- Los valores se validan según su tipo antes de guardarse
- Se usan consultas preparadas (PDO) para prevenir SQL injection
- Los colores se validan en el frontend con inputs tipo `color`

## Respaldo y Restauración

### Hacer Respaldo de Configuración

```sql
SELECT * FROM settings INTO OUTFILE '/tmp/settings_backup.sql';
```

O exportar desde phpMyAdmin la tabla `settings`.

### Restaurar Configuración

```sql
TRUNCATE TABLE settings;
-- Luego importar el archivo de respaldo
```

## Troubleshooting

### La configuración no se aplica

1. Verifica que la tabla `settings` existe y tiene datos
2. Revisa la consola del navegador para errores de JavaScript
3. Comprueba que `/api/get_config.php` devuelve JSON válido
4. Limpia la caché del navegador

### Los colores no cambian

1. Recarga la página del mapa público (F5 o Ctrl+R)
2. Verifica que guardaste los cambios en el panel de configuración
3. Revisa la consola del navegador

### Error al guardar configuración

1. Verifica los permisos de la base de datos
2. Revisa los logs de PHP
3. Comprueba que el modelo `Settings.php` está cargado correctamente

## Referencias

- Modelo: [src/models/Settings.php](../src/models/Settings.php)
- Interfaz Admin: [admin/settings.php](../admin/settings.php)
- API: [api/get_config.php](../api/get_config.php)
- Migración: [install/migration_settings.sql](../install/migration_settings.sql)
- Base de datos: [database.sql](../database.sql)
