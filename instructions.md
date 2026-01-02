# Instrucciones

Necesito crear una aplicación web para poder crear mapas interactivos con la información de mis viajes.
La aplicación tiene que permitir lo siguiente:

Frontend:
* Mostrar un mapa del mundo utilizando OpenStreetMaps
* Mostrar un selector de viajes para filtrar, por default se mostrarán todos al mismo tiempo
* En el mapa mostrar la ruta del viaje (polylines del recorrido diferenciando ruta de automóvil, a pie, avión, barco o tren)
* Mostrar los puntos clave: lugares visitados, lugares de alojamiento, con un ícono y título
* Al posicionarse sobre un punto clave desplegar una tarjeta con fotografía, título y descripción, fecha, enlace, coordenadas.
* permitir filtrar qué capa de datos mostrar (rutas, puntos clave, títulos, etc.)
* Mostrar los viajes con colores para diferenciarlos
* Considerar clustering para puntos o viajes superpuestos

Backend
* Carga de viajes
* Por cada viaje cargar cada recorrido dibujando a mano sobre el mapa indicando el tipo de viaje (punto a punto para facilitar el dibujo, permitir editar), debe ser editable, agregar nuevos puntos, remover existentes
* Por cada viaje agregar los puntos clave: definir tipos, ícono a utilizar, imagen, título, descripción, enlace externo, fecha y coordenadas
* Carga de usuarios (ABM)
* Sistema de sesión, con login, sin registro público
* Imágenes se suben al filesystem, hay que definir una carpeta en configuración para guardarlas

Especificaciones:
Desarrollar con:

Backend: PHP 8.x nativo (Vanilla PHP). Usaremos POO (Programación Orientada a Objetos) simple y PDO para la base de datos. Estructura MVC manual (Model-View-Controller) para mantener el orden sin usar frameworks.

Base de Datos: MySQL o MariaDB.

Frontend Estructura: Bootstrap 5 (descargado, CSS y JS locales).

Frontend Lógica: jQuery 3.x (descargado local).

Mapas:

Leaflet.js (Core).
Leaflet.draw (Para dibujar/editar rutas en el admin).
Leaflet.markercluster (Para agrupar puntos en el frontend).
Leaflet.polylineDecorator (Opcional, para dibujar flechitas de dirección en las rutas).

A continuación te defino las fases del proyecto

## Fase 1: Base de Datos y Estructura del Proyecto

Actúa como un Arquitecto de Software experto en PHP y MySQL.

**Objetivo:** Definir la estructura de archivos y la base de datos para una aplicación web de "Diario de Viajes Interactivo".

**Restricciones Técnicas (Muy Importante):**
* Lenguaje: PHP 8.x nativo (Sin Laravel, Symphony, etc.).
* BD: MySQL.
* Frontend: Bootstrap 5 y jQuery (referenciados localmente).
* Enfoque: Mantenibilidad, Código Limpio, POO simple.

**Tareas a realizar:**
1.  **Script SQL (`database.sql`):** Genera el código SQL para crear la base de datos y las siguientes tablas (usa `utf8mb4`):
    * `users`: (id, username, password_hash, created_at).
    * `trips`: (id, title, description, start_date, end_date, color_hex, status [draft/published]).
    * `routes`: (id, trip_id, transport_type [enum: plane, car, walk, ship, train], geojson_data, color). *Nota: `geojson_data` será un LONGTEXT para guardar coordenadas JSON.*
    * `points_of_interest`: (id, trip_id, title, description, type [enum: stay, visit, food], icon, image_path, latitude, longitude, visit_date).
    * Asegúrate de incluir las claves foráneas (Foreign Keys) con `ON DELETE CASCADE`.

2.  **Estructura de Carpetas:** Define una estructura de directorios estándar y segura (ej: `/config`, `/public`, `/src`, `/uploads`, `/assets/vendor`). Explica brevemente qué va en cada una.

3.  **Conexión a BD (`config/db.php`):** Crea una clase o script de conexión usando **PDO**. Debe manejar excepciones (try/catch) correctamente.

4.  **Configuración Global (`config/config.php`):** Define constantes para la URL base (BASE_URL) y rutas de archivos (ROOT_PATH).

## Fase 2: Sistema de Autenticación y Layout Base (Backend)

Actúa como Desarrollador Fullstack PHP.

**Contexto:** Ya tengo la base de datos y la conexión configuradas según la fase anterior. Ahora necesito asegurar el acceso.

**Tareas a realizar:**
1.  **Autenticación:**
    * Crea `auth.php`: Funciones para `login($user, $pass)`, `logout()` y `is_logged_in()`.
    * Usa `password_hash()` y `password_verify()` para seguridad.
    * Crea un script auxiliar `install/seed_admin.php` que al ejecutarse cree un usuario administrador inicial (admin/admin123) en la BD.

2.  **Layout del Panel de Control:**
    * Crea `includes/header.php` y `includes/footer.php`.
    * Debe usar **Bootstrap 5**.
    * Incluye una barra de navegación (Navbar) con enlaces a: Inicio, Viajes, Usuarios y Salir.
    * **Importante:** Asume que CSS y JS de Bootstrap están en `/assets/vendor/bootstrap/`, no uses CDNs.

