# Resumen de Cambios - Sistema de Configuración

## Fecha: 2025-12-28

## Descripción General

Se ha implementado un sistema completo de configuración que permite personalizar opciones globales de TravelMap desde el panel de administración, sin necesidad de editar código fuente.

## Archivos Nuevos Creados

### Base de Datos y Migración
1. **install/migration_settings.sql**
   - Script de migración para instalaciones existentes
   - Crea la tabla `settings`
   - Inserta 11 configuraciones por defecto

### Modelo
2. **src/models/Settings.php**
   - Modelo completo para gestión de configuraciones
   - Métodos: get, set, getAll, getAllAsArray, updateMultiple
   - Métodos especializados: getTransportColors, getMapConfig
   - Sistema de caché en memoria
   - Conversión automática de tipos (string, number, boolean, json)

### API
3. **api/get_config.php**
   - Endpoint JSON para obtener configuraciones del cliente
   - Devuelve configuraciones del mapa y colores de transporte
   - Usado por JavaScript para cargar configuración dinámica

### Panel de Administración
4. **admin/settings.php**
   - Interfaz completa para editar configuraciones
   - Organizado en 3 secciones:
     - Configuración General (upload, sesión, zona horaria)
     - Configuración del Mapa (clustering)
     - Colores de Transporte (5 tipos)
   - Validación de formulario
   - Conversión automática de unidades (MB a bytes, horas a segundos)
   - Selectores de color con vista previa

### Documentación
5. **docs/CONFIGURACION.md**
   - Documentación completa del sistema de configuración
   - Guía de instalación y migración
   - Descripción de todas las configuraciones disponibles
   - Arquitectura técnica
   - Troubleshooting
   - Cómo agregar nuevas configuraciones

## Archivos Modificados

### Base de Datos
1. **database.sql**
   - Agregada tabla `settings` con estructura completa
   - Agregados 11 registros de configuración por defecto
   - Índice en `setting_key` para búsquedas rápidas

### Configuración
2. **config/config.php**
   - Ahora carga configuraciones desde la base de datos
   - Usa el modelo `Settings` para obtener valores dinámicos
   - Mantiene valores por defecto como fallback
   - Aplica configuraciones: timezone, max_upload_size, session_lifetime

### Interfaz de Administración
3. **includes/header.php**
   - Agregado nuevo ítem de menú "Configuración"
   - Ícono de engranaje (Bootstrap Icons)
   - Resaltado activo cuando se está en settings.php

### JavaScript - Mapa Público
4. **assets/js/public_map.js**
   - Variable global `appConfig` para almacenar configuración
   - Función `loadConfig()` que carga configuración desde API
   - Variable `transportConfig` ahora es `let` (era `const`) para permitir actualización
   - `initMap()` usa configuración dinámica para clustering:
     - `clusterEnabled`: activa/desactiva clustering
     - `maxClusterRadius`: radio del cluster
     - `disableClusteringAtZoom`: nivel de zoom para desactivar
   - Actualización de colores de transporte desde configuración
   - Inicialización secuencial: loadConfig() → initMap() → loadData()
   - Fallback a valores por defecto si falla la carga

### JavaScript - Editor de Rutas
5. **assets/js/trip_map.js**
   - Variable global `appConfig` para configuración
   - Función `loadConfig()` similar a public_map.js
   - Variable `transportColors` ahora es `let` (era `const`)
   - Colores se actualizan desde la configuración del servidor
   - Inicialización: loadConfig() → initMap()
   - Valores por defecto si falla la carga

### Documentación
6. **README.md**
   - Actualizada sección de características:
     - Panel de Configuración agregado a "Panel de Administración"
     - Menciona clustering configurable
     - Menciona colores personalizables
   - Actualizada arquitectura: modelo Settings agregado
   - Actualizada guía de uso: paso opcional de configuración
   - Mejoradas descripciones de características existentes

## Tabla de Base de Datos

### Estructura de `settings`

```sql
CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json'),
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
);
```

### Configuraciones Por Defecto

| Clave | Valor | Tipo | Descripción |
|-------|-------|------|-------------|
| max_upload_size | 8388608 | number | Tamaño máximo de carga (8MB) |
| session_lifetime | 86400 | number | Duración de sesión (24h) |
| timezone | America/Argentina/Buenos_Aires | string | Zona horaria |
| map_cluster_enabled | true | boolean | Habilitar clustering |
| map_cluster_max_radius | 30 | number | Radio máximo del cluster |
| map_cluster_disable_at_zoom | 15 | number | Zoom para desactivar cluster |
| transport_color_plane | #FF4444 | string | Color avión (rojo) |
| transport_color_ship | #00AAAA | string | Color barco (cyan) |
| transport_color_car | #4444FF | string | Color auto (azul) |
| transport_color_train | #FF8800 | string | Color tren (naranja) |
| transport_color_walk | #44FF44 | string | Color caminando (verde) |

