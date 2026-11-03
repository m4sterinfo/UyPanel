<h1 align="center">📦 UyPanel — Sistema de build (MAIN vs LB)</h1>

<p align="center">
  Cómo UyPanel crea dos variantes de build desde una única base de código:<br>
  un servidor MAIN completo y un servidor ligero de balanceo de carga (LB).
</p>

---

## 📚 Navegación

* [🏗 Variantes de build](#variantes-de-build)
* [⚙️ Targets del Makefile](#targets-del-makefile)
* [📂 Qué incluye cada build](#qué-incluye-cada-build)
* [🔀 MAIN vs LB — diferencias clave](#main-vs-lb--diferencias-clave)
* [🌐 Configuración nginx del LB](#configuración-nginx-del-lb)
* [🔧 Comportamiento del LB en runtime](#comportamiento-del-lb-en-runtime)
* [➕ Añadir código nuevo a los builds](#añadir-código-nuevo-a-los-builds)
* [✅ Verificación del build](#verificación-del-build)

---

## 🏗 Variantes de build

UyPanel soporta dos roles de despliegue desde un único árbol de código:

| Variante | Archivo | Propósito |
|---|---|---|
| **MAIN** | `uypanel.tar.gz` | Aplicación completa — panel admin, streaming, todos los módulos, tareas cron |
| **LB** (Load Balancer) | `loadbalancer.tar.gz` | Solo streaming — sin panel admin, sin gestión de usuarios |

**MAIN** es el servidor principal que gestiona todo: la UI del administrador, escritura en BD, gestión de usuarios/dispositivos, procesado de EPG, backups, etc.

**LB** es un nodo de streaming ligero que recibe los streams de MAIN (u otras fuentes) y los entrega a los clientes. Se conecta a la BD principal en modo solo-lectura y no tiene panel admin ni herramientas de gestión.

---

## ⚙️ Targets del Makefile

| Target | Resultado | Descripción |
|---|---|---|
| `make main` | `dist/uypanel.tar.gz` | Build MAIN completo |
| `make lb` | `dist/loadbalancer.tar.gz` | Build LB (solo streaming) |
| `make main_update` | `dist/update.tar.gz` | Actualización incremental de MAIN |
| `make lb_update` | `dist/loadbalancer_update.tar.gz` | Actualización incremental del LB |
| `make new` | Ambos builds completos | Atajo: `main` + `lb` |

Artefactos adicionales:
- `UyPanel.zip` — paquete instalador (`install/` + `uypanel.tar.gz`)
- `hashes.md5` — sumas MD5 para verificar la integridad

---

## 📂 Qué incluye cada build

### Build MAIN

El build MAIN contiene **todo** el directorio `src/` completo.

### Build LB — directorios incluidos

Al archivo LB solo se copian estos directorios:

```
bin/        cli/        config/     content/    core/
domain/     includes/   infrastructure/         resources/
signals/    streaming/  tmp/        www/
```

Más los archivos raíz: `autoload.php`, `bootstrap.php`, `console.php`, `service`, `status`, `update`.

### Build LB — contenido excluido

Tras la copia, del build LB se **elimina** el contenido específico de administración:

**Directorios eliminados:**
| Ruta | Motivo |
|---|---|
| `bin/install/` | Scripts de instalación (no se necesitan en el LB) |
| `bin/redis/` | Binario Redis (el LB no arranca su propio Redis) |
| `bin/nginx/conf/codes/` | Páginas de códigos de error (UI admin) |
| `includes/langs/` | Archivos de idioma del panel admin |
| `includes/api/` | Rutas de la API admin |
| `includes/libs/resources/` | Librerías de recursos del admin |
| `includes/bootstrap/` | Bootstrap del admin |
| `domain/User/` | Gestión de usuarios |
| `domain/Device/` | Registro de dispositivos |
| `domain/Auth/` | Autorización (de panel) |
| `resources/langs/` | Recursos de idioma |
| `resources/libs/` | Recursos de librerías |

**Archivos eliminados:**
| Archivo | Motivo |
|---|---|
| `includes/admin.php`, `includes/admin_api.php` | Lógica del panel admin |
| `includes/reseller_api.php` | API de resellers |
| `www/xplugin.php`, `www/probe.php`, `www/playlist.php` | Endpoints del admin |
| `www/player_api.php`, `www/epg.php`, `www/enigma2.php` | API de clientes (la atiende MAIN) |
| `www/admin/api.php`, `www/admin/proxy_api.php` | API admin |
| `config/rclone.conf` | Config de backups |
| `domain/Epg/EPG.php` | Clase de procesado de EPG |
| `bin/nginx/conf/gzip.conf` | Config gzip (el LB usa el suyo) |

**Comandos CLI eliminados:**
| Archivo | Motivo |
|---|---|
| `cli/Commands/MigrateCommand.php` | Migración solo en MAIN |
| `cli/Commands/CacheHandlerCommand.php` | Gestor de caché solo en MAIN |
| `cli/Commands/BalancerCommand.php` | Instalador de LB (no se necesita en el propio LB) |

**Tareas cron eliminadas:**
| Archivo | Motivo |
|---|---|
| `cli/CronJobs/RootMysqlCronJob.php` | Mantenimiento de BD (solo MAIN) |
| `cli/CronJobs/BackupsCronJob.php` | Backups (solo MAIN) |
| `cli/CronJobs/CacheEngineCronJob.php` | Regeneración completa de caché (solo MAIN) |
| `cli/CronJobs/EpgCronJob.php` | Procesado de EPG (solo MAIN) |
| `cli/CronJobs/UpdateCronJob.php` | Comprobación de actualizaciones (solo MAIN) |
| `cli/CronJobs/ProvidersCronJob.php` | Sincronización de providers (solo MAIN) |
| `cli/CronJobs/SeriesCronJob.php` | Metadatos de series (solo MAIN) |

> **Nota:** Los crons de módulos (TMDB, Plex, Watch) ahora viven en `modules/<name>/` y quedan automáticamente excluidos de los builds LB, ya que `modules/` no está en `LB_DIRS`.

### Build LB — configs reemplazadas

Estos archivos de `lb_configs/` **reemplazan** las versiones de MAIN:

| Origen | Destino | Propósito |
|---|---|---|
| `lb_configs/nginx.conf` | `bin/nginx/conf/nginx.conf` | Nginx optimizado para streaming |
| `lb_configs/live.conf` | `bin/nginx_rtmp/conf/live.conf` | Hooks RTMP de autenticación |

---

## 🔀 MAIN vs LB — diferencias clave

| Aspecto | MAIN | LB |
|---|---|---|
| Panel admin | ✅ UI completa | ❌ No incluido |
| Rol de la BD | Lectura + Escritura | Solo lectura |
| Gestión de usuarios/dispositivos | ✅ | ❌ |
| Procesado de EPG | ✅ | ❌ |
| Backups | ✅ | ❌ |
| Herramienta de migración | ✅ | ❌ |
| Entrega de streams | ✅ | ✅ |
| Recepción RTMP | ✅ | ✅ |
| Transcodificación (FFmpeg) | ✅ | ✅ |
| Comandos CLI | 26 | ~15 (los de admin eliminados) |
| Tareas cron | 25 | ~16 (las de admin eliminadas) |
| Sistema de módulos | ✅ | ❌ |

---

## 🌐 Configuración nginx del LB

El build LB usa una configuración nginx especial, optimizada para streaming de alto throughput:

| Ajuste | Valor | Propósito |
|---|---|---|
| Worker processes | `auto` | Escala según las CPUs |
| Worker connections | 16 000 | Alta concurrencia por worker |
| Máx. descriptores de archivo | 300 000 | Límite de recursos del sistema |
| Pool de threads | `pool_uypanel` (32 threads) | I/O asíncrono para streaming |
| Gzip | OFF | Los datos de streaming ya están comprimidos |
| Access logs | OFF | Reduce la carga de I/O |
| Rate limiting | 20 req/seg por IP | Protección anti-DDoS |
| Send timeout | 20 min | Soporta streams largos |

Los hooks RTMP (`lb_configs/live.conf`) enrutan la autenticación vía callbacks HTTP locales en lugar del panel admin:

```nginx
on_play http://127.0.0.1:8080/stream/rtmp;
on_publish http://127.0.0.1:8080/stream/rtmp;
on_play_done http://127.0.0.1:8080/stream/rtmp;
```

---

## 🔧 Comportamiento del LB en runtime

### Carga condicional de comandos

`console.php` usa comprobaciones `file_exists()` para los comandos que pueden no existir en los servidores LB:

```php
if (file_exists(__DIR__ . '/cli/Commands/CacheHandlerCommand.php')) {
    $rRegistry->register(new CacheHandlerCommand());
}
```

Esto evita caídas cuando el LB intenta registrar un comando cuyo archivo fue eliminado en el build.

### Cadena de dependencias del streaming

Los servidores LB conservan el pipeline de streaming completo:

```
www/stream.php
  ├── autoload.php
  ├── bootstrap.php (CONTEXT_STREAM)
  ├── core/* (Config, Database, Cache, Auth, Http, Logging, Util)
  ├── domain/Stream, domain/Server, domain/Vod, domain/Bouquet
  ├── streaming/* (Auth, Delivery, Codec, Protection)
  ├── infrastructure/redis, infrastructure/database
  └── resources/data
```

---

## ➕ Añadir código nuevo a los builds

### Nuevo directorio bajo `src/`, necesario para el streaming

Añádelo a `LB_DIRS` en el Makefile:

```makefile
LB_DIRS = bin cli config content core domain ... your_dir
```

### Nuevo directorio solo-admin

Añádelo a `LB_DIRS_TO_REMOVE`:

```makefile
LB_DIRS_TO_REMOVE = ... your_dir/admin_stuff
```

### Nuevo archivo solo-admin

Añádelo a `LB_FILES_TO_REMOVE`:

```makefile
LB_FILES_TO_REMOVE = ... your_dir/admin_file.php
```

### Nuevo comando CLI (solo MAIN)

1. Añade la comprobación `file_exists()` en `console.php`
2. Añade el archivo a `LB_FILES_TO_REMOVE`

---

## ✅ Verificación del build

Tras modificar el build, comprueba ambas variantes:

```bash
# Construir ambas
make new

# Comprobar que el LB contiene el código de streaming
tar -tzf dist/loadbalancer.tar.gz | grep -cE "core/|domain/Stream|streaming/"
# Esperado: > 0

# Comprobar que el LB NO contiene código admin
tar -tzf dist/loadbalancer.tar.gz | grep -cE "admin/|player/|ministra|reseller"
# Esperado: 0

# Comparar tamaños (el LB debe ser bastante menor)
ls -lh dist/uypanel.tar.gz dist/loadbalancer.tar.gz
```
