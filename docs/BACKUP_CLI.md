# Backup CLI — TravelMap

Herramienta de línea de comandos para generar backups de TravelMap sin necesidad de un navegador ni autenticación web. Diseñada para ejecutarse manualmente o de forma automática desde un cron job.

---

## Instalación de permisos (obligatorio)

Después de clonar o actualizar el repositorio, aplicar estos permisos para que el servidor web **no pueda leer** los scripts:

```bash
chmod 0750 bin/
chmod 0700 bin/travelmap.php
chown <usuario-cron>:<grupo-cron> bin/ bin/travelmap.php  # opcional
```

Reemplazar `<usuario-cron>` por el usuario que ejecutará el cron (p. ej. `pi`, `tucho235`, `www-data` si el cron corre como Apache, aunque lo último no es recomendable).

### Recomendación fuerte para producción

Lo más seguro es mover `bin/` **fuera del DocumentRoot** del servidor web:

```bash
# Ejemplo: mover fuera del docroot
sudo mv /var/www/TravelMap/bin /opt/travelmap-bin
sudo ln -s /opt/travelmap-bin /var/www/TravelMap/bin  # opcional, para desarrollo
```

Así el servidor web no puede alcanzar el directorio ni aunque falle el `.htaccess`.

---

## Uso

```bash
# Backup completo (todos los datos + imágenes → produce ZIP)
php bin/travelmap.php backup create

# Solo datos, sin imágenes (produce JSON)
php bin/travelmap.php backup create --no-images

# Solo algunas secciones
php bin/travelmap.php backup create --no-images --only=trips,routes

# Guardar en directorio personalizado
php bin/travelmap.php backup create --output=/mnt/nas/backups

# Listar backups existentes
php bin/travelmap.php backup list

# Ayuda del módulo backup
php bin/travelmap.php backup help

# Ayuda general
php bin/travelmap.php help
```

### Flags de `create`

| Flag | Descripción |
|------|-------------|
| `--no-images` | Excluye imágenes; genera JSON en vez de ZIP |
| `--only=<secciones>` | Secciones a incluir: `trips`, `routes`, `points`, `tags`, `settings` (separadas por coma) |
| `--output=<ruta>` | Directorio destino (default: `ROOT_PATH/backups`) |

---

## Cron job semanal

Abrir el crontab del usuario:

```bash
crontab -e
```

Agregar la línea (backup todos los domingos a las 03:00):

```cron
0 3 * * 0  /usr/bin/php /home/tucho235/Developer/TravelMap/bin/travelmap.php backup create >> /var/log/travelmap.log 2>&1
```

- Si el comando falla (exit ≠ 0) y `MAILTO` está configurado, cron enviará un email con el error.
- El log `/var/log/travelmap.log` se puede rotar con logrotate:

```
/var/log/travelmap.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
}
```

---

## Capas de seguridad

El CLI está protegido con cinco capas de defensa en profundidad:

1. **`bin/.htaccess`** — deniega todo acceso web (Apache 2.2 y 2.4+).
2. **Guard SAPI** — primera línea del script: si `PHP_SAPI !== 'cli'` responde 403 y sale. Protege incluso si `.htaccess` no se procesa.
3. **Permisos Unix** — `chmod 0700` evita que `www-data` pueda leer o ejecutar el archivo.
4. **Sin variables HTTP** — el script nunca lee `$_GET`, `$_POST`, `$_REQUEST` ni `$_SERVER['HTTP_*']`. Solo usa `$argv`.
5. **Validación de args** — los flags se validan contra whitelists antes de usarse en rutas o lógica.

---

## Configuración nginx (si no usas Apache)

nginx **no lee `.htaccess`**. Añadir estos bloques en el virtual host:

```nginx
# Bloquear directorios sensibles
location ^~ /bin/        { deny all; return 404; }
location ^~ /config/     { deny all; return 404; }
location ^~ /backups/    { deny all; return 404; }
location ^~ /src/        { deny all; return 404; }
location ^~ /includes/   { deny all; return 404; }
```

---

## Interoperabilidad con la UI web

Los archivos generados por el CLI (`backup_YYYY-MM-DD_HHmmss.zip` o `.json`) son idénticos a los generados desde `admin/backup.php`. Aparecen automáticamente en la lista de backups de la interfaz web y pueden restaurarse desde ahí sin ningún cambio adicional.

---

## Transferir backups a otro servidor

El CLI no implementa transferencia remota. Usar herramientas estándar:

```bash
# Copiar el último backup por SCP
scp backups/$(ls -t backups/ | head -1) usuario@servidor:/destino/

# O sincronizar todo el directorio
rsync -avz backups/ usuario@servidor:/destino/backups/
```
