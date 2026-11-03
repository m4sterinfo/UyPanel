<h1 align="center">🛠 Herramientas CLI y migraciones de BD</h1>

<p align="center">
  Referencia de la línea de comandos de UyPanel, utilidades del sistema y sistema de migraciones de la base de datos.<br>
  Cubre las operaciones del día a día, el acceso de emergencia y la creación de nuevas migraciones de BD.
</p>

---

## 📚 Navegación

* [🖥 Punto de entrada de la consola](#punto-de-entrada-de-la-consola)
* [� Registro completo de comandos](#registro-completo-de-comandos)
* [🆕 Registro de un comando nuevo](#registro-de-un-comando-nuevo)
* [🔧 Comando tools](#comando-tools)
* [🗃 Migraciones de la base de datos](#migraciones-de-la-base-de-datos)
* [📝 Crear una migración nueva](#crear-una-migración-nueva)
* [⚙️ Operaciones CLI típicas](#operaciones-cli-típicas)

---

## 🖥 Punto de entrada de la consola

Todos los comandos CLI se ejecutan vía `console.php`:

```bash
/home/uypanel/bin/php/bin/php /home/uypanel/console.php <command> [args...]
```

La consola soporta tres tipos de comandos:

| Tipo | Cantidad | Descripción |
|---|---|---|
| **Commands** | 26 | Operaciones puntuales (update, status, tools, etc.) |
| **CronJobs** | 25 | Tareas programadas (auto-lanzadas vía crontab) |
| **Daemons** | 8 | Procesos demonio en segundo plano (Commands que usan `DaemonTrait`) |

> **Nota:** Los daemons son Commands normales con el trait `DaemonTrait`. No hay un directorio `Daemons/` aparte.

Para ver todos los comandos disponibles:

```bash
/home/uypanel/bin/php/bin/php /home/uypanel/console.php list
```

---

## 📋 Registro completo de comandos

### Comandos utilitarios

| Comando | Clase | Descripción | Usuario |
|---|---|---|---|
| `status` | `StatusCommand` | Estado del sistema, migraciones de BD, verificación de configuración | root |
| `update` | `UpdateCommand` | Actualización del sistema (update / post-update) | uypanel |
| `service` | `ServiceCommand` | Gestión del servicio UyPanel: start, stop, restart, reload | root |
| `tools` | `ToolsCommand` | Utilidades de mantenimiento (ver [Comando tools](#comando-tools)) | root/uypanel |
| `certbot` | `CertbotCommand` | Generación de certificado SSL vía certbot | root |
| `binaries` | `BinariesCommand` | Actualización de binarios y GeoLite desde GitHub | uypanel |
| `startup` | `StartupCommand` | Inicialización del sistema: daemons.sh, crontab, caché | root |
| `monitor` | `MonitorCommand` | Monitoreo de un stream por ID (arranque/reinicio/seguimiento) | uypanel |
| `thumbnail` | `ThumbnailCommand` | Generación de miniaturas para un stream | uypanel |
| `plex_item` | `PlexItemCommand` | Procesado de un elemento Plex (película/serie) | uypanel |
| `watch_item` | `WatchItemCommand` | Procesado de un elemento Watch (búsqueda/actualización TMDB) | uypanel |
| `migrate` | `MigrateCommand` | Migración de datos desde la BD `uypanel_migrate` | uypanel |
| `balancer` | `BalancerCommand` | Instalación/configuración del balanceador vía SSH | root |

> Comandos opcionales (registro condicional vía `file_exists()`): `cache_handler`, `balancer`, `migrate`.

### Comandos demonio (procesos en segundo plano)

Estos comandos usan `DaemonTrait` y funcionan de forma continua vía `while(true)`:

| Comando | Clase | Descripción |
|---|---|---|
| `signals` | `SignalsCommand` | Procesado de señales kill/cache desde la BD y Redis |
| `watchdog` | `WatchdogCommand` | Monitoreo del sistema: CPU, conexiones, actualización de servidores |
| `queue` | `QueueCommand` | Procesado de tareas en segundo plano desde la cola |
| `scanner` | `ScannerCommand` | Escaneo de streams/dispositivos nuevos |
| `cache_handler` | `CacheHandlerCommand` | Operaciones de caché (opcional) |

### Comandos de procesado de streams

| Comando | Clase | Descripción |
|---|---|---|
| `proxy` | `ProxyCommand` | Proxying de streams MPEG-TS vía sockets |
| `archive` | `ArchiveCommand` | Archivo TV — grabación del stream en segmentos |
| `created` | `CreatedCommand` | Created Channel — composición de un canal a partir de fuentes |
| `delay` | `DelayCommand` | Retardo de reproducción de un stream HLS |
| `loopback` | `LoopbackCommand` | Recepción de MPEG-TS desde otro servidor |
| `llod` | `LlodCommand` | Handler Low-Latency On-Demand de streams |
| `record` | `RecordCommand` | Grabación de un stream en MP4 |
| `ondemand` | `OndemandCommand` | Finalización de streams sin espectadores activos |

### Tareas cron (25)

Todos los nombres de tareas cron llevan el prefijo `cron:`. Usan `CronTrait` y las invoca el crontab del sistema.

| Comando | Clase | Descripción |
|---|---|---|
| `cron:activity` | `ActivityCronJob` | Importación de logs de actividad de usuarios a la BD |
| `cron:backups` | `BackupsCronJob` | Gestión de backups (opcional) |
| `cron:cache` | `CacheCronJob` | Gestión de la caché |
| `cron:cache_engine` | `CacheEngineCronJob` | Generación de caché para líneas, streams, series, grupos (opcional) |
| `cron:certbot` | `CertbotCronJob` | Renovación del certificado SSL |
| `cron:cleanup` | `CleanupCronJob` | Limpieza de archivos temporales y logs |
| `cron:epg` | `EpgCronJob` | Descarga y procesado de EPG (opcional) |
| `cron:errors` | `ErrorsCronJob` | Procesado de logs de errores |
| `cron:lines_logs` | `LinesLogsCronJob` | Importación de logs de peticiones de clientes a la BD |
| `cron:plex` | `PlexCronJob` | Procesado de actualizaciones de Plex |
| `cron:providers` | `ProvidersCronJob` | Actualización de providers (opcional) |
| `cron:root_mysql` | `RootMysqlCronJob` | Mantenimiento de BD (root, opcional) |
| `cron:root_signals` | `RootSignalsCronJob` | Procesado de señales, iptables, nginx, gestión del servicio (root) |
| `cron:series` | `SeriesCronJob` | Actualización de datos de series (opcional) |
| `cron:servers` | `ServersCronJob` | Monitoreo del servidor, arranque de daemons, actualización de estadísticas |
| `cron:stats` | `StatsCronJob` | Cálculo y guardado de estadísticas |
| `cron:streams` | `StreamsCronJob` | Comprobación y actualización del estado de streams |
| `cron:streams_logs` | `StreamsLogsCronJob` | Importación de logs de streams |
| `cron:tmdb` | `TmdbCronJob` | Obtención de metadatos TMDB (opcional) |
| `cron:tmdb_popular` | `TmdbPopularCronJob` | Obtención de contenido popular de TMDB (opcional) |
| `cron:tmp` | `TmpCronJob` | Limpieza de archivos temporales |
| `cron:update` | `UpdateCronJob` | Comprobación y aplicación de actualizaciones (opcional) |
| `cron:users` | `UsersCronJob` | Gestión de conexiones de usuarios, sincronización Redis |
| `cron:vod` | `VodCronJob` | Procesado de contenido VOD |
| `cron:watch` | `WatchCronJob` | Procesado de actualizaciones de la librería Watch |

> Tareas cron opcionales (registro condicional): `cron:backups`, `cron:cache_engine`, `cron:epg`, `cron:providers`, `cron:root_mysql`, `cron:series`, `cron:tmdb`, `cron:tmdb_popular`, `cron:update`.

---

## 🆕 Registro de un comando nuevo

Todos los comandos CLI implementan `CommandInterface` y se registran explícitamente en `console.php`.

### CommandInterface

```php
interface CommandInterface {
    public function getName(): string;        // Nombre único del comando (usado en el CLI)
    public function getDescription(): string; // Descripción de una línea (se muestra en `list`)
    public function execute(array $rArgs): int; // Punto de entrada, devuelve el código de salida
}
```

### Paso 1. Crea la clase

Crea el archivo en `src/cli/Commands/` (o `src/cli/CronJobs/` para tareas cron):

```php
<?php

class MyNewCommand implements CommandInterface {

    public function getName(): string {
        return 'my_command';
    }

    public function getDescription(): string {
        return 'Breve descripción del comando';
    }

    public function execute(array $rArgs): int {
        // Tu lógica
        echo "Done.\n";
        return 0; // 0 = éxito, 1 = error
    }
}
```

Para **comandos demonio** usa también `DaemonTrait`:

```php
class MyDaemonCommand implements CommandInterface {
    use DaemonTrait;
    // ...
}
```

Para **tareas cron** usa `CronTrait`:

```php
class MyCronJob implements CommandInterface {
    use CronTrait;

    public function getName(): string {
        return 'cron:my_job'; // Los nombres de crons llevan el prefijo cron:
    }
    // ...
}
```

### Paso 2. Regístralo en console.php

Añade en `console.php`:

```php
// Siempre se carga
$rRegistry->register(new MyNewCommand());

// O condicionalmente (para funciones opcionales)
if (file_exists(CLI_PATH . 'Commands/MyNewCommand.php')) {
    $rRegistry->register(new MyNewCommand());
}
```

### Paso 3. Añádelo al Makefile (si se excluye del LB)

Si el comando NO debe entrar en el build del Load Balancer, añade su ruta a `LB_FILES_TO_REMOVE` en el `Makefile`.

### Paso 4. Testeo

```bash
# Comprueba que el comando aparece en la lista
/home/uypanel/bin/php/bin/php /home/uypanel/console.php list

# Ejecútalo
/home/uypanel/bin/php/bin/php /home/uypanel/console.php my_command
```

---

## 🔧 Comando tools

El comando `tools` provee utilidades de mantenimiento del sistema.

```bash
console.php tools <subcommand>
```

### Subcomandos (ejecutar como `root`)

| Subcomando | Descripción |
|---|---|
| `rescue` | Crea un código de acceso temporal para entrar de emergencia al panel. Muestra la URL. **¡Elimina el código después de usarlo!** |
| `access` | Regenera todas las configuraciones de códigos de acceso de nginx y recarga nginx. Muestra la URL de todos los códigos del panel admin. |
| `ports` | Regenera las configuraciones de puertos de nginx (HTTP, HTTPS, RTMP) desde la base de datos y recarga nginx. |
| `migration` | Limpia la base de migraciones (`uypanel_migrate`) y opcionalmente restaura un backup `.sql`. |
| `user` | Crea un administrador de rescate con credenciales aleatorias. Muestra login y contraseña. **¡Elimina el usuario después de usarlo!** |
| `mysql` | Re-autoriza los privilegios MySQL de todos los servidores de balanceo de carga. |
| `database` | Restaura la BD limpia de UyPanel desde `database.sql`. **¡Elimina TODOS los datos!** Requiere el flag `--confirm`. |
| `flush` | Resetea todas las IPs bloqueadas — limpia las reglas iptables, elimina los archivos de bloqueo y vacía la tabla `blocked_ips`. |

### Subcomandos (ejecutar como `uypanel`)

| Subcomando | Descripción |
|---|---|
| `images` | Descarga las imágenes faltantes de streams/películas/series desde TMDB. Escanea la BD y descarga los archivos ausentes. |
| `duplicates` | Encuentra y elimina duplicados de streams VOD. Agrupa por fuente idéntica, conserva el primero y elimina el resto. **¡Operación destructiva!** |
| `bouquets` | Limpia las referencias obsoletas en los bouquets. Elimina los IDs que ya no existen en la base de datos. |

### Ejemplos

```bash
# Acceso de emergencia al panel (root)
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools rescue

# Regeneración de códigos de acceso (root) — necesario tras cambiar la plantilla de nginx
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools access

# Regeneración de la configuración de puertos (root)
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools ports

# Limpiar la base de migraciones (root)
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools migration

# Limpiar la base de migraciones y restaurar un backup (root)
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools migration /path/to/backup.sql

# Crear un administrador de rescate (root)
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools user

# Re-autorizar MySQL en todos los servidores (root)
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools mysql

# Restaurar la base de datos limpia (root) — ¡OPERACIÓN DESTRUCTIVA!
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools database --confirm

# Resetear todas las IPs bloqueadas (root)
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools flush

# Descargar imágenes faltantes (uypanel)
su - uypanel -c '/home/uypanel/bin/php/bin/php /home/uypanel/console.php tools images'

# Eliminar duplicados VOD (uypanel)
su - uypanel -c '/home/uypanel/bin/php/bin/php /home/uypanel/console.php tools duplicates'

# Limpiar referencias en bouquets (uypanel)
su - uypanel -c '/home/uypanel/bin/php/bin/php /home/uypanel/console.php tools bouquets'
```

> ⚠️ **Atención:** `duplicates` elimina streams y todos sus datos asociados de forma irreversible (logs, estadísticas, episodios, registros). Haz siempre un backup antes de ejecutarlo.

> ⚠️ **Atención:** `database --confirm` borra toda la base de datos y la reemplaza por el esquema limpio. Es una operación irreversible.

> 💡 **Consejo:** Tras usar `rescue`, elimina siempre el código desde el panel admin o ejecutando `tools access` tras recuperar el acceso.

> 💡 **Consejo:** Tras usar `user`, cambia la contraseña de inmediato y elimina el usuario de rescate al terminar.

---

## 🗃 Migraciones de la base de datos

UyPanel usa un sistema de migraciones en archivos para gestionar los cambios de esquema de la BD entre versiones. Las migraciones se ejecutan automáticamente en las actualizaciones y en la comprobación de estado del sistema.

### Cómo funciona

1. Los archivos de migración se guardan como `.sql` en:
   ```
   /home/uypanel/migrations/
   ```

2. Cada archivo se nombra con un prefijo numérico:
   ```
   001_drop_watch_folders_plex_token.sql
   002_panel_logs_add_file_env.sql
   003_drop_settings_segment_type.sql
   ```

3. Las migraciones aplicadas se registran en la tabla `migrations` de la BD. Cada migración se ejecuta **exactamente una vez** — si ya fue aplicada, se omite.

4. Las migraciones se ejecutan automáticamente:
   - `console.php update post-update` — tras actualizar el panel
   - `console.php status` — al comprobar el estado del sistema (solo en el servidor MAIN)

### Orden de ejecución de migraciones

```text
[ MigrationRunner::run() ]
        │
        ▼
[ CREATE TABLE IF NOT EXISTS `migrations` ]
        │
        ▼
[ Lectura de todos los archivos *.sql de migrations/ ]
        │
        ▼
[ Para cada archivo ausente en la tabla `migrations`: ]
    ├── Ejecutar las consultas SQL
    ├── Registrar en la tabla `migrations`
    └── Mostrar [OK] o [WARN]
```

---

## 📝 Crear una migración nueva

Cuando necesites cambiar el esquema de la BD (añadir columnas, crear tablas, insertar datos, etc.), crea un nuevo archivo SQL de migración.

### Paso 1. Elige el nombre del archivo

Usa el número secuencial siguiente y un nombre descriptivo:

```
NNN_short_description.sql
```

**Reglas de formato:**
- Prefijo numérico: 3 dígitos con ceros a la izquierda (p. ej. `006`, `007`)
- Separador: guion bajo `_`
- Nombre: minúsculas, guiones bajos, descripción de la acción
- Extensión: `.sql`

**Ejemplos:**
```
006_add_user_timezone.sql
007_create_audit_log_table.sql
008_insert_default_codec_settings.sql
```

### Paso 2. Escribe el SQL

Pon las consultas SQL en el archivo. Varias consultas se separan con `;`.

**Reglas para el SQL de migraciones:**

1. **Usa `IF EXISTS` / `IF NOT EXISTS`** para idempotencia:

```sql
-- Añadir columna (seguro)
ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `timezone` VARCHAR(64) DEFAULT 'UTC';

-- Eliminar columna (seguro)
ALTER TABLE `settings` DROP COLUMN IF EXISTS `old_column`;

-- Crear tabla (seguro)
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

2. **Usa `INSERT` condicional** para evitar duplicados:

```sql
INSERT INTO `streams_arguments` (argument_key, argument_name, argument_cmd)
SELECT 'my_key', 'My Argument', '-my_flag %s'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `streams_arguments` WHERE argument_key = 'my_key');
```

3. **No mezcles DDL y DML** dependientes entre sí en el mismo archivo. Si necesitas añadir una columna y luego llenarla — usa dos archivos de migración.

4. Los **comentarios** se soportan con el prefijo `--` (se omiten al ejecutar).

### Paso 3. Coloca el archivo

Copia el archivo de migración a:

```
/home/uypanel/migrations/
```

> 💡 En el repositorio de código es `src/migrations/`.

### Paso 4. Testeo

Ejecuta el comando status para aplicar las migraciones pendientes:

```bash
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php status first-run
```

Salida esperada:

```
Migrations
------------------------------
  [OK]   006_add_user_timezone.sql

```

Si una consulta falla, la migración igualmente queda registrada pero mostrará `[WARN]` — revisa el SQL y corrige los problemas.

---

## ⚙️ Operaciones CLI típicas

### Comprobación de estado

```bash
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php status
```

Comprueba si UyPanel está en marcha, se conecta a la BD, aplica las migraciones pendientes, corrige permisos y valida la configuración de nginx. Necesario tras la instalación o una restauración.

Con el argumento `first-run` omite la comprobación de estado — se usa en la configuración inicial:

```bash
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php status first-run
```

### Gestión del servicio

```bash
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php service start|stop|restart|reload
```

### Actualización manual

```bash
sudo -u uypanel /home/uypanel/bin/php/bin/php /home/uypanel/console.php update update
```

Descarga y aplica la última actualización desde GitHub. Normalmente se lanza automáticamente desde el panel web.

### Diagnóstico de streams

```bash
sudo -u uypanel /home/uypanel/bin/php/bin/php /home/uypanel/console.php monitor <stream_id>
```

Arranca el stream manualmente y muestra los errores. Útil para diagnosticar problemas de arranque de streams.

### Certificado SSL

```bash
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php certbot
```

### Migración (desde otros sistemas)

```bash
/home/uypanel/bin/php/bin/php /home/uypanel/console.php migrate
```

Traslada los datos desde la base de migración. Detalles en la [Guía de migración](es-es/info/migration_guide.md).

---
