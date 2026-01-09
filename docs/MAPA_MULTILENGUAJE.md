# Soporte Multilenguaje en Mapas

## Descripción

El sistema de mapas de TravelMap ahora incluye soporte para mostrar las etiquetas y nombres de lugares en el idioma activo del sitio, en lugar de mostrarlos únicamente en los idiomas originales de cada región.

## ¿Cómo funciona?

### MapLibre GL (Recomendado)

Cuando se usa el renderizador **MapLibre GL** (configuración por defecto), el sistema:

1. **Detecta el idioma activo** del sitio mediante:
   - Sistema de internacionalización (`window.i18n.currentLang`)
   - Atributo `lang` del documento HTML
   - Fallback a inglés si no se detecta

2. **Modifica las capas del mapa** una vez cargado:
   - Busca en cada capa de texto del estilo del mapa
   - Reemplaza los campos de texto con expresiones que intentan mostrar:
     1. Nombre en el idioma seleccionado (ej: `name:es` para español)
     2. Nombre en inglés (`name:en`) como fallback
     3. Nombre por defecto (`name`)

3. **Utiliza datos de OpenStreetMap** que incluyen nombres traducidos en múltiples idiomas:
   - Español: `name:es`
   - Inglés: `name:en`
   - Francés: `name:fr`
   - Alemán: `name:de`
   - Italiano: `name:it`
   - Portugués: `name:pt`
   - Ruso: `name:ru`
   - Chino: `name:zh`
   - Japonés: `name:ja`
   - Árabe: `name:ar`
   - Y muchos más...

### Leaflet (Limitaciones)

Cuando se usa el renderizador **Leaflet** con tiles raster:

- **IMPORTANTE**: Los tiles raster son **imágenes PNG pre-renderizadas** que no pueden cambiar el idioma dinámicamente
- Cada tile es una imagen fija (.png) que ya viene con los textos "quemados" en un idioma específico
- Los tiles de **CARTO** incluyen nombres principalmente en inglés con algunos nombres locales mezclados
- Los tiles de **OpenStreetMap** estándar muestran nombres principalmente en el idioma local de cada región
- **Limitación técnica fundamental**: No existe forma de cambiar el idioma de los tiles raster sin:
  1. Usar un servidor diferente de tiles pre-renderizados en cada idioma (no disponible gratuitamente)
  2. Implementar un proxy que renderice tiles on-the-fly (muy complejo y costoso en recursos)
  3. Usar un servicio comercial con API key (Mapbox, Maptiler, etc.)
- **Caché**: El Service Worker puede cachear tiles viejos - ver sección de solución de problemas
- **Recomendación**: **Usar MapLibre GL** para soporte multilenguaje completo real

### ¿Por qué los tiles raster no pueden cambiar de idioma?

Los tiles raster son como fotografías:
- Cada tile es una imagen PNG de 256x256 píxeles
- Los textos ya están "pintados" en la imagen
- No hay forma de modificar los textos sin volver a renderizar la imagen
- Es como intentar cambiar el texto de una fotografía - no es posible sin Photoshop

**Analogía**: Imagina que cada tile del mapa es una foto impresa. No puedes cambiar el texto de una foto impresa sin re-imprimirla. Lo mismo pasa con los tiles raster.

### Opciones para soporte de idiomas en Leaflet

#### ✅ Opción 1: MapLibre GL (RECOMENDADA)
- Cambiar a MapLibre GL en Configuración > Mapa
- Soporte completo de idiomas en tiempo real
- Los tiles son vectoriales, no imágenes
- **Gratis y sin limitaciones**

#### ⚠️ Opción 2: Servicios con API Key (Limitado)
Requieren registro y tienen límites de uso gratuito:
- **Maptiler** (100,000 tiles/mes gratis)
- **Mapbox** (200,000 tiles/mes gratis)
- **Thunderforest** (15,000 tiles/mes gratis)

#### ❌ Opción 3: Servidor de tiles propio (No recomendado)
- Requiere servidor con tile rendering
- Alto consumo de recursos
- Mantenimiento complejo
- Solo viable para proyectos grandes

## Idiomas soportados

La disponibilidad de traducciones depende de la cobertura de datos en OpenStreetMap:

- **Excelente cobertura**: Ciudades principales, países, regiones importantes
- **Buena cobertura**: Europa, América del Norte, parte de Asia
- **Cobertura variable**: Zonas rurales, países con menor contribución a OSM

## Configuración

### Cambiar el renderizador del mapa

1. Ir a **Admin > Configuración**
2. En la sección "Mapa", seleccionar **MapLibre GL** como renderizador
3. Guardar cambios

### Cambiar el idioma del sitio

El idioma del mapa se actualiza automáticamente cuando cambias el idioma del sitio:

1. Cambiar el idioma en el selector de idioma del sitio
2. Recargar la página del mapa
3. Los nombres se mostrarán en el nuevo idioma (cuando estén disponibles)

### Configurar tiles con API key (Opcional - Solo para Leaflet)

Si necesitas soporte de idiomas en Leaflet y no quieres cambiar a MapLibre GL, puedes configurar un servicio con API key:

#### Maptiler (Recomendado)

1. **Registrarse en Maptiler**:
   - Ir a https://www.maptiler.com/
   - Crear cuenta gratuita (100,000 tiles/mes)
   - Obtener API key

