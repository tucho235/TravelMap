# Ideas para el futuro

## CLI (`bin/travelmap.php`)

La estructura `<modulo> <operacion>` está pensada para crecer. Posibles adiciones:

### `backup restore`
Restaurar un backup `.zip` o `.json` directamente desde servidor, sin pasar por la UI web. Útil en disaster recovery.

### `backup prune`
Eliminar backups viejos. Opciones posibles: `--keep=10` (conservar los N más recientes) o `--older-than=30d`.

### `import gpx|exif|flights <archivo>`
Correr los importadores existentes desde CLI. Útil para migraciones masivas o carga inicial de datos sin pasar por el navegador.

### `images orphans`
Listar (o eliminar con `--delete`) imágenes en disco que no tienen registro en la base de datos.

### `images missing`
Listar registros que apuntan a imágenes que no existen en disco.

### `images reprocess`
Regenerar thumbnails o rehacer procesamiento EXIF sobre imágenes ya almacenadas.

### `db check`
Verificar integridad: claves foráneas huérfanas, inconsistencias entre DB y disco, etc.

### `db migrate`
Correr migraciones pendientes (si en algún momento se implementa un sistema de migraciones).

### `users create`
Crear un usuario administrador sin pasar por la web. Esencial para instalaciones limpias.

### `users reset-password`
Resetear la contraseña de un usuario directamente desde el servidor.

---

## Timeline de viaje (`trip.php`)

### Rutas en el timeline
Actualmente el timeline solo muestra POIs ordenados por `visit_date`. Agregar rutas daría una narrativa completa: *"Día 1: volé de Buenos Aires a Madrid → Día 2: visité el Prado → Día 3: tren a Barcelona..."*

**Requiere migración de DB:** agregar a la tabla `routes`:
- `name VARCHAR(200) NULL` — nombre opcional (ej. "Vuelo BUE→MAD"). También útil en el admin para distinguir rutas del mismo tipo en un viaje.
- `travel_date DATE NULL` — fecha de viaje (distinta de `created_at`). Sin esta columna no hay forma de intercalar rutas y POIs cronológicamente.

Con ambos campos, el timeline puede mezclar POIs y rutas ordenados por fecha, mostrando tipo de transporte, nombre y distancia para las rutas.

### Itinerarios / días dentro de un viaje
Permitir organizar las rutas y POIs de un viaje en sub-grupos con nombre: "Día 1", "Paseo por Córdoba", "Viaje a La Rioja", etc. El timeline de `trip.php` mostraría tabs en lugar de una lista plana.

**Diseño clave:** `itinerary_id` nullable en `routes` y `points_of_interest` → los viajes sin itinerarios siguen funcionando exactamente igual que hoy.

**Migración de DB:**
- Nueva tabla `itineraries` — `id, trip_id, name, date (nullable), sort_order`
- Agregar `itinerary_id NULL` a `routes`
- Agregar `itinerary_id NULL` a `points_of_interest`

**Fases de implementación:**

*Fase 1 — Modelo de datos:* migración + modelo `Itinerary`. Sin cambios de UI. Reversible.

*Fase 2 — Admin:* sección para gestionar itinerarios dentro de un viaje + selector de itinerario en los formularios de ruta y POI. Archivos a tocar: `point_form.php`, `route_handler.php`, nuevo panel de itinerarios.

*Fase 3 — trip.php:* si el viaje tiene itinerarios → tabs con filtrado del mapa por tab. Si no → flat timeline como hoy. Esta es la parte más visible.

---

## MCP (Model Context Protocol)

Exponer TravelMap como un servidor MCP para interactuar con los viajes conversacionalmente desde Claude u otro LLM.

**Tools candidatas:**
- `list_trips` — listar viajes con filtros (fecha, tags)
- `get_trip` — detalle de un viaje con rutas y POIs
- `create_trip` — crear viaje con título, fechas, descripción
- `create_poi` — agregar un POI a un viaje con coordenadas
- `search_trips` — buscar por título, tag o fechas

**Casos de uso:**
- *"Creá un viaje a Japón del 10 al 20 de mayo"*
- *"Agregá un POI en el Coliseo al viaje de Roma"*
- *"¿Cuántos km recorrí en 2024?"*

**Decisiones de diseño a resolver antes de implementar:**
- ¿Corre en el mismo servidor que la app o es independiente?
- ¿Solo lectura primero, escritura después?
- Autenticación: local (sin auth) vs. expuesto a Claude.ai (requiere API key/token)

**Complejidad:** baja-media. El MCP sería un servidor liviano (PHP CLI o Node) que reutiliza los mismos modelos que ya usa la app.
