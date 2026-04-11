# Importadores – TravelMap

TravelMap incluye varios importadores para cargar datos desde fuentes externas. Todos están disponibles en el panel de administración.

---

## Vuelos – FlightRadar / FlightDiary

**Ruta**: Admin → Importar Vuelos

Importa el historial de vuelos desde un archivo CSV exportado de [FlightRadar24](https://my.flightradar24.com/settings/export) o FlightDiary.

### Proceso

1. Exportar el CSV desde la cuenta de FlightRadar24 (Configuración → Exportar historial)
2. En el panel, subir el archivo CSV
3. Revisar la vista previa:
   - Los vuelos se agrupan automáticamente en viajes según intervalos de tiempo
   - Se puede fusionar o separar viajes, mover vuelos entre grupos y editar los títulos
4. Confirmar la importación

Los viajes se crean en estado **borrador** para revisión antes de publicar.

**Base de datos de aeropuertos**: incluye 70+ aeropuertos con coordenadas. Las rutas se generan como GeoJSON automáticamente.

---

## Estadías – Airbnb

**Ruta**: Admin → Importar Airbnb

Importa reservas pasadas de Airbnb como puntos de tipo "stay" (estadía).

### Proceso

1. Exportar las reservas desde Airbnb (Perfil → Datos personales → Exportar datos)
2. Subir el archivo CSV en el panel
3. Los puntos se **geocodifican automáticamente** usando las direcciones del CSV
4. Se vinculan a viajes existentes si las fechas coinciden

---

## Rutas – BRouter

**Ruta**: Admin → Importar BRouter

Importa rutas calculadas desde [BRouter Web](https://brouter.de/brouter-web/).

### Proceso

1. Planificar la ruta en BRouter y exportar el archivo CSV de coordenadas
2. Subir el archivo en el panel
3. Seleccionar el tipo de transporte y el viaje destino
4. Confirmar

---

## Rutas – GPX (GraphHopper / OpenRailRouting)

**Ruta**: Admin → Importar GPX

Importa archivos GPX generados por [GraphHopper](https://graphhopper.com/) o [OpenRailRouting](https://routing.openrailrouting.org/).

### Proceso

1. Calcular la ruta en el servicio de enrutamiento y descargar el archivo `.gpx`
2. Subir el archivo en el panel
3. Seleccionar un viaje existente o crear uno nuevo (se puede añadir descripción)
4. Opcionalmente importar los waypoints del archivo
5. Confirmar

---

## Imágenes con EXIF

**Ruta**: Admin → Importar EXIF

Crea puntos de interés a partir de imágenes con datos GPS y fecha de captura en los metadatos EXIF.

### Proceso

1. Subir una o más imágenes con datos EXIF de ubicación
2. El sistema extrae coordenadas y fecha de captura de cada imagen
3. Realiza geocodificación inversa para obtener el nombre del lugar
4. Confirmar la creación de cada punto

**Nota**: Si la imagen no tiene coordenadas en EXIF, el importador intenta extraer la fecha desde el nombre del archivo.