2. **Modificar configuración** en `assets/js/public_map_leaflet.js`:
   ```javascript
   const MAPTILER_KEY = 'TU_API_KEY_AQUI';
   const tileUrl = `https://api.maptiler.com/maps/streets/{z}/{x}/{y}.png?key=${MAPTILER_KEY}&language=${currentLang}`;
   ```

3. **Idiomas soportados**: en, es, fr, de, it, pt, ru, zh, ja, ar, y más

#### Mapbox

1. **Registrarse en Mapbox**:
   - Ir a https://www.mapbox.com/
   - Crear cuenta gratuita (200,000 tiles/mes)
   - Obtener access token

2. **Modificar configuración**:
   ```javascript
   const MAPBOX_TOKEN = 'TU_ACCESS_TOKEN_AQUI';
   const tileUrl = `https://api.mapbox.com/styles/v1/mapbox/streets-v12/tiles/{z}/{x}/{y}?access_token=${MAPBOX_TOKEN}&language=${currentLang}`;
   ```

**Nota**: Esta configuración solo aplica para Leaflet. MapLibre GL ya tiene soporte de idiomas sin necesidad de API key.

## Archivos modificados

### MapLibre GL (`public_map.js`)

```javascript
// Función que aplica el idioma a las etiquetas del mapa
function applyLanguageToMap(lang) {
    // Obtiene el estilo actual
    const style = map.getStyle();
    
    // Para cada capa con etiquetas de texto
    style.layers.forEach(layer => {
        if (layer.layout && layer.layout['text-field']) {
            // Crea expresión que intenta mostrar nombre en idioma seleccionado
            const newTextField = [
                'coalesce',
                ['get', `name:${lang}`],  // Nombre en idioma seleccionado
                ['get', 'name:en'],        // Fallback a inglés
                ['get', 'name']            // Fallback a nombre original
            ];
            
            // Actualiza la capa
            map.setLayoutProperty(layer.id, 'text-field', newTextField);
        }
    });
}
```

### Leaflet (`public_map_leaflet.js`)

Se detecta el idioma activo, aunque la aplicación en tiles raster es limitada:

```javascript
// Detectar idioma actual
const currentLang = window.i18n?.currentLang || document.documentElement.lang || 'en';
```

## Limitaciones conocidas

1. **Disponibilidad de traducciones**: No todos los lugares tienen nombres traducidos en OSM
2. **Tiles raster**: Los mapas Leaflet con tiles raster tienen soporte limitado
3. **Alfabetos no latinos**: Algunos idiomas pueden no mostrarse correctamente según la fuente del mapa
4. **Lugares pequeños**: Pueblos y lugares menores pueden no tener traducciones disponibles

## Mejores prácticas

1. **Usar MapLibre GL** para mejor soporte multilenguaje
2. **Verificar cobertura**: No todos los lugares tienen nombres en todos los idiomas
3. **Contribuir a OSM**: Si faltan traducciones, puedes añadirlas en OpenStreetMap
4. **Idioma fallback**: El sistema siempre intentará mostrar algo (inglés o nombre original)

## Solución de problemas

### Los nombres siguen en el idioma original (Leaflet)

**Causa principal:**
- **Los tiles raster de Leaflet son imágenes pre-renderizadas** - no pueden cambiar el idioma dinámicamente
- Esto es una limitación técnica fundamental de los tiles raster vs vectoriales

**Soluciones:**
- ✅ **Cambiar a MapLibre GL** (Configuración > Mapa > Renderizador)
- ⚠️ Si usas Leaflet, los nombres seguirán en el idioma predefinido del servidor de tiles

### El Service Worker está cacheando tiles viejos

**Síntomas:**
- Los tiles del mapa no se actualizan
- Sigues viendo los mismos nombres aunque cambies configuración

**Soluciones:**

1. **Limpiar caché del navegador**:
   - Presiona `Ctrl + Shift + Delete` (Windows) o `Cmd + Shift + Delete` (Mac)
   - Selecciona "Imágenes y archivos en caché"
   - Clic en "Borrar datos"

2. **Forzar recarga completa**:
   - Presiona `Ctrl + F5` (Windows) o `Cmd + Shift + R` (Mac)

3. **Limpiar caché del Service Worker manualmente**:
   ```javascript
   // En la consola del navegador (F12)
   navigator.serviceWorker.ready.then(registration => {
       registration.active.postMessage('clearCache');
   });
   ```

4. **Desregistrar Service Worker** (último recurso):
   - Abre DevTools (F12)
   - Ve a "Application" > "Service Workers"
   - Clic en "Unregister"
   - Recarga la página

### Los nombres siguen en el idioma original (MapLibre GL)

**Posibles causas:**
- El lugar no tiene traducción en ese idioma en OSM
- Estás usando renderizador Leaflet con tiles OSM estándar
- El idioma no está correctamente detectado

**Soluciones:**
- Cambiar a MapLibre GL
- Verificar que el idioma del sitio esté configurado correctamente
- Algunos lugares solo tienen nombre en idioma local

### El mapa no carga

**Posibles causas:**
- Error en la detección de idioma
- Problema con el estilo del mapa

**Soluciones:**
- Revisar consola del navegador (F12)
- Verificar que el idioma sea uno válido (en, es, fr, etc.)
- Limpiar caché del navegador

## Referencias

- [OpenStreetMap Multilingual Names](https://wiki.openstreetmap.org/wiki/Multilingual_names)
- [MapLibre GL Style Specification](https://maplibre.org/maplibre-style-spec/)
- [CARTO Basemaps](https://carto.com/basemaps/)
