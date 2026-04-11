# Configuración – TravelMap

Todas las opciones se gestionan desde **Admin → Configuración**. Los valores se guardan en la base de datos y se aplican automáticamente en toda la aplicación.

---

## General

| Opción | Clave interna | Por defecto | Descripción |
|---|---|---|---|
| Idioma por defecto | `default_language` | `en` | Idioma de la interfaz para nuevos visitantes. Los usuarios pueden cambiarlo con el selector en el mapa. |
| Zona horaria | `timezone` | `America/Argentina/Buenos_Aires` | Zona horaria para fechas y horas en todo el sistema. |
| Duración de sesión | `session_lifetime` | 24 h | Tiempo antes de que la sesión de administrador expire. |
| Tamaño máximo de upload | `max_upload_size` | 8 MB | Límite para imágenes de puntos de interés. |

---

## Mapa

| Opción | Por defecto | Descripción |
|---|---|---|
| Estilo de mapa | Voyager | Estilo visual del mapa base. Opciones: Positron, Voyager, Dark Matter, OSM Liberty. |
| Clustering habilitado | Sí | Agrupa puntos cercanos en el mapa público. |
| Radio máximo de cluster | 30 px | Distancia máxima en píxeles para agrupar puntos. |
| Desactivar clustering en zoom | 15 | Nivel de zoom a partir del cual se muestran los puntos individualmente. |

### Colores de rutas por tipo de transporte

Cada tipo de transporte tiene un color configurable que se aplica en el mapa público y en el editor de rutas:

| Tipo | Color por defecto |
|---|---|
| Avión | `#FF6B6B` |
| Tren | `#4ECDC4` |
| Barco | `#45B7D1` |
| Coche | `#96CEB4` |
| A pie | `#FFEAA7` |
| Autobús | `#DDA0DD` |
| Bicicleta | `#98FB98` |
| Teleférico / Aéreo | `#FFB347` |

---

## Imágenes

| Opción | Por defecto | Descripción |
|---|---|---|
| Ancho máximo | 1920 px | Las imágenes subidas se redimensionan si superan este ancho. |
| Alto máximo | 1080 px | Las imágenes subidas se redimensionan si superan este alto. |
| Calidad JPEG | 85 % | Nivel de compresión para imágenes JPEG. |
| Ancho máximo de thumbnail | 1024 px | Dimensión máxima de las miniaturas generadas. |

Las imágenes PNG con transparencia se procesan sin comprometer el canal alfa.

---

## Sitio público

| Opción | Por defecto | Descripción |
|---|---|---|
| Título del sitio | `Travel Map - Mis Viajes...` | Aparece en la pestaña del navegador y en resultados de búsqueda (SEO). Máximo 100 caracteres. |
| Meta description | — | Descripción breve para buscadores. Recomendado: máximo 160 caracteres. |
| Favicon | — | URL al archivo `.ico` o `.png` (mínimo 16×16 px). |
| Código de analytics | — | Pegar aquí el snippet de Google Analytics u otro sistema. Se inyecta en el `<head>` de la vista pública. |

---

## Pestaña Viaje

Opciones del comportamiento en la página individual de cada viaje (`/trip.php`):

| Opción | Descripción |
|---|---|
| Mostrar tooltip al hacer hover | Activa/desactiva el tooltip al pasar el cursor sobre un marcador. |
| Zoom al volar a un POI | Nivel de zoom al centrar el mapa en un punto de interés. |
| Velocidad de animación | Velocidad de la animación de flyTo al seleccionar un POI. |

---

## Archivos de configuración

Los archivos `config/config.php` y `config/db.php` se generan durante la instalación y **nunca deben subirse al repositorio** (están en `.gitignore`). Contienen credenciales de la base de datos y la URL base del proyecto.

Para regenerarlos manualmente, copiar los archivos `.example.php` correspondientes. Ver [INSTALACION.md](INSTALACION.md).