3.  **Dashboard (`admin/index.php`):**
    * Crea una página protegida (si no hay sesión, redirige a `login.php`).
    * Muestra un mensaje de bienvenida simple extendiendo el layout creado.

4.  **Login (`login.php`):** Formulario simple de inicio de sesión centrado en pantalla.

## Fase 3: ABM de Viajes y Puntos (Lógica PHP pura)

Actúa como Desarrollador Backend PHP Senior.

**Objetivo:** Crear el sistema CRUD (Crear, Leer, Actualizar, Borrar) para los datos, SIN integrar el mapa todavía.

**Tareas a realizar:**
1.  **Gestión de Viajes (`admin/trips.php` y `admin/trip_form.php`):**
    * Listado de viajes en una tabla (con acciones Editar/Borrar).
    * Formulario para crear/editar: Título, Fechas, Color (input type="color"), Descripción.
    * Procesa el formulario en PHP nativo validando los datos básicos.

2.  **Gestión de Puntos de Interés (`admin/points.php`):**
    * Debe permitir filtrar puntos por "Viaje".
    * Formulario de creación/edición:
        * Selección de Viaje (Select box).
        * Título, Descripción, Tipo, Fecha.
        * **Coordenadas:** Dos inputs de texto simples (`lat`, `lng`) que por ahora llenaremos manualmente (en la siguiente fase los automatizaremos con el mapa).
        * **Imagen:** Implementa la subida de archivos al servidor. Guarda el archivo en `/uploads/points/` generando un nombre único. Guarda la ruta relativa en la BD. Valida extensiones (jpg, png).

**Nota:** Usa POO o funciones limpias para las consultas SQL. Mantén el código separado de la vista tanto como sea posible dentro de PHP nativo.

## Fase 4: Editor de Mapas (Integración Leaflet + Draw)

Actúa como Experto en JavaScript, jQuery y GIS (Sistemas de Información Geográfica).

**Contexto:** Tengo el backend PHP listo. Ahora necesito integrar el mapa en el formulario de edición de viajes (`admin/trip_edit_map.php`).

**Requerimientos Técnicos:**
* Librería: **Leaflet.js** (Core).
* Plugins: **Leaflet.draw** (Para dibujar rutas).
* Todo debe funcionar con archivos locales en `/assets/vendor/`.

**Tareas a realizar:**
1.  **Inicialización:** Genera el código JS para iniciar un mapa Leaflet dentro del formulario de edición de viaje. Centra el mapa en una vista global por defecto.

2.  **Dibujo de Rutas (Polyline):**
    * Habilita la herramienta de dibujo (Leaflet.draw) solo para **Polilíneas**.
    * Al terminar de dibujar una línea (`draw:created`), abre un `prompt` o modal simple pidiendo el "Tipo de Transporte" (Avión, Auto, etc).
    * Asigna un color a la línea según el transporte (ej: Avión=Rojo, Auto=Azul).
    * **Persistencia:** Cada vez que se dibuje o edite una línea, exporta el GeoJSON de todas las capas a un `<input type="hidden" name="routes_geojson">` para que PHP pueda guardarlo.

3.  **Ubicación de Puntos:**
    * Muestra los puntos existentes del viaje como marcadores en el mapa.
    * Permite que los marcadores sean "draggables" (arrastrables). Al soltar el marcador, actualiza los inputs de latitud/longitud del formulario.
    * Permite hacer clic en cualquier parte vacía del mapa para obtener las coordenadas y llenar los inputs de latitud/longitud para un *nuevo* punto.

4.  Dame el código JavaScript necesario (usando jQuery para selectores) y explica cómo integrarlo en el HTML existente.

## Fase 5: Visualizador Público (Frontend Final)

Actúa como Desarrollador Frontend Creativo.

**Objetivo:** Crear la página pública (`index.php`) donde se visualizan todos los viajes.

**Requerimientos:**
1.  **API Endpoint:** Crea un archivo PHP simple (`api/get_all_data.php`) que devuelva un JSON con toda la estructura: Viajes, sus Rutas y sus Puntos.

2.  **Interfaz de Mapa (`index.php`):**
    * El mapa debe ocupar el 100% del Viewport (height: 100vh).
    * Crea un menú flotante (usando Bootstrap Offcanvas o un Card flotante) que sirva de "Leyenda" y "Filtro". Debe tener checkboxes para mostrar/ocultar viajes específicos.

3.  **Lógica JS (Leaflet):**
    * Consume el JSON del API.
    * **Rutas:** Dibuja las polilíneas. Usa `dashArray` (líneas punteadas) si el transporte es 'Avión' o 'Barco'.
    * **Clustering:** Usa el plugin **Leaflet.markercluster** para agrupar los puntos de interés cercanos y evitar saturación.
    * **Popups:** Al hacer clic en un punto, muestra un popup estilizado con la imagen (si existe), título, fecha y descripción.

4.  **Estilos CSS:** Sugiere CSS personalizado para que los popups y el menú flotante se vean modernos y limpios.