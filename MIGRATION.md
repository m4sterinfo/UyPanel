# UyPanel — Plan de migración

> Este documento describe el **orden de migración**, los **detalles de cada fase** y la **estrategia de gestión de riesgos**.
> Para los principios de arquitectura, estructura del proyecto y descripción de componentes, véase [ARCHITECTURE.md](ARCHITECTURE.md).

## Contenido

1. [Principio de migración](#1-principio-de-migración)
2. [Fases 0–9: Completadas](#2-fases-09-completadas)
3. [Fase 10: Eliminación de legacy admin entry-points ✅](#3-fase-10-eliminación-de-legacy-admin-entry-points)
4. [Fase 11: Unificación de API ✅ (11.1–11.4)](#4-fase-11-unificación-de-api-111114)
5. [Fase 12: CLI — runner único ✅](#5-fase-12-cli-runner-único)
6. [Fase 13: Streaming entry points ✅ (13.1–13.3)](#6-fase-13-streaming-entry-points-131133)
7. [Fase 14: CSS/JS partials](#7-fase-14-cssjs-partials)
8. [Fase 15: Eliminación de includes/admin.php](#8-fase-15-eliminación-de-includesadminphp)
9. [Orden de ejecución y matriz de riesgos](#9-orden-de-ejecución-y-matriz-de-riesgos)
10. [Estrategia de migración por riesgos](#10-estrategia-de-migración-por-riesgos)

---

## 1. Principio de migración

### Extraer → delegar → reemplazar

Cada paso de la migración sigue un mismo patrón:

```
1. Crear la nueva clase en el directorio destino
2. Mover a ella los métodos del god-object
3. En el archivo antiguo dejar un método proxy:
     public static function oldMethod(...$args) {
         return NewClass::method(...$args);
     }
4. Registrar la clase en el autoloader
5. Verificar: el sistema funciona igual que antes
6. (más tarde) Actualizar el código llamante → eliminar el proxy
```

Así cada paso es seguro y retrocompatible.

---

## 2. Fases 0–9: Completadas

### Fase 0: Preparación ✅

Autoload, esqueleto de directorios, bootstrap.php, ServiceContainer, división de constants.php → 7 archivos core.

---

### Fase 1: Extracción de core/ ✅

Database, Cache, Http/Request, Auth, Process, Util — todos los componentes base extraídos de los god-objects.

### Fase 1.7: Extracciones restantes de core/ ✅

Logging, SystemInfo, BruteforceGuard, CurlClient, EventDispatcher, Authorization, Authenticator, ImageUtils — 8 pasos completados.

---

### Fase 2: Deduplicación CoreUtilities ↔ StreamingUtilities ✅

53 métodos duplicados deduplicados: Redis/señales, tracking de conexiones, datos de referencia, init().

---

### Fase 3: Extracción de domain/ — lógica de negocio ✅

12 contextos de dominio: Stream, Vod, Line, User, Device, Server, Bouquet, Epg, Settings/Ticket, Security, Auth, Playlist. Todas las entity/repository/service extraídas.

---

### Fase 4: Extracción de streaming/ (hot path) ✅

streaming/Auth, Delivery, Codec, Protection/Health, StreamingBootstrap — bootstrap ligero para el hot path.

---

### Fase 5: Extracción de módulos ✅

6 módulos extraídos de forma atómica: plex, watch, tmdb, ministra, fingerprint/theft-detection/magscan. ModuleInterface + ModuleLoader. Thread/Multithread deduplicados en `core/Process/`.

- 🔲 `ministra/*.js` → modules/ministra/assets/ (archivos JS del portal — aplazado a la Fase 13.4)

---

### Fase 6: Controladores y Views (admin/reseller) ✅

#### Paso 6.1 — Layout unificado ✅

Unified wrappers: `public/Views/layouts/admin.php` + `footer.php`.
- **Admin: 112/112 archivos de página — 100% migrados**
- **Reseller: 22/22 archivos de página — 100% migrados**
- ⏭️ CSS/JS partials — **aplazado** a la Fase 14 (footer.php: ~800 líneas de JS inline específico de página)

#### Paso 6.2 — Router + Front Controller ✅

`core/Http/Router.php` (450 líneas), `public/index.php` (Front Controller), `Request.php`, `Response.php`, `RequestGuard.php`. Parsing de URL en tres modos (Access Code + XC_SCOPE / URL directa / fallback). Access Codes soportados.

#### Paso 6.3 — Conversión de páginas admin (Controller/View) ✅

**111/111 páginas admin** migradas: Controller + View + Scripts + routes. Patrón: Thin Controller → Service → View. `BaseAdminController` + `_scripts_init.php`.

#### Paso 6.4 — Unificación admin/reseller ✅

22 páginas reseller migradas. `BaseResellerController` + 22 controladores/views/rutas.

#### Paso 6.5 — Estabilización del contrato Controller/View ✅

Dos pasadas de estabilización: viewGlobals ampliado, nullable-guards para foreach/in_array/count.

#### Paso 6.5b — Auditoría nullable de views reseller ✅

Correcciones a nivel de fuente: `getPermissions()` → fallback `[]`, defaults defensivos en `functions.php`/`table.php` para `direct_reports`, `all_reports`, `stream_ids`, `category_ids`, `series_ids`, `subresellers`. Correcciones puntuales P0/P1 en 10 archivos.

---

### Fase 7: Migración del bootstrap admin.php

#### Paso 7.1 — Extracción de datos inline ✅
Los datos del bootstrap se consolidaron en `resources/data/admin_constants.php`.

#### Paso 7.2 — Reemplazo del bootstrap procedimental ✅
8 incrementos: runtime → `bootstrapAdminRuntime()` → `admin_session.php` + `admin_runtime.php` → `XC_Bootstrap::boot(CONTEXT_ADMIN)` → fachada `admin_bootstrap.php`.

#### Paso 7.3 — Eliminación de envolturas proxy de admin.php ✅
40 definiciones proxy eliminadas, 560+ call-sites reemplazados por llamadas directas a servicios de domain. `admin.php` reducido de ~4448 a ~3050 líneas.

#### Paso 7.3.1 — Migración de getCategories/getOutputs ✅
`getCategories()` → `CategoryService::getAllByType()` (~75+ call-sites). `getOutputs()` → `OutputFormatRepository::getAll()` (3 call-sites). Creado `domain/Line/OutputFormatRepository.php`.

#### Paso 7.4 — Eliminación del parámetro `$db` de las clases Domain ✅
28 clases (100 métodos) → `global $db` interno. ~357 call-sites actualizados.

---

### Fase 8: Liquidación de los god-objects ✅

**Objetivo:** Eliminar los tres archivos-monolito (`CoreUtilities.php`, `StreamingUtilities.php`, `admin_api.php`), reemplazando ~7 400 llamadas externas por acceso directo a las clases destino.

| Archivo | Líneas antes | Métodos | Llamadas externas | Estado |
|------|:----------:|:-------:|:---------------:|--------|
| `admin_api.php` | 3 686 | 79 | ~300 | ✅ 8.1 — eliminado |
| `StreamingUtilities.php` | 659 | 78 | 1 344 | ✅ 8.2 — eliminado |
| `CoreUtilities.php` | 1 971 | 152 | 5 755 | ✅ 8.3–8.11 — eliminado |

#### Paso 8.1 — admin_api.php ✅
60 métodos PROXY + 18 OWN migrados a servicios de domain. Archivo eliminado.

#### Paso 8.2 — StreamingUtilities.php ✅
42 métodos PROXY + 33 OWN, 18 propiedades. ~314 referencias reemplazadas en 10 lotes. Archivo eliminado.

#### Paso 8.3 — Métodos de CoreUtilities ✅
81 métodos PROXY + 69 OWN extraídos a clases destino (17 lotes). CU reducido de 1 971 a 40 líneas.

#### Pasos 8.4–8.6 — Propiedades (blocklist, FFmpeg, light) ✅
- **8.4** 7 propiedades blocklist/allowlist → lazy getters de `BlocklistService` (FileCache)
- **8.5** 3 propiedades FFmpeg → value-object `FfmpegPaths::resolve()`
- **8.6** 3 propiedades ($rCategories, $rBouquets, $rSegmentSettings) → getters de servicio

#### Pasos 8.7–8.10 — Propiedades de infraestructura → singletons ✅
- **8.7** `$rCached` → `FileCache`, `$rConfig` → `ConfigReader`, `$rServers` (184 refs) → `ServerRepository::getAll()`
- **8.8** `$db` (30 refs) → `DatabaseFactory::set()/get()`, `$redis` (62 refs) → `RedisManager::instance()`
- **8.9** `$rSettings` (819 refs, 221 archivos) → `SettingsManager::set()/getAll()/get()/update()`
- **8.10** `$rRequest` (3863 refs, 166 archivos) → `RequestManager::set()/getAll()/get()/update()`

#### Paso 8.11 — Eliminación de CoreUtilities.php ✅
6 call-sites `CoreUtilities::init()` → `LegacyInitializer::initCore()`. Archivo eliminado físicamente.

**Evolución de CU:**

| Fase | Líneas | Métodos | Propiedades |
|------|------:|--------:|--------:|
| Antes del refactoring | 1 971 | 152 | 21 |
| Tras 8.3 | 40 | 3 | 21 |
| Tras 8.4–8.6 | 19 | 2 | 8 |
| Tras 8.7–8.8 | 9 | 1 | 2 |
| Tras 8.9–8.10 | 5 | 1 | 0 |
| **8.11 — ELIMINADO** | **0** | **0** | **0** |

**Nuevas clases singleton/service (Fase 8):** `SettingsManager`, `RequestManager`, `ConfigReader`, `DatabaseFactory` (singleton), `RedisManager` (singleton), `FfmpegPaths`, `FileCache`, `DataEncryptor`, `InputSanitizer`, `IpUtils`, `UrlBuilder`, `ImageUtils`, `Helpers`, `ProcessManager`, `ConnectionManager`, `BackupService`, `ProviderService`, `ProfileService`, `RadioService`, `SystemCheck`, `InputValidator`.

---

### Fase 9: Estabilización del build ✅

> **Objetivo de la fase:** tras los refactors masivos (Fases 7–8), llevar el proyecto a un build "verde": Makefile correcto, cabeceras PHP estandarizadas, layout unificado, exportación de globals.

#### Paso 9.1 — Makefile: build LB para la nueva arquitectura ✅

**Problema:** `LB_FILES` no incluía `core/`, `domain/`, `streaming/`, `infrastructure/`, `resources/`, `autoload.php`, `bootstrap.php` — el build LB no podía funcionar con el código migrado.

**Solución (5 cambios en el Makefile):**
1. `LB_FILES` → `LB_DIRS` (14 dirs) + `LB_ROOT_FILES` (5 archivos raíz)
2. `lb_copy_files`: segundo bucle para archivos raíz vía `git ls-files --error-unmatch`
3. `lb_update_copy_files`: comprobación en cascada (dirs → root files) para actualizaciones delta
4. `LB_DIRS_TO_REMOVE`: +6 exclusiones solo-admin (`includes/bootstrap`, `domain/User`, `domain/Device`, `domain/Auth`, `resources/langs`, `resources/libs`)
5. `set_permissions`: `core/ domain/ streaming/ infrastructure/ resources/` → dirs:755, files:644; PHP raíz → 644

#### Paso 9.2 — Estandarización de cabeceras PHP (Clean Headers) ✅

**Problema:** los archivos de views admin y reseller comenzaban de forma heterogénea — imposible incluirlos de forma segura como fragmentos de view desde el controlador del layout.

**Solución:** condición guard `$__viewMode` (script `tools/clean_headers.py`, 4 iteraciones):

```php
<?php if (!isset($__viewMode)): ?>
    <?php
    include 'session.php';
    include 'functions.php';
    renderUnifiedLayoutHeader('admin');
    ?>
<?php endif; // !$__viewMode ?>
```

**Alcance:** 112 archivos admin + 22 archivos reseller.

#### Paso 9.3 — Migración de view-layout: renderUnifiedLayoutHeader ✅

**Solución:** `renderUnifiedLayoutHeader($scope, $vars)` / `renderUnifiedLayoutFooter($scope, $vars)` — envoltura única para incluir header/footer con extracción de 16 globals.

**Archivos nuevos:**
- `public/Views/layouts/admin.php`
- `public/Views/layouts/footer.php`

**Alcance:** 112 archivos admin + 22 reseller convertidos.

#### Paso 9.4 — Variables globales: exportación de datos singleton ✅

`LegacyInitializer::exportGlobals()` — exporta los datos singleton a `$GLOBALS` una vez en el bootstrap.

**Variables exportadas:** `$rSettings`, `$rRequest`, `$rConfig`, `$rServers`, `$rFFPROBE`, `$rFFMPEG_CPU`, `$rFFMPEG_GPU`, `$db` + contexto streaming: `$rCached`, `$rBlockedUA/ISP/IPs/Servers`, `$rAllowedIPs`, `$rProxies`, `$rBouquets`, `$rSegmentSettings`.

**Alcance:** 54 reemplazos en 20 archivos.

---

## 3. Fase 10: Eliminación de legacy admin entry-points ✅

> **Objetivo:** Todas las peticiones admin se sirven ÚNICAMENTE vía `public/index.php` (Front Controller → Router → Controller → View). El directorio `src/admin/` fue eliminado.

#### Paso 10.1 — Eliminación del fallback en el Front Controller ✅

**Lo hecho:**
1. Creados controladores para 7 páginas aún no enrutadas:
   - `LogoutController` — destroySession + redirect
   - `PlayerEmbedController` — hace proxy de admin/player.php
   - `PostController` — establece `$GLOBALS['__forcePostMode']`, hace proxy de admin/post.php
   - `LoginController` — hace proxy de admin/login.php (documento HTML propio)
   - `SetupController` — métodos `index()` (setup.php) y `database()` (database.php)
2. Añadidas 7 rutas en `public/routes/admin.php` (logout, player, post, login, setup, database, index)
3. Unificada la sección 4a del FC: todos los scopes (admin/reseller/player) usan el Router para noBootstrapPages
4. La sección 7 (legacy fallback) envuelta con el feature flag `$rSettings['use_legacy_fallback']` (default: true)
5. Modificado `admin/post.php`: `$rICount` ahora considera `$GLOBALS['__forcePostMode']`

#### Paso 10.2 — Migración de admin/login.php → LoginController ✅

LoginController creado como thin proxy: chdir(admin/) + require login.php. Rutas `login` e `index` registradas.

#### Paso 10.3 — Migración de admin/setup.php, database.php → SetupController ✅

SetupController: los métodos index() y database() hacen proxy a los archivos legacy. Rutas registradas.

#### Paso 10.4 — Migración de admin/api.php → AjaxController ✅

AjaxController — thin proxy: chdir(admin/) + require api.php. Ruta `$router->any('api', ...)`. El API-fallback del FC envuelto con feature flag.

#### Paso 10.5 — Consolidación de session.php + functions.php ✅

**Creado:**
- `infrastructure/bootstrap/admin_session_fc.php` — versión limpia de admin/session.php para la vía FC
- `infrastructure/bootstrap/admin_functions_fc.php` — versión limpia de admin/functions.php

El FC cambiado a `infrastructure/bootstrap/admin_*_fc.php`. Los originales se mantienen para acceso directo por nginx.

#### Paso 10.6 — Eliminación del directorio src/admin/ ✅

**Lo hecho:**
1. 127 archivos PHP movidos de `admin/` → `public/Views/admin/`
2. 423 archivos estáticos (CSS, fonts, images, JS, libs) movidos de `admin/assets/` → `public/assets/admin/`
3. El directorio `src/admin/` eliminado por completo
4. Actualizados todos los enlaces:
   - **FC** (`public/index.php`): `$adminDir = MAIN_HOME . 'public/Views/admin/'`
   - **5 controladores**: LoginController, SetupController, PostController, PlayerEmbedController, AjaxController — rutas a `public/Views/admin/`
   - **2 archivos de layout**: `dirname(__DIR__) . '/admin/header.php'` / `footer.php`
   - **BaseAdminController**: `$__viewMode = true` antes del require de la view
   - **112 archivos de view**: `__DIR__ . '/../layouts/'` (rutas relativas corregidas)
   - **8 archivos de view**: `dirname(__DIR__, 3) . '/modules/'` (profundidad corregida)
   - **AuthRepository.php**: `$rAlias` admin → `'public/Views/admin'`
   - **Makefile**: permissions `$(TEMP_DIR)/public/assets/admin`
5. La plantilla de nginx (`bin/nginx/conf/codes/template`) ya era correcta: `alias /home/uypanel/public/assets/#TYPE#/`
6. Chequeo de sintaxis: 126/127 pasan (review.php — bug preexistente, no regresión)

---

## 4. Fase 11: Unificación de API — externa e interna ✅ (11.1–11.4)

> **Objetivo:** Una única capa de API vía `public/Controllers/Api/` con enrutado por Router. Eliminación de los archivos PHP independientes en `src/www/` (`player_api.php`, `enigma2.php`, `epg.php`, `playlist.php`, `xplugin.php`).

#### Paso 11.1 — Player API → PlayerApiController ✅

**Resultado:** `public/Controllers/Api/PlayerApiController.php` (843 líneas, 10 actions). Bootstrap vía `stream/init.php` (hot path). Auth vía `UserRepository::getStreamingUserInfo()`. Standalone — NO hereda de BaseApiController.

`www/player_api.php` → thin proxy (7 líneas).

#### Paso 11.2 — Enigma2 API → Enigma2ApiController + XPluginApiController ✅

**Resultado:** separados (en lugar de unirlos como estaba previsto) — auth, formato de salida y dominio distintos:
- `Enigma2ApiController.php` (507 líneas, 10 XML-actions)
- `XPluginApiController.php` (181 líneas, gestión de dispositivos)
- `SimpleXMLExtended` extraído a una clase aparte (estaba inline en switch/default — bug)

`www/enigma2.php` y `www/xplugin.php` → thin proxies (7 líneas cada uno).

#### Paso 11.3 — Playlist/EPG → PlaylistApiController, EpgApiController ✅

**Resultado:**
- `PlaylistApiController.php` (62 líneas) — generación M3U/M3U8
- `EpgApiController.php` (105 líneas) — generación XMLTV XML
- `BaseApiController.php` (109 líneas) — clase base común

`www/playlist.php` y `www/epg.php` → thin proxies.

#### Paso 11.4 — Internal Server API → InternalApiController ✅

**Resultado:** `InternalApiController.php` (506 líneas, 30+ actions). Auth: password + whitelist de IPs (server-to-server). Extraídos: `serveFile()`, `probeStream()`, `killProcessGroup()`.

`www/api.php` → thin proxy.

**Estadísticas:** 7 archivos de controladores = 2313 líneas. 6 archivos proxy = 42 líneas.

#### Paso 11.5 — Admin/Reseller REST API → rutas unificadas ⏳

**Estado:** APLAZADO. Las clases `APIWrapper` están embebidas en `index.php` (2065+556 líneas, mismo nombre de clase). Se requiere: renombrar clases, extracción, resolución del conflicto de namespace. PR aparte.

#### Paso 11.6 — Eliminación de archivos API legacy ⏳

**Estado:** APLAZADO. Requiere enrutado de API por el FC (scope=api). Los thin proxies `www/*.php` deben mantenerse hasta enrutar la API por el Front Controller.

**Precondiciones:** 11.5 completado, rewrites de nginx actualizados.

---

## 5. Fase 12: CLI — runner único y comandos estructurados ✅

> **Objetivo:** Todos los scripts CLI (`includes/cli/`, `crons/`, `src/service`, `src/tools`, `src/status`) se ejecutan a través del punto de entrada único `console.php`. Cada script es una clase-comando con la interfaz `CommandInterface`.

#### Paso 12.1 — Console entry point + CommandInterface ✅

**Creado:**
```
cli/
├── CommandInterface.php     # interface: getName(), getDescription(), execute(array $args): int
├── CommandRegistry.php      # Registro de comandos (name → class)
├── DaemonTrait.php          # Boilerplate común para daemons
├── Commands/                # 26 comandos
└── CronJobs/                # 25 tareas cron
console.php                  # Punto de entrada único (nivel raíz)
```

**Ejecución:**
```bash
# Antes:
php /home/uypanel/includes/cli/startup.php
php /home/uypanel/crons/servers.php

# Ahora:
php /home/uypanel/console.php startup
php /home/uypanel/console.php cron:servers
```

#### Paso 12.2 — Migración de includes/cli/ → cli/Commands/ ✅

**Resultado:** 24 archivos de `includes/cli/` → 26 clases Command en `cli/Commands/`. Toda la lógica trasladada; los archivos proxy se crearon y luego se eliminaron (paso 12.6).

| Archivo legacy | → Command | Tipo |
|---|---|---|
| `startup.php` | `Commands/StartupCommand` | daemon |
| `watchdog.php` | `Commands/WatchdogCommand` | daemon |
| `signals.php` | `Commands/SignalsCommand` | daemon |
| `queue.php` | `Commands/QueueCommand` | daemon |
| `cache_handler.php` | `Commands/CacheHandlerCommand` | daemon |
| `connection_sync.php` | `Commands/ConnectionSyncCommand` | daemon |
| `monitor.php` | `Commands/MonitorCommand` | CLI |
| `scanner.php` | `Commands/ScannerCommand` | CLI |
| `balancer.php` | `Commands/BalancerCommand` | CLI |
| `migrate.php` | `Commands/MigrateCommand` | CLI |
| `tools.php` | `Commands/ToolsCommand` | CLI |
| `update.php` | `Commands/UpdateCommand` | CLI |
| `binaries.php` | `Commands/BinariesCommand` | CLI |
| `archive.php` | `Commands/ArchiveCommand` | CLI |
| `created.php` | `Commands/CreatedCommand` | CLI |
| `delay.php` | `Commands/DelayCommand` | CLI |
| `loopback.php` | `Commands/LoopbackCommand` | CLI |
| `llod.php` | `Commands/LlodCommand` | CLI |
| `ondemand.php` | `Commands/OndemandCommand` | CLI |
| `plex_item.php` | `Commands/PlexItemCommand` | CLI |
| `proxy.php` | `Commands/ProxyCommand` | CLI |
| `record.php` | `Commands/RecordCommand` | CLI |
| `thumbnail.php` | `Commands/ThumbnailCommand` | CLI |
| `watch_item.php` | `Commands/WatchItemCommand` | CLI |
| — | `Commands/CertbotCommand` | CLI |
| — | `Commands/ServiceCommand` | CLI |

#### Paso 12.3 — Migración de crons/ → cli/CronJobs/ ✅

**Resultado:** 25 archivos cron → 25 clases CronJob en `cli/CronJobs/`. Los archivos proxy y el directorio `crons/` eliminados (paso 12.8).

| Archivo legacy | → CronJob | Notas |
|---|---|---|
| `activity.php` | `CronJobs/ActivityCronJob` | |
| `backups.php` | `CronJobs/BackupsCronJob` | |
| `cache.php` | `CronJobs/CacheCronJob` | |
| `cache_engine.php` | `CronJobs/CacheEngineCronJob` | |
| `certbot.php` | `CronJobs/CertbotCronJob` | |
| `cleanup.php` | `CronJobs/CleanupCronJob` | |
| `epg.php` | `CronJobs/EpgCronJob` | Grande: clase EPG + parsing XML |
| `errors.php` | `CronJobs/ErrorsCronJob` | |
| `lines_logs.php` | `CronJobs/LinesLogsCronJob` | |
| `plex.php` | `CronJobs/PlexCronJob` | Módulo plex |
| `providers.php` | `CronJobs/ProvidersCronJob` | |
| `root_mysql.php` | `CronJobs/RootMysqlCronJob` | Requiere root, excluido del LB |
| `root_signals.php` | `CronJobs/RootSignalsCronJob` | Requiere root |
| `series.php` | `CronJobs/SeriesCronJob` | |
| `servers.php` | `CronJobs/ServersCronJob` | |
| `stats.php` | `CronJobs/StatsCronJob` | |
| `streams.php` | `CronJobs/StreamsCronJob` | |
| `streams_logs.php` | `CronJobs/StreamsLogsCronJob` | |
| `tmdb.php` | `CronJobs/TmdbCronJob` | Módulo tmdb |
| `tmdb_popular.php` | `CronJobs/TmdbPopularCronJob` | Módulo tmdb |
| `tmp.php` | `CronJobs/TmpCronJob` | |
| `update.php` | `CronJobs/UpdateCronJob` | |
| `users.php` | `CronJobs/UsersCronJob` | |
| `vod.php` | `CronJobs/VodCronJob` | |
| `watch.php` | `CronJobs/WatchCronJob` | Módulo watch |

#### Paso 12.4 — Migración de scripts de nivel raíz ✅

| Legacy | → | Estado |
|---|---|---|
| `src/service` (shell) | `console.php service:{start\|stop\|restart\|reload}` | ✅ ServiceCommand |
| `src/status` (PHP) | `console.php status` | ✅ StatusCommand |
| `src/tools` (PHP) | `console.php tools:{rescue\|access\|ports}` | ✅ ToolsCommand |
| `src/update` (Python) | Sigue siendo script Python | Sin cambios |

#### Paso 12.5 — Actualización de daemons.sh y crontab ✅

1. ✅ `bin/daemons.sh` → ejecución vía `console.php {command}`
2. ✅ El crontab se genera en `StartupCommand::installCrontab()` / `installRootCrontab()` → `console.php cron:{name}`
3. ✅ `src/service` → `console.php service:{start|stop|restart|reload}`

#### Paso 12.6 — Eliminación de archivos CLI legacy ✅

**Ejecutado:**
1. ✅ Constante `CLI_PATH` eliminada de `core/Config/Paths.php`
2. ✅ 37 referencias a `CLI_PATH` reemplazadas por `MAIN_HOME . 'console.php {command}'` en 14 archivos
3. ✅ `includes/cli/` — **carpeta eliminada** (24 archivos)
4. ✅ Makefile: 3 entradas antiguas de `includes/cli/` eliminadas de EXCLUDES
5. ✅ `php -l` — los 14 archivos verificados, sintaxis OK
6. ✅ `crons/` — **carpeta eliminada** (25 archivos proxy), ver paso 12.8

**Patrón de reemplazo:**
```
# Antes:
PHP_BIN . ' ' . CLI_PATH . 'script.php args'
# Ahora:
PHP_BIN . ' ' . MAIN_HOME . 'console.php command args'
```

**Casos especiales:**
- `OndemandCommand`: `md5_file(CLI_PATH . 'ondemand.php')` → `md5_file(__FILE__)` (detección de auto-modificación)
- `BalancerCommand`: ambas ramas `$rType == 2` → `console.php startup` (usaban rutas legacy distintas)

#### Paso 12.8 — Eliminación de CRON_PATH y del directorio crons/ ✅

**Problema:** 25 archivos proxy en `crons/` y la constante `CRON_PATH` — residuos legacy tras la migración cron → `console.php cron:{name}`. 34+ referencias a `CRON_PATH` en la base de código.

**Ejecutado:**
1. ✅ 35 referencias a `CRON_PATH` reemplazadas por `MAIN_HOME . 'console.php cron:{name}'` en 9 archivos
2. ✅ Constante `CRON_PATH` eliminada de `core/Config/Paths.php`
3. ✅ Mapeo autoload muerto `EPG → crons/epg.php` eliminado
4. ✅ Clase `EPG` restaurada en `domain/Epg/EPG.php` (se había perdido en la conversión proxy)
5. ✅ `src/crons/` — **carpeta eliminada** (25 archivos proxy)
6. ✅ Makefile: `crons` quitado de `LB_DIRS`, 9 entradas `crons/*.php` de `LB_FILES_TO_REMOVE`, 2 líneas de permissions
7. ✅ 8 archivos CronJob (solo MAIN) añadidos a `LB_FILES_TO_REMOVE` + guards `file_exists()` en `console.php`
8. ✅ `MigrationRunner::runFileCleanup()` — ahora elimina los directorios vaciados
9. ✅ Ruta legacy en `post.php` (`MAIN_HOME . '/php/bin/php ' . MAIN_HOME . '/crons/epg.php'`) corregida

**Patrón de reemplazo:**
```
# Antes:
PHP_BIN . ' ' . CRON_PATH . 'name.php args'
# Ahora:
PHP_BIN . ' ' . MAIN_HOME . 'console.php cron:name args'
```

**Archivos afectados:**
- `cli/CronJobs/CacheEngineCronJob.php` (9 reemplazos)
- `cli/Commands/StartupCommand.php` (4 reemplazos, incl. `file_exists()` → comprobación `cache_complete`)
- `cli/Commands/SignalsCommand.php` (2 reemplazos)
- `cli/Commands/BalancerCommand.php` (1 reemplazo)
- `cli/Commands/CertbotCommand.php` (1 reemplazo)
- `public/Controllers/Api/InternalApiController.php` (4 reemplazos)
- `public/Controllers/Api/AdminApiController.php` (5 reemplazos)
- `public/Views/admin/api.php` (8 reemplazos)
- `public/Views/admin/post.php` (1 reemplazo)

#### Paso 12.7 — Guard LB para comandos solo-MAIN ✅

**Problema:** 4 clases CLI excluidas del build LB vía `LB_FILES_TO_REMOVE`, pero `console.php` las registraba incondicionalmente → crash de `require_once` en el LB.

**Solución:** guards `file_exists()` en `console.php` para el registro condicional.

| Archivo | Guard en console.php | Guard en crontab |
|---|---|---|
| `cli/Commands/MigrateCommand.php` | ✅ `file_exists()` | — (no está en crontab) |
| `cli/Commands/CacheHandlerCommand.php` | ✅ `file_exists()` | — (no está en crontab) |
| `cli/Commands/BalancerCommand.php` | ✅ `file_exists()` | — (no está en crontab) |
| `cli/CronJobs/RootMysqlCronJob.php` | ✅ `file_exists()` | ✅ `file_exists()` en StartupCommand + StatusCommand |
| `cli/CronJobs/BackupsCronJob.php` | ✅ `file_exists()` | — |
| `cli/CronJobs/CacheEngineCronJob.php` | ✅ `file_exists()` | — |
| `cli/CronJobs/EpgCronJob.php` | ✅ `file_exists()` | — |
| `cli/CronJobs/UpdateCronJob.php` | ✅ `file_exists()` | — |
| `cli/CronJobs/ProvidersCronJob.php` | ✅ `file_exists()` | — |
| `cli/CronJobs/SeriesCronJob.php` | ✅ `file_exists()` | — |
| `modules/tmdb/TmdbCronJob.php` | ✅ auto-discovery | Movido al directorio del módulo |
| `modules/tmdb/TmdbPopularCronJob.php` | ✅ auto-discovery | Movido al directorio del módulo |
| `domain/Epg/EPG.php` | — | — (autoload) |

**Makefile `LB_FILES_TO_REMOVE`:**
```makefile
cli/Commands/MigrateCommand.php
cli/Commands/CacheHandlerCommand.php
cli/Commands/BalancerCommand.php
cli/CronJobs/RootMysqlCronJob.php
cli/CronJobs/BackupsCronJob.php
cli/CronJobs/CacheEngineCronJob.php
cli/CronJobs/EpgCronJob.php
cli/CronJobs/UpdateCronJob.php
cli/CronJobs/ProvidersCronJob.php
cli/CronJobs/SeriesCronJob.php
domain/Epg/EPG.php
```

---

## 6. Fase 13: Streaming entry points ✅ (13.1–13.3)

> **Objetivo:** Minimizar la duplicación de bootstrap en `www/stream/*.php`, SIN aumentar la latencia. El hot path se mantiene < 50ms p99.

> **IMPORTANTE:** El streaming es territorio sagrado. Nada de Router, nada de ServiceContainer, nada de autoload completo en el hot path. Solo mejoras puntuales.

#### Paso 13.1 — Punto de entrada streaming único (micro-router) ✅

**Resultado:** `www/stream/index.php` — micro-router con parámetro `?handler=`. Modo dormido: nginx sigue dirigiendo a los archivos concretos; el router se activa opcionalmente.

Overhead < 0.1ms (un array lookup).

#### Paso 13.2 — Deduplicación de shutdown handlers ✅

**Resultado:** `streaming/Lifecycle/ShutdownHandler.php` — reemplaza las `function shutdown()` duplicadas en live/vod/timeshift. Parámetro context para la limpieza específica de live (tmp files, on_demand queue).

`auth.php` y `rtmp.php` conservan sus shutdown handlers (patrón BruteforceGuard).

#### Paso 13.3 — Extracción del middleware de auth de streaming ✅

**Resultado:** `streaming/Auth/StreamAuthMiddleware.php` — `sendStreamHeaders()` + `decryptToken()`. Extrae ~35 líneas duplicadas de cada archivo de streaming.

**Archivos modificados:**
- `www/stream/live.php` — ShutdownHandler + StreamAuthMiddleware
- `www/stream/vod.php` — igual
- `www/stream/timeshift.php` — igual

#### Paso 13.4 — Ministra JS → modules/ministra/assets/ ⏳

**Estado:** APLAZADO. `www/c` — symlink a `ministra/` (creado por `RootSignalsCronJob::enable_ministra`). Mover los JS rompe el symlink; requiere alias de nginx + cambio del signal handler. Beneficio bajo, coste alto.

---

## 7. Fase 14: CSS/JS partials — división de footer.php

> **Objetivo:** Extraer ~800 líneas de JS inline específico de página de `admin/footer.php` a archivos separados. footer.php queda < 100 líneas (solo layout).

#### Paso 14.1 — Auditoría del JS inline en footer.php

1. Listar todos los bloques `<script>` de `admin/footer.php` asociándolos a `$_TITLE` / página
2. Contar líneas por bloque
3. Agrupar: JS común (todas las páginas) vs JS específico de página

#### Paso 14.2 — Extracción del JS específico de página

**Patrón:**
```
// Antes en footer.php:
<?php if ($_TITLE == 'streams'): ?>
<script>
    // 200 líneas de JS para la tabla de streams
</script>
<?php endif; ?>

// Ahora:
// public/assets/js/pages/streams.js — archivo aparte
// footer.php: <script src="assets/js/pages/<?= $_TITLE ?>.js"></script>
```

**Acciones:**
1. Para cada bloque de página → crear `public/assets/js/pages/{page}.js`
2. En footer.php: `<script src>` dinámico según `$_TITLE`
3. JS común (DataTables init, modals, AJAX helpers) → `public/assets/js/common.js`

#### Paso 14.3 — Minificación (opcional)

Si se quiere optimización de producción:
1. Target `make js-minify` en el Makefile
2. terser o closure-compiler para `assets/js/pages/*.js`
3. Versionado: `?v={hash}` en `<script src>`

---

## 8. Fase 15: Eliminación de includes/admin.php — el último legacy bootstrap

> **Objetivo:** `includes/admin.php` (el último archivo legacy) eliminado. Todo el bootstrap vía `XC_Bootstrap::boot()`.

#### Paso 15.1 — Auditoría de dependencias de includes/admin.php

1. `grep -rn "include.*admin\.php\|require.*admin\.php" src/` — todas las inclusiones
2. Para cada inclusión: qué se espera exactamente (variables globales, funciones, sesión)?
3. Elaborar lista: qué vive aún SOLO en `includes/admin.php` y no tiene reemplazo

#### Paso 15.2 — Traslado de las funciones restantes

**Restos esperados:**
- funciones utilitarias globales → `includes/admin_functions.php` o servicios de dominio
- inicialización de `$language` → `core/I18n/Translator.php`
- bootstrap de sesión → `core/Auth/SessionManager.php`
- carga de `$rPermissions` → `domain/Auth/AuthorizationService.php`

#### Paso 15.3 — Conmutación del bootstrap

1. `BaseAdminController::before()`: en lugar de dual bootstrap → solo `XC_Bootstrap::boot(CONTEXT_ADMIN)`
2. `BaseResellerController::before()`: igual
3. Feature flag: `use_legacy_bootstrap = false`
4. Probar las 134 páginas

#### Paso 15.4 — Eliminación de includes/admin.php

1. `rm src/includes/admin.php`
2. Eliminar `require 'admin.php'` de todos los archivos
3. Actualizar `bootstrap.php` — quitar la rama de dual bootstrap
4. `php -l` + smoke test completo

---

## 9. Orden de ejecución y matriz de riesgos

### Orden de ejecución de las fases 10–15

```
Fase 10 ─── Eliminación de admin/ legacy entry-points        ✅ COMPLETADA
    │
    ▼
Fase 11 ─── Unificación de API (controllers ✅ / deletion ⏳)  ✅ 11.1–11.4
    │
    ▼
Fase 12 ─── CLI runner único + eliminación de includes/cli/   ✅ COMPLETADA
    │
    ▼
Fase 13 ─── Micro-optimizaciones de streaming                ✅ 13.1–13.3
    │
    ▼
Fase 14 ─── CSS/JS partials (división de footer.php)       ⏳ NO INICIADA
    │
    ▼
Fase 15 ─── Eliminación de includes/admin.php                  ⏳ NO INICIADA
```

### Matriz de riesgos

| Fase | Riesgo | Justificación |
|------|------|-------------|
| 10 | ✅ Completada | Los 6 pasos ejecutados |
| 11 | ✅ Controllers / ⏳ Deletion | 11.1–11.4 — controladores + proxies. 11.5–11.6 — extracción REST API + eliminación aplazadas |
| 12 | ✅ Completada | console.php, 26 Commands, 25 CronJobs, includes/cli/ eliminada, guards LB |
| 13 | ✅ 13.1–13.3 / ⏳ 13.4 | ShutdownHandler + StreamAuthMiddleware. Ministra aplazada |
| 14 | 🟢 Bajo | Solo refactor de frontend, no rompe el backend |
| 15 | 🔴 Alto | Eliminación del último legacy bootstrap, sin fallback |

### Reparto por releases (fases 0–9)

| Release | Contiene | Riesgo |
|-------|----------|------|
| **v1.8** | Fases 0–2 (extracción core/, dedup) | 🟢 Bajo — métodos proxy, retrocompatibilidad |
| **v1.9** | Fases 3–4 (extracción domain/ + streaming/) | 🟡 Medio — más movimientos, los proxy cubren |
| **v2.0** | Fases 5–6 (modules + controllers) | 🟡 Medio — nuevo enrutado, dual bootstrap |
| **v2.1** | Fases 7–8 (cleanup, eliminación de legacy) | 🔴 Alto — eliminación de god-objects, sin fallback |

### Reparto por releases (fases 10–15)

| Release | Contiene | Riesgo |
|-------|----------|------|
| **v2.2** | Fase 10 (eliminación de admin/) | ✅ Completada |
| **v2.3** | Fase 11 (API controllers) + Fase 12 (CLI runner) + Fase 13 (streaming) | ✅ Completada |
| **v2.4** | Fase 11 (API deletion) + Fase 14 (CSS/JS partials) | 🟢 + 🟢 |
| **v3.0** | Fase 15 (eliminación del legacy bootstrap) | 🔴 Hito final |

---

## 10. Estrategia de migración por riesgos

### 10.1. Principio: cada paso es reversible

Cada cambio sigue el patrón **extract → delegate → verify → replace**:

```
1. Extract: crear la nueva clase
2. Delegate: el código antiguo llama al nuevo vía proxy
3. Verify: el sistema funciona como antes + el código nuevo funciona
4. Replace: actualizar el código llamante a llamadas directas (paso aparte)
```

Si el paso 3 falla — el rollback = eliminar la clase nueva + quitar el proxy. El sistema vuelve al estado anterior.

### 10.2. Estrategia de regresión

| Nivel | Qué se comprueba | Cómo se comprueba | Cuándo |
|---------|----------------|-----------------|-------|
| **Syntax** | Los archivos PHP compilan | `php -l` en cada archivo modificado | Tras cada commit |
| **Smoke** | El sistema arranca | `make new && make lb` + comprobación HTTP 200 | Tras cada fase |
| **Functional** | Los escenarios principales funcionan | Checklist manual (crear stream, arrancar, parar) | Tras cada fase |
| **Integration** | El build LB funciona | Deploy a un servidor LB de prueba + test de streaming | Tras las fases 1–4 |
| **Backward compat** | La API no está rota | Verificación de respuestas de API (formato JSON, códigos de error) | Tras la fase 6 |

### 10.3. Dual bootstrap en la fase de transición

**Estado actual (fases 1–6):** el dual bootstrap funciona en paralelo.

```
bootstrap.php (nuevo)          includes/admin.php (legacy antiguo)
      │                                │
      ├── autoload.php                 ├── require Database.php
      ├── ServiceContainer             ├── CoreUtilities::init()
      ├── ConfigLoader                 ├── API/ResellerAPI init
      └── nuevas clases core/          └── 50 define() + global $db
```

**Reglas del dual bootstrap:**
1. `bootstrap.php` se carga PRIMERO en cada punto de entrada
2. `includes/admin.php` se carga DESPUÉS — para el código legacy aún no migrado
3. Las clases nuevas (`core/`, `domain/`) se inicializan vía `ServiceContainer`
4. El código legacy (`CoreUtilities`, `admin_api.php`) sigue funcionando vía métodos proxy
5. Tras la migración completa (Fase 15) — `includes/admin.php` se elimina

### 10.4. Retrocompatibilidad de la API

**Regla:** la API externa (`player.api`, `xmltv.php`, API inter-servidor) no cambia el formato de respuestas hasta la Fase 11.

```
Fases 1–10: Refactorización interna, la API externa no cambia
Fase 11:   API v2 (opcional) con nuevo enrutado
           La API v1 sigue funcionando vía compatibility layer
```

### 10.5. Plan de rollback

```
Si un release rompe production:
1. git revert del último merge en main
2. Reconstruir: make new && make lb
3. Desplegar el build anterior
4. Post-mortem: qué se rompió, por qué no lo detectó el smoke test
```

Para la Fase 15 (eliminación del legacy) — **feature flag:**
```php
// config.ini
[migration]
use_legacy_bootstrap = false    ; true = rollback a admin.php
use_legacy_api = false          ; true = rollback al switch de admin_api.php
```