## Flujo de Funcionamiento

### Carga de Configuración en PHP

1. Se incluye `config/config.php`
2. Se carga `config/db.php` (conexión)
3. Se instancia el modelo `Settings`
4. Se obtienen las configuraciones necesarias
5. Se aplican (timezone, límites de upload, sesión)

### Carga de Configuración en JavaScript

1. El usuario accede al mapa público o editor
2. JavaScript ejecuta `loadConfig()`
3. AJAX a `/api/get_config.php`
4. API consulta la BD usando modelo Settings
5. Devuelve JSON con configuración
6. JavaScript actualiza variables globales
7. Se inicializa el mapa con valores configurados
8. Si falla, usa valores por defecto

## Características Implementadas

### ✅ Configuración General
- [x] Tamaño máximo de carga (1-100 MB)
- [x] Tiempo de vida de sesión (1-720 horas)
- [x] Zona horaria (20+ zonas disponibles)

### ✅ Configuración del Mapa
- [x] Habilitar/deshabilitar clustering
- [x] Radio máximo del cluster (10-200 px)
- [x] Nivel de zoom para desactivar (1-20)

### ✅ Colores de Transporte
- [x] Color para avión (✈️)
- [x] Color para barco (🚢)
- [x] Color para auto (🚗)
- [x] Color para tren (🚂)
- [x] Color para caminando (🚶)

### ✅ Infraestructura
- [x] Modelo Settings con caché
- [x] API REST para configuración
- [x] Interfaz de administración
- [x] Integración con mapas
- [x] Sistema de fallback
- [x] Documentación completa

## Instrucciones de Migración

### Para Instalaciones Nuevas
Simplemente ejecutar `database.sql` que ya incluye todo.

### Para Instalaciones Existentes

1. Ejecutar en phpMyAdmin:
   ```
   install/migration_settings.sql
   ```

2. Verificar que se creó la tabla y los datos:
   ```sql
   SELECT COUNT(*) FROM settings;
   -- Debe devolver 11
   ```

3. Listo! La configuración ya está disponible en el menú admin.

## Testing Recomendado

1. **Crear Tabla**
   - Ejecutar migration_settings.sql
   - Verificar 11 registros creados

2. **Panel de Administración**
   - Acceder a /admin/settings.php
   - Cambiar tamaño de upload a 10MB
   - Cambiar color de avión a #00FF00
   - Guardar
   - Verificar mensaje de éxito

3. **Mapa Público**
   - Abrir mapa público
   - Abrir consola del navegador
   - Verificar mensaje "Configuración cargada"
   - Verificar que rutas de avión usan el nuevo color

4. **Clustering**
   - Desactivar clustering en configuración
   - Recargar mapa público
   - Verificar que todos los puntos se muestran individuales

5. **Editor de Rutas**
   - Abrir editor de rutas
   - Dibujar una ruta tipo "plane"
   - Verificar que usa el color configurado

## Compatibilidad

- **PHP**: 8.0+
- **MySQL**: 5.7+ / MariaDB 10.3+
- **Navegadores**: Chrome, Firefox, Safari, Edge (versiones recientes)
- **Dependencias**: No se agregaron nuevas dependencias externas

## Seguridad

- ✅ Solo usuarios autenticados pueden modificar configuración
- ✅ Consultas preparadas (PDO) previenen SQL injection
- ✅ Validación de tipos de datos
- ✅ Valores por defecto seguros
- ✅ API pública solo en modo lectura

## Performance

- ✅ Caché en memoria del modelo Settings
- ✅ Una sola consulta para obtener todas las configuraciones
- ✅ Índice en setting_key para búsquedas rápidas
- ✅ Carga asíncrona en JavaScript (no bloquea renderizado)

## Próximos Pasos / Mejoras Futuras (Opcionales)

1. Agregar más zonas horarias
2. Validación avanzada de colores (formato hex)
3. Preview en vivo de cambios de color
4. Importar/exportar configuraciones
5. Historial de cambios de configuración
6. Restablecer valores por defecto con un botón
7. Configuración por usuario (además de global)

## Notas Importantes

- Los cambios de configuración se aplican inmediatamente
- Los colores requieren recargar la página del mapa para verse
- El clustering se puede desactivar completamente si afecta performance
- Todos los valores tienen fallbacks seguros
- La documentación está en español
- El código está bien comentado

## Autor

Sistema de configuración implementado el 28 de diciembre de 2025
Compatible con TravelMap v1.0+
