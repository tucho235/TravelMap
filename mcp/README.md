# TravelMap MCP Server

Servidor MCP local para TravelMap. Permite usar un LLM (Claude Desktop, Claude CLI u otro cliente MCP) para buscar, listar y crear Trips, Routes y POIs dictando en lenguaje natural.

**Transporte:** stdio local (proceso lanzado por el cliente MCP).  
**Autenticación:** ninguna — la seguridad proviene de que sólo el usuario del SO puede invocar el proceso.

---

## Requisitos

- **PHP ≥ 7.4** con extensiones: `pdo_mysql`, `gd`, `fileinfo`, `exif`, `curl`
- **MySQL/MariaDB** con el schema de TravelMap instalado
- `config/config.php` y `config/db.php` configurados (copiar de `config/config.example.php`)
- El usuario que ejecuta PHP debe tener permisos de escritura en `uploads/` y `logs/`

---

## Instalación

1. Clonar o actualizar el repo. No se necesita `composer install`.
2. Crear la carpeta de logs:
   ```bash
   mkdir -p logs
   chmod 750 logs
   ```
3. La carpeta `uploads/mcp_temp/` se crea automáticamente la primera vez que se llama a `import_photos_batch`.

---

## Configuración en Claude Desktop

### Linux
Editar `~/.config/Claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "travelmap": {
      "command": "php",
      "args": ["/home/tucho235/Developer/TravelMap/mcp/server.php"],
      "env": {
        "TRAVELMAP_ENV": "local"
      }
    }
  }
}
```

### macOS
Editar `~/Library/Application Support/Claude/claude_desktop_config.json` con el mismo contenido.

### Claude CLI (proyecto local)
Crear `.mcp.json` en la raíz del proyecto:

```json
{
  "mcpServers": {
    "travelmap": {
      "command": "php",
      "args": ["mcp/server.php"]
    }
  }
}
```

---

## Tools disponibles

| Tool | Descripción |
|---|---|
| `list_trips` | Lista viajes con filtro opcional por status y orden |
| `search_trips` | Busca viajes por texto, tag o rango de fechas |
| `get_trip` | Obtiene un viaje completo con rutas, POIs y tags |
| `create_trip` | Crea un nuevo viaje |
| `list_routes` | Lista las rutas de un viaje |
| `create_route` | Crea una ruta desde GeoJSON, CSV BRouter (texto o base64) |
| `list_pois` | Lista los POIs de un viaje |
| `search_pois` | Busca POIs por texto, viaje o tipo |
| `create_poi` | Crea un POI con foto opcional (auto-fill EXIF GPS/fecha) |
| `import_photos_batch` | Analiza hasta 50 fotos: extrae GPS/fecha, interpola, geocodifica |
| `get_stats` | Estadísticas globales (viajes, POIs, km por transporte) |
| `cleanup_temp_batch` | Elimina carpeta temporal de `import_photos_batch` |

---

## Flujos de uso típicos

### Dictado de viajes pasados

```
"Crea un viaje llamado 'Vuelta a España 2023' del 2023-06-01 al 2023-06-30,
 estado publicado, tags: moto, carretera"
→ create_trip

"Lista mis viajes para ver el id"
→ list_trips

"Añade una ruta en moto al viaje 5, desde este CSV de BRouter [adjunto]"
→ create_route (brouter_csv_base64)

"Añade un POI al viaje 5 en estas coordenadas: 41.38, 2.17,
 tipo visita, titulo Sagrada Familia"
→ create_poi

"Te mando 20 fotos de ese viaje, analízalas y dime qué POIs puedo crear"
→ import_photos_batch
  → revisar resultado (coords interpoladas, ciudades sugeridas)
  → create_poi × N usando temp_photo_path
```

### Auto-fill desde EXIF

Si adjuntas una foto con GPS EXIF a `create_poi` sin dar `latitude`/`longitude`, las coordenadas se extraen automáticamente. El campo `auto_filled` en la respuesta indica qué se rellenó.

El campo `suggested_place` siempre incluye la ciudad sugerida por Nominatim (no se usa automáticamente como título — tú decides).

---

## Logs

El servidor escribe en `logs/mcp.log` (chmod 640). Los campos con datos binarios grandes (foto base64, CSV, GeoJSON) se loguean sólo por su longitud en bytes, nunca con el contenido.

```bash
tail -f logs/mcp.log
```

---

## Tests

```bash
# Requiere: php, jq, bash
bash mcp/tests/run_tests.sh

# Mantener los registros de prueba en la DB:
bash mcp/tests/run_tests.sh --keep-data
```

Los tests hacen smoke testing de todos los tools y verifican casos de seguridad (path traversal, base64 inválido, trip_id inexistente, JSON malformado).

---

## Seguridad

El MCP **no expone ningún puerto de red** — es estrictamente local (stdio). Las protecciones a nivel de aplicación incluyen:

- **MIME + GD probe**: las fotos son validadas por `finfo` y decodificadas por GD antes de guardarse (bloquea polyglots y archivos disfrazados)
- **Path traversal**: `basename()` en filenames + `realpath()` con verificación de que el destino está dentro de `uploads/`
- **SQL injection**: PDO prepared statements en todos los modelos; wildcards LIKE escapados con `addcslashes` en PHP
- **Tamaño**: cap pre-decode (14 MB base64) y post-decode (`MAX_UPLOAD_SIZE` de config)
- **Nominatim rate-limit**: máximo 1 request/segundo, compartido con la API web
- **No stack traces al cliente**: excepciones → log interno + mensaje genérico al LLM
- **Carpetas temp protegidas**: `uploads/mcp_temp/.htaccess` bloquea ejecución PHP

---

## Estructura de archivos

```
mcp/
├── server.php          # Entrada stdio (ejecutar este)
├── bootstrap.php       # Carga config, helpers, modelos
├── JsonRpc.php         # Framing NDJSON stdin/stdout
├── Dispatcher.php      # Registro y enrutado de tools
├── Logger.php          # logs/mcp.log con sanitización
├── Schema.php          # Validador JSON Schema minimal
├── tools/
│   ├── TripTools.php   # list_trips, search_trips, get_trip, create_trip
│   ├── RouteTools.php  # list_routes, create_route
│   ├── PoiTools.php    # list_pois, search_pois, create_poi, import_photos_batch
│   └── StatsTools.php  # get_stats
└── tests/
    ├── run_tests.sh
    └── fixtures/
        ├── sample.brouter.csv
        └── tiny.jpg.b64
```

Helpers nuevos en `src/helpers/`:
- `BRouterParser.php` — parser CSV BRouter (extraído de `admin/import_brouter.php`)
- `ExifExtractor.php` — lectura GPS/fecha EXIF + interpolación (extraído de `api/import_exif_upload.php`)
- `Geocoder.php` — reverse geocoding Nominatim con cache DB (extraído de `api/reverse_geocode.php`)
