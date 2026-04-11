# Instalación y Actualización – TravelMap

## Requisitos

| Componente | Versión mínima |
|---|---|
| PHP | 8.0 |
| MySQL / MariaDB | 5.7 / 10.3 |
| Extensiones PHP | `pdo_mysql`, `gd`, `fileinfo`, `curl` |
| Navegador | Soporte WebGL (Chrome, Firefox, Edge, Safari modernos) |

Para verificar que las extensiones están activas, crear un archivo temporal con `<?php phpinfo(); ?>` y buscar las secciones "gd", "PDO" y "fileinfo". En XAMPP, habilitar extensiones en `php.ini` (quitar `;` delante de la línea correspondiente) y reiniciar Apache.

---

## Instalación nueva

### 1. Colocar los archivos

Clonar o copiar el proyecto en la carpeta raíz del servidor web:

```
c:\xampp\htdocs\TravelMap\    (Windows/XAMPP)
/var/www/html/TravelMap/      (Linux/LAMP)
```

Si el proyecto va en la raíz del dominio (sin subcarpeta), colocarlo directamente en `htdocs/` o `www/`.

### 2. Acceder al instalador

Abrir en el navegador:

```
http://localhost/TravelMap/install/
```

El instalador guía por cinco pasos:

**Paso 1 – Verificación de requisitos**  
Comprueba automáticamente la versión de PHP, extensiones requeridas y permisos de escritura.

**Paso 2 – Configuración**  
Ingresar las credenciales de la base de datos y la subcarpeta del proyecto. El instalador genera automáticamente los archivos `config/db.php` y `config/config.php`.

- **Host**: normalmente `127.0.0.1` o `localhost`
- **Base de datos**: nombre de la BD a crear (por ejemplo, `travelmap`)
- **Usuario / Contraseña**: credenciales de MySQL
- **Subcarpeta**: `/TravelMap` si el proyecto está en esa carpeta; dejar vacío si está en la raíz del dominio

**Paso 3 – Inicializar base de datos**  
Ejecuta `database.sql`, que crea todas las tablas y datos iniciales. También marca todas las migraciones como aplicadas.

**Paso 4 – Crear usuario administrador**  
Ingresar nombre de usuario y contraseña para el primer acceso al panel de administración.

**Paso 5 – Finalización**  
El sistema queda operativo.

### 3. Eliminar el instalador

Después de instalar, **eliminar o proteger la carpeta `install/`**:

```bash
rm -rf install/   # Linux
```

En Windows, eliminar la carpeta manualmente o restringir el acceso en la configuración del servidor web.

---

## Configuración manual (alternativa sin asistente)

Si no es posible usar el asistente web, hacer la configuración manualmente:

### Archivos de configuración

Copiar los archivos de ejemplo:

```
config/config.example.php  →  config/config.php
config/db.example.php      →  config/db.php
```

Editar `config/db.php`:

```php
private const DB_HOST    = '127.0.0.1';
private const DB_NAME    = 'travelmap';
private const DB_USER    = 'root';
private const DB_PASS    = '';        // contraseña de tu BD
private const DB_CHARSET = 'utf8mb4';
```

Editar `config/config.php`:

```php
$folder = '/TravelMap';   // vacío '' si está en la raíz del dominio
```

### Crear la base de datos

Importar `database.sql` en MySQL:

```bash
mysql -u root -p travelmap < database.sql
```

O desde phpMyAdmin: crear la base de datos, seleccionarla e importar el archivo SQL.

---

## Actualización

### Pasos

1. Descargar el ZIP de la nueva versión desde GitHub o hacer `git pull`
2. Copiar los archivos en el servidor — los archivos `config/config.php` y `config/db.php` no están en el repositorio (están en `.gitignore`), por lo que la configuración existente no se sobreescribe
3. Acceder a `http://[host]/TravelMap/install/` e iniciar sesión con un usuario administrador
4. El instalador detecta las migraciones pendientes y las ejecuta con un clic
5. Volver a eliminar o proteger `install/` al terminar

### Sistema de migraciones

Las migraciones están en `install/migrations/` con numeración secuencial (`001_`, `002_`, ...). Cada migración incluye un método `check()` que detecta si el cambio ya fue aplicado. Esto garantiza que al actualizar, solo se ejecuten los cambios realmente pendientes, incluso en instalaciones previas al sistema de migraciones.

La tabla `schema_migrations` registra qué migraciones fueron aplicadas y cuándo.

### Herramienta de thumbnails

Si al actualizar las imágenes existentes no tienen thumbnails, ejecutar:

```
http://[host]/TravelMap/install/generate_thumbnails.php
```

---

## Acceso a la aplicación

| Sección | URL |
|---|---|
| Vista pública | `http://[host]/TravelMap/` |
| Panel de administración | `http://[host]/TravelMap/admin/` |
| Instalador/actualizador | `http://[host]/TravelMap/install/` |
