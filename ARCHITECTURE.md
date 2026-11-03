# UyPanel — Plan arquitectónico de reorganización de `/src`

## Contenido

0. [Objetivos estratégicos](#0-objetivos-estratégicos)
1. [Diagnóstico del estado actual](#1-diagnóstico-del-estado-actual)
2. [Estilo arquitectónico y principios](#2-estilo-arquitectónico-y-principios)
3. [Estructura objetivo de `/src`](#3-estructura-objetivo-de-src)
4. [Descripción de componentes](#4-descripción-de-componentes)
5. [Mapa de migración: de dónde → a dónde](#5-mapa-de-migración-de-dónde--a-dónde)
6. [Sistema de módulos](#6-sistema-de-módulos)
7. [Fronteras del núcleo y los módulos](#7-fronteras-del-núcleo-y-los-módulos)
8. [Variantes de build: MAIN vs LoadBalancer](#8-variantes-de-build-main-vs-loadbalancer)
9. [Transacciones y rendimiento](#9-transacciones-y-rendimiento)
10. [Reglas para contribuyentes](#10-reglas-para-contribuyentes)

> El **plan de migración** (fases 0–15, estrategia de riesgos, orden de ejecución) está en [MIGRATION.md](MIGRATION.md).

---

## 0. Objetivos estratégicos

### 0.1. Por qué todo esto

| # | Objetivo | Prioridad | Métrica de éxito |
|---|------|-----------|----------------|
| 1 | **Accesibilidad para contribuyentes** | 🔴 Crítico | Un desarrollador PHP de nivel medio hace su primer PR en 2 horas sin conocer DDD |
| 2 | **Mantenibilidad** | 🔴 Crítico | Un cambio típico (añadir stream, cambiar límites) < 1 hora sin riesgo de efectos secundarios |
| 3 | **Aislamiento de fallos** | 🔴 Crítico | Un bug en el panel admin no rompe el streaming. Un bug en un módulo no rompe el núcleo |
| 4 | **Multi-node** | 🟡 Importante | El servidor LB se construye a partir de un subconjunto del código. El camino de streaming no carga la lógica admin |
| 5 | **Comercio open-core** | 🟡 Importante | Los módulos comerciales se conectan sin modificar el núcleo. Eliminar un módulo no rompe el sistema |
| 6 | **Testeabilidad** | 🟢 De soporte | Cualquier servicio puede testearse sustituyendo dependencias por mocks |

### 0.2. Qué NO es esto

- **No es una reescritura desde cero.** Refactorización iterativa. Cada paso es retrocompatible y reversible.
- **No es DDD.** Sin Event Sourcing, CQRS, Aggregate Root, Domain Events. Patrón simple: Controller → Service → Repository.
- **No es un proyecto académico.** La arquitectura está optimizada para comprensibilidad y previsibilidad, no para pureza teórica.

### 0.3. Principio de toma de decisiones

Cada decisión arquitectónica pasa tres filtros:

1. **¿Un contribuyente lo entiende en 5 minutos?** → si no, simplificar
2. **¿No rompe el hot path de streaming?** → si lo rompe, rechazar
3. **¿Se puede aislar en un módulo?** → si no, justificarlo

Si una decisión mejora la "belleza del código" pero sube la barrera de entrada — **rechazar**.

---

## 1. Diagnóstico del estado actual

### Escala: 382 archivos PHP, ~199 000 líneas

### Problemas críticos

| # | Problema | Dónde se manifiesta | Impacto |
|---|----------|-----------------|---------|
| 1 | **God-objects** | `CoreUtilities` (4847 líns.), `admin_api.php` (6981 líns.), `admin.php` (4448 líns.) | Imposible modificar una subsistema sin riesgo de romper otra |
| 2 | **Bootstrap duplicado** | `www/constants.php` vs `www/stream/init.php` — los mismos ~70 `define()`, funciones de error, flood-check | Cada cambio hay que hacerlo en dos sitios |
| 3 | **Duplicación fork** | `CoreUtilities` vs `StreamingUtilities` — propiedades idénticas, `init()`, `cleanGlobals()` | Los bugs se corrigen en una clase y quedan en la otra |
| 4 | **Variables globales como bus de datos** | `global $db`, `$rSettings`, `$rServers`, `$rUserInfo` en cada archivo | Imposible testear, imposible aislar |
| 5 | **SQL en la capa de presentación** | Cada página admin hace `$db->query()` directo en el HTML | Lógica de negocio inseparable de la presentación |
| 6 | **Un include = efectos secundarios** | `require admin.php` arranca sesión, crea BD, init de 3 clases, define 50 constantes | No hay forma de conectar una parte del sistema sin toda |
| 7 | **Admin = copia-pega de Reseller** | `reseller/` — clon simplificado de `admin/` con el mismo header/footer/session | Los cambios en uno no llegan al otro |
| 8 | **Goto-labels** | `includes/cli/monitor.php` contiene `goto label235`, `label592` | Restos de ofuscación, flujo de control ilegible |
| 9 | **Datos inline** | Arrays de países/tipos MAC/permisos de 150+ líneas directamente en `admin.php` | Datos entrelazados con la lógica de inicialización |
| 10 | **God-cron** | `crons/servers.php` — monitoreo, reinicio de daemons, estadísticas, limpieza | Tareas de frecuencia y naturaleza distintas en un mismo archivo |

### Grafo de dependencias (actual)

```
admin/*.php ──────┐
reseller/*.php ───┤──→ includes/admin.php ──→ CoreUtilities (4847)
includes/api/ ────┘         │                     ├── Database
                            │                     ├── Redis
                            ├──→ admin_api.php (6981)
                            ├──→ reseller_api.php (1204)
                            └──→ constants.php (~70 define())

www/stream/*.php ──→ www/stream/init.php ──→ StreamingUtilities (1992)
                          │                     ├── Database (la misma)
                          │                     └── Redis (el mismo)
                          └── constants (DUPLICADO)
```

**Punto único de fallo: `includes/admin.php`** — cada contexto de ejecución (admin, reseller, crons, API) pasa por este único archivo de 4448 líneas.

---

## 2. Estilo arquitectónico y principios

### 2.0. Formalización del estilo arquitectónico

**Es: un monolito estructurado con organización predecible por contextos.**

No DDD. No Hexagonal. No Clean Architecture. No microservicios.
Un monolito PHP simple, descompuesto por contextos con un mínimo de abstracciones.

#### Patrón: Controller → Service → Repository

Cada contexto (Streams, VOD, Lines, Users...) está organizado internamente igual:

```
domain/Stream/
  ├── StreamService.php       # Lógica de negocio + orquestación
  ├── StreamRepository.php    # Consultas SQL (SELECT/INSERT/UPDATE/DELETE)
  └── StreamProcess.php       # Operaciones especializadas (ffmpeg, kill)

public/Controllers/Admin/
  └── StreamController.php    # Recibir petición → llamar Service → dar respuesta
```

Y eso es todo. Tres archivos por contexto. Un contribuyente ve `/Stream/` — y sabe dónde tocar.

#### Reglas de dependencias (simple)

```
Controller  →  Service  →  Repository  →  Database
                  ↓
          Infrastructure (nginx, redis, ffmpeg)
```

| Capa | Puede depender de | NO PUEDE depender de |
|------|-------------------|--------------------|
| `public/` | `domain/` (Service + Repository), `core/` | `streaming/`, `modules/` directamente |
| `domain/` | `core/` (Database, Cache, Events) | `public/`, `streaming/`, `modules/`, `infrastructure/` |
| `core/` | Solo otros subdirectorios `core/` | Todo lo demás |
| `streaming/` | `core/` (subset), `domain/` (consultas read-only) | `public/`, `modules/` |
| `modules/` | `domain/` (Service, Repository), `core/` | Otros módulos (sin dependencia explícita), `public/`, `streaming/` |

### 2.1. Inversión de dependencias

El código de nivel superior (UI, CLI, endpoints HTTP) depende de abstracciones del núcleo, no de implementaciones concretas. El núcleo no sabe que los módulos existen.

### 2.2. Punto único de bootstrap — múltiples contextos

En lugar de `init.php` / `constants.php` / `stream/init.php` duplicados — un solo bootstrap con un conjunto configurable de servicios a cargar.

### 2.3. Strict Constructor Injection — prohibido el Service Locator

**Regla:** `ServiceContainer` se usa **SOLO** en la fase de bootstrap (composition root).
Tras el bootstrap, todas las dependencias se pasan **por constructor**. Ningún servicio llama a `$container->get()` dentro de sus métodos.

```php
// ✅ CORRECTO — constructor injection:
class StreamService {
    public function __construct(
        private StreamRepository $repository,
        private EventDispatcher $events,
        private FileLogger $logger
    ) {}
    
    public function create(array $data): int {
        // Usa $this->repository, $this->events, $this->logger
        // NO llama a ServiceContainer
    }
}

// ✅ CORRECTO — composition root (bootstrap.php):
$container->register('stream.service', function($c) {
    return new StreamService(
        $c->get('stream.repository'),
        $c->get('event.dispatcher'),
        $c->get('logger.file')
    );
});

// ❌ PROHIBIDO — Service Locator dentro del servicio:
class StreamService {
    public function create(array $data): int {
        $db = ServiceContainer::getInstance()->get('db');  // ← ANTIPATRÓN
        $logger = ServiceContainer::getInstance()->get('logger');  // ← ANTIPATRÓN
    }
}
```

**Excepciones (temporales, fase de migración):**
- El código legacy en `includes/` puede acceder al contenedor directamente vía métodos proxy
- Cada acceso así se marca con `// @legacy-container — quitar en Fase 8`
- En la Fase 8 todos los accesos legacy al contenedor se reemplazan por constructor injection

### 2.4. Consolidación de clases pequeñas — prohibido el "una clase = un archivo" a ciegas

**Regla:** No crear un archivo/clase aparte si tiene < 150 líneas **y** < 5 métodos públicos. Las clases pequeñas viven en un mismo archivo con la clase afín.

**Por qué:** Evitar la explosión de archivos con clases de 1-2 funciones. 44 archivos de 40 líneas es peor que un god de 4000, aunque no mucho mejor para navegar.

**Reglas concretas:**

| Situación | Qué hacer | Ejemplo |
|----------|-----------|--------|
| Repository < 5 métodos | Unir Service + Repository en un archivo | `EpgService.php` contiene lógica de negocio y SQL |
| Contexto = 1 Service con 1-2 métodos | Fusionar en el contexto afín más cercano | `TicketService::submit()` → `UserService::submitTicket()` |
| Un "Validator" aparte con 1 método | Hacerlo método private del Service | `HMACValidator::validate()` → `HMACService::validate()` |
| Un "Sync" aparte con 1 método | Fusionar en el Service del mismo contexto | `DeviceSync::sync()` → `MagService::syncLineDevices()` |
| Service + Repository > 300 líneas en total | Separar en archivos distintos | `StreamService` (700 líns.) + `StreamRepository` (400 líns.) = separados |

**Cuándo SÍ se puede crear un archivo aparte:**
- Clase > 150 líneas
- Clase con ≥ 5 métodos públicos
- Clase usada desde 3+ contextos distintos (p. ej. `ConnectionTracker`)
- Interfaz para DI (`CacheInterface`, `LoggerInterface`)

```php
// ✅ CORRECTO — Service+Repository pequeños en un archivo:
// domain/Epg/EpgService.php
class EpgRepository {
    public function __construct(private Database $db) {}
    public function findById(int $id): ?array { ... }
    public function getStreamEpg(int $streamId): array { ... }
}

class EpgService {
    public function __construct(private EpgRepository $repo) {}
    public function process(array $data): int { ... }
    public function getChannelEpg(int $channelId): array { ... }
}

// ❌ INCORRECTO — dos archivos de 40 líneas:
// domain/Epg/EpgRepository.php  (40 líneas, 3 métodos)
// domain/Epg/EpgService.php     (50 líneas, 2 métodos)
```

### 2.5. Fronteras vía interfaces, no vía `global`

Cada servicio recibe sus dependencias por constructor. Ningún componente usa `global`.

### 2.6. Módulo = directorio aislado

Un módulo es un directorio con un contrato conocido. Se puede eliminar y el sistema sigue funcionando (degradando funcionalidad, sin caerse).

### 2.7. Open-core sin restricciones de licencia en el núcleo

El núcleo (`core/`) es completamente libre. Los módulos (`modules/`) pueden ser open-source o comerciales. El núcleo no contiene comprobaciones de licencia, cifrado ni restricciones ocultas. El licenciamiento es tarea de un módulo opcional de extensiones aparte.

---

## 3. Estructura objetivo de `/src`

```
src/
├── bootstrap.php                    # Bootstrap único: require_once, DI container, config
├── constants.php                    # Todas las constantes path/version/status (un archivo)
│
├── core/                            # ═══ NÚCLEO (estable, libre, mínimo) ═══
│   ├── Config/
│   │   ├── ConfigLoader.php         # .ini → array, caché, override por env
│   │   └── PathResolver.php         # Todas las rutas del sistema (reemplaza 70 define())
│   │
│   ├── Database/
│   │   ├── Database.php              # Envoltura PDO base (movida desde includes/)
│   │   ├── DatabaseHandler.php      # Gestor de BD — envoltura extendida con reconnect, bulk ops
│   │   ├── QueryBuilder.php         # Constructor de consultas en lugar de SQL crudo en la UI
│   │   └── Migration.php            # Migraciones (extraído de `status`)
│   │
│   ├── Cache/
│   │   ├── CacheInterface.php       # Contrato para cualquier driver de caché
│   │   ├── FileCache.php            # Actual file cache con igbinary
│   │   └── RedisCache.php           # Envoltura Redis
│   │
│   ├── Auth/
│   │   ├── SessionManager.php       # Gestor de sesiones único (admin + reseller)
│   │   ├── Authenticator.php        # Login/password/api_key
│   │   └── Authorization.php        # Comprobación de permisos ($rPermissions → RBAC)
│   │
│   ├── Http/
│   │   ├── Request.php              # Envoltura de $_GET/$_POST (reemplaza cleanGlobals)
│   │   ├── Response.php             # Respuesta JSON/HTML
│   │   ├── Router.php               # Enrutado en lugar de switch($rAction)
│   │   └── Middleware/
│   │       ├── FloodProtection.php  # Rate limiting (extraído de constants.php)
│   │       ├── IpWhitelist.php      # Filtrado por IP
│   │       └── CorsMiddleware.php
│   │
│   ├── Process/
│   │   ├── ProcessManager.php       # Gestión de procesos (kill, ps, isRunning)
│   │   ├── DaemonRunner.php         # Arranque de daemons PHP con archivos PID
│   │   └── CronLock.php             # Locks de archivo para crons (checkCron)
│   │
│   ├── Logging/
│   │   ├── LoggerInterface.php       # Contrato
│   │   ├── FileLogger.php           # Logging a archivo
│   │   └── DatabaseLogger.php       # panel_logs / login_logs / activity
│   │
│   ├── Events/
│   │   ├── EventDispatcher.php      # Event bus simple
│   │   └── EventInterface.php       # Contrato de evento
│   │
│   ├── Container/
│   │   └── ServiceContainer.php     # Contenedor DI mínimo
│   │
│   └── Util/
│       ├── GeoIP.php                # Extraído de CoreUtilities
│       ├── NetworkUtils.php         # Operaciones IP, CIDR, subnet matching
│       ├── TimeUtils.php            # secondsToTime(), helper de timezone
│       ├── Encryption.php           # AES, generación de tokens
│       └── ImageUtils.php           # Resize, thumbnail, upload
│
├── domain/                          # ═══ LÓGICA DE NEGOCIO (servicios y repositorios) ═══
│   ├── Stream/
│   │   ├── StreamService.php        # Lógica de negocio + orquestación
│   │   ├── StreamRepository.php    # Consultas SQL (de admin_api.php)
│   │   ├── ChannelService.php       # Canales: create, massEdit, order
│   │   ├── CategoryService.php      # Categorías + CategoryRepository (< 150 líns. = un archivo)
│   │   ├── StreamProcess.php       # FFmpeg, kill, restart
│   │   ├── StreamMonitor.php        # Monitoreo del stream (de cli/monitor.php)
│   │   ├── ConnectionTracker.php    # Redis: conexiones, heartbeat
│   │   ├── StreamSorter.php         # Ordenamientos y formateo
│   │   ├── PlaylistGenerator.php    # Generación de playlists M3U/EPG
│   │   ├── CronGenerator.php        # Generación de crons
│   │   └── M3UParser.php            # Parsing de M3U
│   │
│   ├── Vod/
│   │   ├── MovieService.php         # + MovieRepository (< 300 líns. en total = un archivo)
│   │   ├── SeriesService.php        # + SeriesRepository (< 300 líns. en total = un archivo)
│   │   └── EpisodeService.php       # process, massEdit, massDelete
│   │
│   ├── Line/
│   │   ├── LineService.php          # + LineRepository (separados — > 300 líns. en total)
│   │   ├── LineRepository.php
│   │   └── PackageService.php       # + PackageRepository (un archivo, < 150 líns.)
│   │
│   ├── Device/
│   │   ├── MagService.php           # + DeviceSync::syncLineDevices (fusionado)
│   │   └── EnigmaService.php
│   │
│   ├── User/
│   │   ├── UserService.php          # + ProfileService::editAdminProfile (fusionado)
│   │   │                            # + TicketService::submit (fusionado)
│   │   ├── UserRepository.php       # getUserInfo, getUser, getRegisteredUsers...
│   │   └── GroupService.php         # + GroupRepository (un archivo, < 150 líns.)
│   │
│   ├── Server/
│   │   ├── ServerRepository.php
│   │   ├── ServerService.php        # Monitoreo, health-check
│   │   └── SettingsService.php      # edit, editBackup, editCacheCron (fusionado desde domain/Settings/)
│   │
│   ├── Bouquet/
│   │   └── BouquetService.php       # + BouquetRepository + BouquetMapper (un archivo, < 300 líns.)
│   │
│   ├── Epg/
│   │   ├── EPG.php                  # Parser XML de EPG (XmlStringStreamer), solo MAIN
│   │   └── EpgService.php           # + EpgRepository (un archivo, < 300 líns.)
│   │
│   ├── Auth/
│   │   ├── AuthService.php          # CodeService + HMACService + HMACValidator (unificados)
│   │   └── AuthRepository.php       # CodeRepository + HMACRepository (unificados)
│   │
│   └── Security/
│       └── BlocklistService.php     # + BlocklistRepository (un archivo, < 300 líns.)
│
├── streaming/                       # ═══ MOTOR DE STREAMING (hot path) ═══
│   ├── StreamingBootstrap.php       # Init ligero para el contexto de streaming
│   ├── Auth/
│   │   ├── TokenAuth.php            # Parsing HMAC/token (de auth.php)
│   │   ├── StreamAuth.php           # Verificación de acceso al stream
│   │   └── DeviceLock.php           # Vinculación a dispositivo
│   │
│   ├── Delivery/
│   │   ├── LiveDelivery.php         # Distribución live (de live.php)
│   │   ├── VodDelivery.php          # Distribución VOD (de stream/vod.php)
│   │   ├── TimeshiftDelivery.php    # Timeshift
│   │   └── SegmentReader.php        # Lectura de segmentos TS
│   │
│   ├── Balancer/
│   │   └── LoadBalancer.php         # Distribución + RedirectStrategy (un archivo, < 150 líns.)
│   │
│   ├── Protection/
│   │   ├── RestreamDetector.php     # Protección antipiratería
│   │   ├── ConnectionLimiter.php    # Límite de conexiones
│   │   └── GeoBlock.php            # Bloqueo por país/ISP/IP/UA
│   │
│   ├── Codec/
│   │   ├── FFmpegCommand.php        # Construcción de comandos FFmpeg (de CoreUtilities)
│   │   ├── TranscodeProfile.php     # Perfiles de transcodificación
│   │   └── TsParser.php            # Parser MPEG-TS (el actual ts.php)
│   │
│   └── Health/
│       ├── DivergenceDetector.php   # Monitoreo de calidad
│       └── BitrateTracker.php       # Seguimiento de bitrate/FPS
│
├── public/                          # ═══ PUNTOS DE ENTRADA — HTTP (UI, API) ═══
│   ├── index.php                    # Punto de entrada único (front controller)
│   │
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── BaseAdminController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── StreamController.php
│   │   │   ├── LineController.php
│   │   │   ├── VodController.php
│   │   │   ├── ServerController.php
│   │   │   ├── SettingsController.php
│   │   │   ├── UserController.php
│   │   │   ├── BouquetController.php
│   │   │   ├── EpgController.php
│   │   │   └── ... (111 controladores, uno por página)
│   │   │
│   │   ├── Reseller/
│   │   │   ├── BaseResellerController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── LineController.php
│   │   │   └── ... (22 controladores)
│   │   │
│   │   └── Api/                         # (planificado)
│   │       ├── AdminApiController.php
│   │       ├── ResellerApiController.php
│   │       ├── PlayerApiController.php   # Player API pública
│   │       └── InternalApiController.php  # API inter-servidor (actual www/api.php)
│   │
│   ├── Views/
│   │   ├── layouts/
│   │   │   ├── admin.php            # Plantilla header + footer para admin
│   │   │   └── footer.php           # Footer unificado
│   │   │
│   │   ├── admin/
│   │   │   ├── dashboard.php
│   │   │   ├── streams/
│   │   │   │   ├── list.php
│   │   │   │   ├── edit.php
│   │   │   │   └── view.php
│   │   │   ├── lines/
│   │   │   ├── vod/
│   │   │   ├── servers/
│   │   │   └── settings/
│   │   │
│   │   ├── reseller/
│   │   │   ├── dashboard.php
│   │   │   └── ...
│   │   │
│   │   └── partials/
│   │       ├── modals.php
│   │       ├── topbar.php
│   │       └── table.php
│   │
│   └── routes/
│       ├── admin.php
│       ├── reseller.php
│       └── api.php
│
├── player/                          # Web-player integrado
│   └── ... (PHP + JS + CSS)
│
├── cli/                             # ═══ (PLANIFICADO) PUNTOS DE ENTRADA CLI ═══
│   ├── Commands/
│   │   ├── StartupCommand.php       # cli/startup.php
│   │   ├── WatchdogCommand.php      # cli/watchdog.php
│   │   ├── MonitorCommand.php       # cli/monitor.php
│   │   ├── CacheHandlerCommand.php  # cli/cache_handler.php
│   │   ├── QueueCommand.php         # cli/queue.php
│   │   ├── SignalsCommand.php       # cli/signals.php
│   │   ├── ScannerCommand.php       # cli/scanner.php
│   │   ├── MigrateCommand.php       # De status
│   │   └── ToolsCommand.php         # De tools (rescue, access, ports, etc.)
│   └── CronJobs/
│       ├── StreamsCron.php
│       ├── ServersCron.php
│       ├── CacheCron.php
│       ├── EpgCron.php
│       ├── CleanupCron.php
│       ├── BackupsCron.php
│       ├── StatsCron.php
│       ├── LogsCron.php            # lines_logs + streams_logs
│       ├── VodCron.php
│       └── TmdbCron.php
│
├── modules/                         # ═══ MÓDULOS OPCIONALES ═══
│   ├── ministra/                    # Middleware Ministra/Stalker (actual ministra/)
│   │   ├── module.json              # Manifest: name, version, dependencies
│   │   ├── MinistraModule.php       # Punto de entrada del módulo (implements ModuleInterface)
│   │   ├── PortalHandler.php        # Despachador type+action (15 handlers)
│   │   ├── PortalHelpers.php        # Helpers del portal (19 métodos estáticos)
│   │   └── assets/
│   │
│   ├── plex/                        # Integración Plex (actual settings_plex, plex_add)
│   │   ├── module.json
│   │   ├── PlexModule.php
│   │   ├── PlexService.php
│   │   └── config/
│   │
│   ├── tmdb/                        # Integración TMDB
│   │   ├── module.json
│   │   ├── TmdbModule.php
│   │   ├── TmdbService.php
│   │   ├── TmdbCron.php
│   │   ├── TmdbPopularCron.php
│   │   └── lib.php                  # proxy → includes/libs/tmdb.php
│   │
│   ├── watch/                       # Watch/Recording (actuales watch, record)
│   │   ├── module.json
│   │   ├── WatchModule.php
│   │   ├── WatchService.php
│   │   ├── RecordingService.php
│   │   ├── WatchController.php
│   │   ├── WatchCron.php
│   │   ├── WatchItem.php
│   │   └── views/
│   │       ├── watch.php
│   │       ├── watch_scripts.php
│   │       ├── watch_add.php
│   │       ├── watch_add_scripts.php
│   │       ├── settings_watch.php
│   │       ├── settings_watch_scripts.php
│   │       ├── watch_output.php
│   │       ├── watch_output_scripts.php
│   │       ├── record.php
│   │       └── record_scripts.php
│   │
│   ├── fingerprint/                 # Watermarking por fingerprint
│   │   ├── module.json
│   │   └── FingerprintModule.php
│   │
│   ├── theft-detection/             # Detección antirrobo/restream
│   │   ├── module.json
│   │   └── TheftDetectionModule.php
│   │
│   └── magscan/                     # Escaneo de dispositivos MAG
│       ├── module.json
│       └── MagscanModule.php
│
├── infrastructure/                  # ═══ INFRAESTRUCTURA DEL SISTEMA ═══
│   ├── nginx/
│   │   ├── NginxConfigGenerator.php # Generación de nginx.conf (de CoreUtilities)
│   │   ├── templates/
│   │   │   ├── main.conf.tpl
│   │   │   ├── rtmp.conf.tpl
│   │   │   └── vhost.conf.tpl
│   │   └── NginxReloader.php
│   │
│   ├── redis/
│   │   └── RedisManager.php         # Conexión, pipeline, pub/sub
│   │
│   ├── service/
│   │   ├── ServiceManager.sh        # El actual archivo `service` (bash)
│   │   └── daemons.sh               # Lista de daemons
│   │
│   ├── install/
│   │   ├── database.sql             # Esquema inicial
│   │   └── proxy.tar.gz
│   │
│   └── bin/                         # Binarios externos (FFmpeg, certbot, yt-dlp, etc.)
│       ├── ffmpeg_bin/
│       ├── certbot/
│       ├── maxmind/
│       ├── guess
│       ├── yt-dlp
│       └── network.py
│
├── data/                            # ═══ DATOS DE EJECUCIÓN ═══
│   ├── cache/                       # File cache (igbinary)
│   ├── logs/                        # Logs de la aplicación
│   ├── tmp/                         # Archivos temporales (actual tmp/)
│   ├── content/                     # Contenido multimedia (actual content/)
│   │   ├── archive/
│   │   ├── epg/
│   │   ├── playlists/
│   │   ├── streams/
│   │   ├── video/
│   │   └── vod/
│   ├── backups/                     # Copias de seguridad
│   └── signals/                     # Archivos de señales (.gitkeep)
│
├── config/                          # ═══ CONFIGURACIÓN ═══
│   ├── config.ini                   # Config principal (DB, Redis, rutas)
│   ├── modules.php                  # Lista de módulos activados
│   ├── routes.php                   # Tabla de rutas
│   ├── plex/
│   └── rclone.conf
│
└── resources/                       # ═══ RECURSOS ═══
    ├── langs/                       # Traducciones (actuales archivos .ini)
    │   ├── en.ini
    │   ├── ru.ini
    │   ├── de.ini
    │   ├── es.ini
    │   ├── fr.ini
    │   ├── bg.ini
    │   └── pt.ini
    ├── data/
    │   ├── countries.php            # Arrays de países (extraídos de admin.php)
    │   ├── timezones.php
    │   ├── mac_types.php
    │   └── error_codes.php          # $rErrorCodes (extraídos de constants.php)
    └── libs/                        # Librerías PHP de terceros
        ├── MobileDetect.php
        ├── XmlStringStreamer.php
        ├── m3u/
        └── Dropbox.php
```

---

## 4. Descripción de componentes

### 4.1. `core/` — Núcleo del sistema

**Responsabilidad:** Servicios de infraestructura que necesita cualquier contexto de ejecución. No contiene lógica de negocio.

| Subdirectorio | Qué aporta | Qué reemplaza |
|------------|----------|--------------|
| `Config/` | Carga de configuración, resolución de rutas | 70 `define()` de `constants.php` y `stream/init.php` |
| `Database/` | Envoltura PDO (Database + DatabaseHandler), query builder, migraciones | `Database.php` + consultas SQL de `status` |
| `Cache/` | Caché unificada con dos drivers | `igbinary_serialize` dispersos + Redis ad-hoc |
| `Auth/` | Autorización única para admin y reseller | `session.php` × 2, `API::processLogin()`, `ResellerAPI::processLogin()` |
| `Http/` | Abstracción de peticiones, enrutado, middleware | `cleanGlobals()` × 2, `switch($rAction)`, flood-check en constants.php |
| `Process/` | Gestión de PID, daemons, locks | `shell_exec('ps aux')`, `posix_kill()`, `checkCron()` dispersos por los archivos |
| `Logging/` | Logging unificado | `CoreUtilities::saveLog()`, `StreamingUtilities::clientLog()`, `Logger::init()` |
| `Events/` | Event bus para comunicación sin dependencias rígidas | Llamadas directas entre componentes |
| `Container/` | Contenedor DI (solo composition root — ver §2.3) | `global $db`, `CoreUtilities::$rSettings` estáticos |
| `Util/` | Utilidades sin estado | Funciones dispersas por CoreUtilities, admin.php |

**Regla clave:** `core/` no sabe que existen `domain/`, `streaming/`, `modules/`, `public/`. Las dependencias apuntan solo hacia el interior del núcleo.

### 4.2. `domain/` — Lógica de negocio

**Responsabilidad:** Servicios y repositorios organizados por contextos. Cada contexto (Stream, Vod, Line, User...) es un directorio aparte.

**Patrón para cada contexto:**

```
domain/Stream/
  ├── StreamService.php       # Lógica de negocio + orquestación (validación, transacciones, side-effects)
  ├── StreamRepository.php    # Consultas SQL (SELECT/INSERT/UPDATE/DELETE)
  └── StreamProcess.php       # Operaciones de sistema específicas (ffmpeg, kill, PID)
```

**Reglas:**

1. **Service** = toda la lógica de negocio del contexto. Validación, transacciones, llamadas a Infrastructure (nginx, ffmpeg), logging. Un Service por contexto; al crecer, se puede dividir.
2. **Repository** = solo SQL. Recibe arrays, devuelve arrays. Sin lógica. Sin side-effects.
3. **No hay clases Entity.** Los datos se pasan como `array`. Esto es PHP — los arrays son más simples y claros que objetos DTO anónimos.
4. **Consolidación de clases pequeñas (§2.4).** Si el Repository tiene < 5 métodos y < 150 líneas — vive en el mismo archivo que el Service. Los contextos pequeños (Ticket, Settings) se fusionan en el contexto afín, sin crear directorio aparte.

```php
// ✅ CORRECTO — el Service hace todo:
class StreamService {
    public function __construct(
        private StreamRepository $repository,
        private ProcessManager $processManager,
        private FileLogger $logger,
        private Database $db
    ) {}
    
    public function create(array $data): int {
        // Validación
        if (empty($data['stream_source'])) {
            throw new \InvalidArgumentException('Source required');
        }
        
        // Transacción
        $this->db->beginTransaction();
        try {
            $id = $this->repository->insert($data);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        
        $this->logger->log('stream', "Created stream {$id}");
        return $id;
    }
}

// ✅ CORRECTO — Repository = solo SQL:
class StreamRepository {
    public function __construct(private Database $db) {}
    
    public function findById(int $id): ?array {
        return $this->db->row("SELECT * FROM streams WHERE id = ?", [$id]);
    }
    
    public function insert(array $data): int {
        $this->db->query("INSERT INTO streams ...", $data);
        return $this->db->lastInsertId();
    }
}
```

**De dónde sale el código:**
- `admin_api.php` (6981 líneas) → se divide en ~10 Service + ~6 Repository (§2.4 — Repositories pequeños fusionados en Services)
- `admin.php` → `getUserInfo()`, `getSeriesList()` procedimentales → al Repository
- Orquestación (validate + save + nginx + cache + log) → al Service

**Dependencias:** `domain/` depende de `core/` (Database, Cache, Events). No depende de `public/` ni de `modules/`.

### 4.3. `streaming/` — Motor de streaming

**Responsabilidad:** Todo el hot path de entrega de video. Separado de `domain/` porque:
- Es crítico para el rendimiento (no se puede cargar toda la lógica de negocio)
- Tiene su propio bootstrap ligero
- Trabaja a nivel de bytes/segmentos, no a nivel CRUD

#### Aislamiento de streaming

Streaming es un **contexto de ejecución aparte** con dependencias mínimas:

```
streaming/ depende de:
  ✅ core/ (Database, Redis, Logging, GeoIP, Encryption, NetworkUtils)
  ✅ domain/ (Repository — solo consultas SELECT: streams, servidores, bouquets)
  
  ❌ NO depende de:
     - public/ (controladores)
     - modules/ (nada)
     - domain/*Service.php (mutaciones de negocio)
```

**Regla principal:** streaming llama **solo a métodos read** del Repository. La escritura de datos (PID, stats, conexiones) — vía sus propias clases (`ConnectionTracker`, `BitrateTracker`), no vía Domain Services.

**De dónde sale el código:**
- `StreamingUtilities.php` (1992 líns.) → se distribuye por los subdirectorios
- `www/stream/auth.php` (800 líns.) → `Auth/TokenAuth.php` + `Auth/StreamAuth.php` + `Protection/`
- `www/stream/live.php` (708 líns.) → `Delivery/LiveDelivery.php`
- `includes/cli/monitor.php` (565 líns.) → `domain/Stream/StreamMonitor.php` (con refactor del goto)
- Métodos FFmpeg de CoreUtilities → `Codec/FFmpegCommand.php`
- `ts.php` → `Codec/TsParser.php`

### 4.4. `public/` — Puntos de entrada

**Responsabilidad:** Recibir la petición, llamar al Service, formar la respuesta. Sin lógica de negocio.

**HTTP:**
- Un front controller `public/index.php` + `Router`
- Los controladores llaman al **Service** de `domain/` para mutaciones y al **Repository** para lecturas
- Las plantillas (`Views/`) — HTML limpio con mínimo de inserciones PHP
- Admin y Reseller — controladores distintos, plantillas comunes

```php
// ✅ CORRECTO — el controlador llama al Service:
class StreamController {
    public function __construct(
        private StreamService $streamService,
        private StreamRepository $streamRepo
    ) {}
    
    public function create(Request $request): void {
        $id = $this->streamService->create($request->all());
        Response::json(['id' => $id]);
    }
    
    public function list(): void {
        $streams = $this->streamRepo->getAll();
        include VIEW_PATH . '/admin/streams/list.php';
    }
}

// ❌ PROHIBIDO — el controlador contiene lógica de negocio:
class StreamController {
    public function create(Request $request): void {
        // Validación, transacciones, nginx — tarea del Service
        $this->db->beginTransaction();
        $this->streamRepo->insert(...);  // ← fat controller
        $this->nginx->reload();
        $this->db->commit();
    }
}
```

**CLI:**
- Cada daemon/cron — una clase Command aparte
- Inicialización común vía `bootstrap.php` + `ServiceContainer`
- El archivo `service` (bash) → `infrastructure/service/ServiceManager.sh`

**De dónde sale el código:**
- Cada `admin/*.php` (100+ archivos) → Controller + View. Ejemplo: `admin/streams.php` → `Controllers/Admin/StreamController.php` + `Views/admin/streams/list.php`
- `admin/header.php` (675 líns.) + `admin/footer.php` (805 líns.) → `Views/layouts/admin.php` + extracción de JS a archivos `assets/`
- `admin/post.php` (1946 líns.) → los handlers de formularios se distribuyen entre los controladores
- `crons/*.php` → `cli/CronJobs/`, cada cron un archivo (**crons/ eliminado**, Fase 12.8)
- `includes/cli/*.php` → `cli/Commands/` (**includes/cli/ eliminado**, Fase 12.6)

### 4.5. `modules/` — Módulos opcionales

**Responsabilidad:** Funcionalidades adicionales que no forman parte del núcleo. Cada módulo es un directorio autosuficiente.

**Contrato del módulo:**

```php
interface ModuleInterface {
    public function getName(): string;
    public function getVersion(): string;
    public function boot(ServiceContainer $container): void;   // Registro de servicios
    public function registerRoutes(Router $router): void;      // Rutas propias
    public function registerCrons(): array;                     // Crons propios
    public function getEventSubscribers(): array;              // Suscripciones a eventos
}
```

```json
// module.json
{
    "name": "ministra",
    "version": "1.0.0",
    "description": "Ministra/Stalker Portal middleware",
    "requires_core": ">=2.0",
    "dependencies": []
}
```

**Código actual → módulos:**

| Módulo | Origen | Por qué módulo y no núcleo |
|--------|----------|-------------------------|
| `ministra/` | `src/ministra/` (2155+ líneas, decenas de JS) | Portal de terceros, no todos lo necesitan |
| `plex/` | `settings_plex.php`, `plex_add.php`, `crons/plex.php`, `config/plex/` | Integración con servicio externo |
| `tmdb/` | `includes/libs/TMDb/`, `crons/tmdb.php`, `crons/tmdb_popular.php` | API externa de metadatos |
| `watch/` | `watch.php`, `watch_add.php`, `watch_output.php`, `settings_watch.php`, `crons/watch.php` | Grabación/DVR — función avanzada |
| `fingerprint/` | `admin/fingerprint.php` | Watermarks — función enterprise |
| `theft-detection/` | `admin/theft_detection.php` | Antipiratería — función enterprise |
| `magscan/` | `admin/magscan_settings.php` | Escaneo de dispositivos |

### 4.6. `infrastructure/` — Capa de sistema

**Responsabilidad:** Interacción con el SO: nginx, redis, scripts bash, binarios externos.

**De dónde sale el código:**
- `CoreUtilities` → generación de configuración nginx → `nginx/NginxConfigGenerator.php`
- `bin/nginx/`, `bin/php/`, `bin/redis/` → `bin/` (binarios tal cual)
- `bin/daemons.sh` → `service/daemons.sh`
- `service` → `service/ServiceManager.sh`
- `bin/install/database.sql` → `install/database.sql`

### 4.7. `data/` — Datos de runtime

**Responsabilidad:** Todo lo que se genera en operación y no es código.

Reemplaza los actuales: `tmp/`, `content/`, `backups/`, `signals/`.

### 4.8. `config/` — Configuración

**Responsabilidad:** Todo lo que el administrador puede cambiar sin editar código.

### 4.9. `resources/` — Recursos estáticos y datos

**Responsabilidad:** Traducciones, datos de referencia (países, timezones), librerías PHP de terceros.

**De dónde sale el código:**
- `includes/langs/*.ini` → `langs/`
- Arrays inline de países de `admin.php` → `data/countries.php`
- `$rErrorCodes` de `constants.php` → `data/error_codes.php`
- `includes/libs/*` → `libs/`

---

## 5. Mapa de migración: de dónde → a dónde

### 5.1. God-object `CoreUtilities.php` (4847 líneas)

| Método/bloque | Archivo destino |
|------------|-------------|
| `init()`, carga de config | `core/Config/ConfigLoader.php` |
| `$db`, `getDatabase()` | `core/Database/DatabaseHandler.php` |
| `getCache()`, `setCache()` | `core/Cache/FileCache.php` |
| Operaciones Redis | `core/Cache/RedisCache.php`, `infrastructure/redis/RedisManager.php` |
| `cleanGlobals()`, `parseIncomingRecursively()` | `core/Http/Request.php` |
| `startMonitor()`, `stopStream()`, `startMovie()` | `domain/Stream/StreamService.php` |
| `isStreamRunning()`, `isMonitorRunning()` | `core/Process/ProcessManager.php` |
| Construcción de comandos FFmpeg | `streaming/Codec/FFmpegCommand.php` |
| Lookup GeoIP | `core/Util/GeoIP.php` |
| Generación de config nginx | `infrastructure/nginx/NginxConfigGenerator.php` |
| Resize/upload de imágenes | `core/Util/ImageUtils.php` |
| Cifrado (AES/token) | `core/Util/Encryption.php` |
| `saveLog()` | `core/Logging/FileLogger.php` |

### 5.2. God-object `admin_api.php` (6981 líneas)

| Método/bloque | Archivo destino |
|------------|-------------|
| `processStream()` | `domain/Stream/StreamService.php` |
| `processMovie()` | `domain/Vod/MovieService.php` |
| `processSerie()` | `domain/Vod/SeriesService.php` |
| `processEpisode()` | `domain/Vod/EpisodeService.php` |
| `processLine()` | `domain/Line/LineService.php` |
| `processMAG()` | `domain/Device/MagService.php` |
| `processEnigma()` | `domain/Device/EnigmaService.php` |
| `processServer()` | `domain/Server/ServerService.php` |
| `processBouquet()` | `domain/Bouquet/BouquetService.php` (+ Repository + Mapper — un archivo) |
| `processUser()` | `domain/User/UserService.php` (+ submit ticket, editProfile) |
| `processGroup()` | `domain/User/GroupService.php` (+ GroupRepository — un archivo) |
| `processCode()` | `domain/Auth/AuthService.php` (+ Code + HMAC — unificados) |
| `processLogin()` | `core/Auth/Authenticator.php` |
| `processSettings()` | `domain/Server/SettingsService.php` (fusionado desde domain/Settings/) |
| `massEditStreams()` | `domain/Stream/StreamService::massEdit()` |
| `checkMinimumRequirements()` | Validación en cada Service |

### 5.3. God-bootstrap `admin.php` (4448 líneas)

| Bloque | Archivo destino |
|------|-------------|
| `session_start()`, config de sesión | `core/Auth/SessionManager.php` |
| 50+ `define()` de estados | `constants.php` |
| Creación de `Database` | `core/Container/ServiceContainer.php` |
| `CoreUtilities::init()` | `bootstrap.php` |
| `$rCountryCodes`, `$rCountries` | `resources/data/admin_constants.php` |
| `$rPermissions` | `resources/data/admin_constants.php` → `core/Auth/Authorization.php` |
| `getUserInfo()` | `domain/User/UserRepository.php` |
| `getSeriesList()`, `updateSeries()` | `domain/Vod/SeriesService.php` (Repository fusionado — §2.4) |
| `secondsToTime()` | `core/Util/TimeUtils.php` |
| `hasPermissions()` | `core/Auth/Authorization.php` |
| Init de Mobile detect | `core/Http/Middleware/` (si hace falta) |
| Init de Translator | `bootstrap.php` → `ServiceContainer` |

### 5.4. Páginas admin/ (100+ archivos)

**Patrón de transformación:**

```
ANTES:
  admin/streams.php (un archivo = SQL + HTML + JS)

DESPUÉS:
  public/Controllers/Admin/StreamController.php            — enrutado
  domain/Stream/StreamRepository.php                       — datos
  public/Views/admin/streams/list.php                      — plantilla
```

### 5.5. Duplicación admin/ ↔ reseller/

```
ANTES:
  admin/header.php (675 líneas)    +  reseller/header.php (284 líneas)
  admin/footer.php (805 líneas)    +  reseller/footer.php
  admin/session.php               +  reseller/session.php
  admin/functions.php             +  reseller/functions.php

DESPUÉS:
  public/Views/layouts/admin.php               — layout único
  public/Views/layouts/footer.php              — footer único
  core/Auth/SessionManager.php                 — gestor de sesiones único
  core/Auth/Authorization.php                  — RBAC define qué ve el usuario
```

### 5.6. Camino de streaming

```
ANTES:
  www/stream/init.php (bootstrap duplicado)
  www/stream/auth.php (800 líneas: auth + block + token + balance)
  www/stream/live.php (708 líneas: token + ondemand + delivery + heartbeat)
  StreamingUtilities.php (1992 líneas: fork de CoreUtilities)

DESPUÉS:
  streaming/StreamingBootstrap.php   — init ligero sin duplicación
  streaming/Auth/TokenAuth.php       — parsing de tokens
  streaming/Auth/StreamAuth.php      — verificación de acceso
  streaming/Protection/GeoBlock.php  — bloqueos (IP/ISP/UA/Country)
  streaming/Delivery/LiveDelivery.php — entrega de contenido
  streaming/Health/BitrateTracker.php — monitoreo de calidad
  core/Cache/*                       — caché común (sin duplicar)
  core/Database/DatabaseHandler.php   — BD común (sin duplicar)
```

---

## 6. Sistema de módulos

### 6.1. Ciclo de vida del módulo

```
1. El módulo se coloca en modules/{name}/
2. Se añade a config/modules.php
3. En el bootstrap: ServiceContainer escanea modules.php
4. Para cada módulo: require module.json → comprobación de dependencias → boot()
5. El módulo registra: servicios, rutas, crons, suscripciones a eventos
6. Eliminación: quitar de modules.php → (opcional) eliminar la carpeta
```

### 6.2. Reglas de aislamiento

```
✅ El módulo PUEDE:
   - Usar servicios de core/ vía constructor injection
   - Llamar a Services de domain/ para operaciones de negocio
   - Usar Repositories de domain/ para leer datos
   - Registrar sus rutas, comandos, crons
   - Suscribirse a hooks de eventos del núcleo
   - Tener sus assets, views, configs

❌ El módulo NO PUEDE:
   - Modificar archivos de core/ o domain/
   - Acceder directamente a la base de datos saltándose el Repository
   - Depender de otro módulo sin declararlo explícitamente en module.json
   - Sobrescribir rutas o servicios del núcleo
   - Añadir restricciones de licenciamiento al núcleo
```

### 6.3. Extensibilidad vía hooks de eventos

Los eventos se usan **solo para hooks de módulos**, no dentro del CRUD normal.

**Cuándo usar eventos:**
- Un módulo quiere reaccionar a una acción del núcleo (p. ej., grabar DVR al arrancar un stream)
- El núcleo no debe saber nada del módulo

**Cuándo NO usarlos:**
- Dentro de operaciones CRUD (creó stream → actualiza nginx) — es una llamada directa en el Service
- Entre componentes del núcleo (es magia difícil de depurar)

```php
// ✅ CORRECTO — eventos para hooks de módulos:
// El núcleo publica:
$dispatcher->dispatch(new StreamStartedEvent($streamId));

// El módulo watch se suscribe:
class WatchModule implements ModuleInterface {
    public function getEventSubscribers(): array {
        return [
            StreamStartedEvent::class => [WatchRecorder::class, 'onStreamStarted'],
        ];
    }
}

// ❌ INCORRECTO — eventos dentro del CRUD:
class StreamService {
    public function create(array $data): int {
        $id = $this->repo->insert($data);
        // NO hace falta dispatch de StreamCreatedEvent para actualizar nginx.
        // Simplemente llamar $this->nginx->reload() directamente.
        $this->nginx->reload();
        return $id;
    }
}
```

### 6.4. Módulos comerciales (futuro)

Los módulos comerciales son módulos normales en `modules/`, pero:
- Se entregan aparte (no en el repositorio principal)
- Pueden contener su propia comprobación de licencia **dentro de sí mismos**
- El núcleo **no contiene** código de comprobación de licencia — si el módulo se elimina, el sistema funciona

```
modules/
├── ministra/          ← open-source, en el repo principal
├── plex/              ← open-source, en el repo principal
├── enterprise-analytics/  ← comercial, repo aparte
│   ├── module.json
│   ├── License.php        ← comprobación de licencia DENTRO del módulo
│   └── AnalyticsModule.php
└── advanced-security/     ← comercial, repo aparte
```

---

## 7. Fronteras del núcleo y los módulos

### 7.1. Diagrama de paquetes y dependencias

```
                    ┌──────────────┐
                    │   public/    │   ← HTTP (Controllers, Views, Routes)
                    └──────┬───────┘
                           │ depends on
              ┌────────────┼────────────┐
              ▼            ▼            ▼
        ┌──────────┐ ┌──────────┐ ┌──────────┐
        │ domain/  │ │streaming/│ │ modules/ │
        └────┬─────┘ └────┬─────┘ └────┬─────┘
             │            │            │
             │ depends on │ depends on │ depends on
             ▼            ▼            ▼
        ┌──────────────────────────────────┐
        │             core/                │   ← Config, DB, Cache, Auth, Process,
        │                                  │     Logging, Events, Container, Util
        └──────────────────────────────────┘
                       │
                       ▼
        ┌──────────────────────────────────┐
        │        infrastructure/           │   ← nginx, redis, bin, service
        └──────────────────────────────────┘
```

**Las flechas siempre apuntan hacia abajo.** Ninguna capa inferior sabe de las superiores.

**Excepción:** `streaming/` — capa horizontal al nivel de `domain/`, pero con acceso read-only a domain/Repository. Tiene su propio entry path ligero (ver §4.3).

### 7.2. Qué entra en el núcleo y qué no

| En el núcleo (`core/` + `domain/`) | En los módulos (`modules/`) |
|-------------------------------|----------------------|
| Gestión de streams (CRUD, arranque, parada) | Portal Ministra |
| Gestión de suscripciones (líneas, paquetes) | Integración Plex |
| Gestión VOD (películas, series) | Fetch de metadatos TMDb |
| Autorización y RBAC | Watermarking fingerprint |
| Infraestructura de servidores | Theft detection |
| EPG (importación básica) | Escaneo de dispositivos MAG |
| Gestión de bouquets | Grabación Watch/DVR |
| Motor de streaming | Analítica avanzada (futuro) |
| API (admin, reseller, player) | Theming white-label (futuro) |

---

### 5.1 includes/ — Archivos obsoletos

```
includes/
├── admin.php               # ~150 funciones globales -> proxy a domain/*/Service.php
├── admin_api.php            # ~80 métodos de la clase AdminAPI -> proxy a domain/*/Service.php
├── reseller_api.php         # ~9 métodos de la clase ResellerAPI
├── CoreUtilities.php        # ~135 métodos estáticos -> proxy a core/ y domain/
├── StreamingUtilities.php   # ~66 métodos estáticos -> proxy a streaming/
├── ts.php                   # Utilidades de timeshift
├── bootstrap/               # Archivos antiguos de inicialización admin
│   ├── admin_bootstrap.php
│   ├── admin_runtime.php
│   └── admin_session.php
├── api/                     # Handlers de API
│   ├── admin/
│   └── reseller/
├── cli/                     # Scripts CLI (monitor, balancer, watchdog, etc.)
├── data/                    # Datos estáticos (permisos)
├── langs/                   # Archivos de localización (en.ini, ru.ini, de.ini, ...)
├── libs/                    # Librerías de terceros
│   ├── Logger.php
│   ├── AsyncFileOperations.php
│   ├── Dropbox.php
│   ├── TMDb/
│   └── ...
└── python/                  # Scripts Python
```

**Estado**: las 150 funciones de `admin.php` y los 135 métodos de `CoreUtilities` ya son envolturas proxy que delegan en `domain/` y `core/`. Al completar la migración, los archivos se eliminarán.

### 5.2 admin/ — Páginas admin legacy

~120 archivos PHP — las antiguas páginas admin que contenían SQL+HTML en un solo archivo. **Reemplazadas** por `public/Controllers/Admin/` + `public/Views/admin/`.

### 5.3 reseller/ — Páginas reseller legacy

~35 archivos PHP — análogo a admin/, pero para el panel de resellers. **Reemplazadas** por `public/Controllers/Reseller/` + `public/Views/reseller/`.

### 5.4 ministra/ — Stalker Portal JS

Archivos JavaScript para soporte de Stalker Portal (dispositivos MAG). No son PHP — los sirve nginx directamente. Se conservan tal cual.

### 5.5 player/ — Web-player integrado

Mini-aplicación (PHP + JS + CSS) para mostrar streams en web. Tiene sus propios `header.php`, `footer.php`, `functions.php`. Se conserva tal cual hasta un refactor aparte.

---

## 8. Variantes de build: MAIN vs LoadBalancer

### 8.1. Dos artefactos desde una misma base de código

El sistema se construye en dos variantes desde un mismo `src/`:

| Artefacto | Propósito | Qué incluye | Qué excluye |
|----------|-----------|--------------|---------------|
| **MAIN** | Servidor principal (admin + streaming) | Todo el contenido de `src/` | Nada |
| **LoadBalancer (LB)** | Servidor de streaming sin gestión | Solo streaming + infraestructura | Panel admin, reseller, player, ministra, módulos solo-admin |

**Principio clave:** el LB es un subconjunto de MAIN. El código no se bifurca: se filtra en el build.

### 8.2. El problema del build LB (RESUELTO ✅ — Fase 9.1)

El antiguo `Makefile` construía el LB desde una lista fija `LB_FILES` que no incluía los nuevos directorios arquitectónicos. Tras las fases 0–8, eso significaba que el build LB no contenía `core/`, `domain/`, `streaming/`, `infrastructure/`, `resources/`, ni `autoload.php` y `bootstrap.php`.

**Solución (Fase 9.1):** `LB_FILES` dividido en `LB_DIRS` + `LB_ROOT_FILES`, añadidos todos los directorios necesarios y las exclusiones solo-admin. Detalles en §8.3.

| Directorio / archivo | ¿Necesario en LB? | Estado |
|----|---|---|
| `autoload.php` | ✅ SÍ | ✅ Añadido a `LB_ROOT_FILES` |
| `bootstrap.php` | ✅ SÍ | ✅ Añadido a `LB_ROOT_FILES` |
| `core/` | ✅ SÍ | ✅ Añadido a `LB_DIRS` |
| `domain/` | ⚠ PARCIAL | ✅ En `LB_DIRS` + exclusiones `User/`, `Device/`, `Auth/` |
| `streaming/` | ✅ SÍ | ✅ Añadido a `LB_DIRS` |
| `infrastructure/` | ✅ SÍ | ✅ Añadido a `LB_DIRS` |
| `resources/` | ⚠ PARCIAL | ✅ En `LB_DIRS` + exclusiones `langs/`, `libs/` |
| `public/` | ❌ NO | ✅ Correctamente ausente |
| `modules/` | ❌ NO | ✅ Correctamente ausente |
| `admin/` | ❌ NO | ✅ Correctamente ausente |
| `ministra/` | ❌ NO | ✅ Correctamente ausente |
| `player/` | ❌ NO | ✅ Correctamente ausente |
| `reseller/` | ❌ NO | ✅ Correctamente ausente |

### 8.3. Configuración implementada del Makefile ✅

#### `LB_DIRS` + `LB_ROOT_FILES` (en lugar del único `LB_FILES`)

```makefile
# Directorios (copiados vía git ls-files | grep "^src/$dir/")
LB_DIRS := bin config content core crons domain includes \
    infrastructure resources signals streaming tmp www

# Archivos raíz (copiados vía git ls-files --error-unmatch)
LB_ROOT_FILES := autoload.php bootstrap.php service status update
```

> **Por qué separado:** grep `"^src/$item/"` con `/` final nunca matchea archivos (`autoload.php`, `service`, `status`, `update`). Por eso los archivos raíz se copian en un bucle aparte vía `git ls-files --error-unmatch`.

#### `LB_DIRS_TO_REMOVE` (directorios solo-admin)

```makefile
LB_DIRS_TO_REMOVE := \
    bin/install \
    bin/redis \
    bin/nginx/conf/codes \
    includes/langs \
    includes/api \
    includes/libs/resources \
    includes/bootstrap \
    domain/User \
    domain/Device \
    domain/Auth \
    resources/langs \
    resources/libs
```

#### `lb_copy_files` — dos bucles

1. **Directorios:** `for lb_item in $(LB_DIRS)` → `git ls-files | grep "^src/$$lb_item/"` → copiar
2. **Archivos raíz:** `for root_file in $(LB_ROOT_FILES)` → `git ls-files --error-unmatch "src/$$root_file"` → copiar

#### `lb_update_copy_files` — comprobación en cascada

```bash
# Para cada archivo cambiado:
for lb_item in $(LB_DIRS); do    # 1. comprobación por directorio
    grep -q "^$$lb_item/"
done
for root_file in $(LB_ROOT_FILES); do  # 2. comprobación por archivo raíz
    [ "$$rel_path" = "$$root_file" ]
done
```

#### `set_permissions` — nuevos directorios

```makefile
# core/ domain/ streaming/ infrastructure/ resources/ → dirs:755, files:644
@for arch_dir in core domain streaming infrastructure resources; do \
    if [ -d "$(TEMP_DIR)/$$arch_dir" ]; then \
        find "$(TEMP_DIR)/$$arch_dir" -type d -exec chmod 755 {} +; \
        find "$(TEMP_DIR)/$$arch_dir" -type f -exec chmod 644 {} +; \
    fi; \
done

# autoload.php, bootstrap.php → 644
chmod 0644 $(TEMP_DIR)/autoload.php 2>/dev/null || true
chmod 0644 $(TEMP_DIR)/bootstrap.php 2>/dev/null || true
```

### 8.4. Qué usa el LB — mapa de dependencias

```
www/stream/*.php ──→ bootstrap.php (CONTEXT_STREAM)
                         │
                         ├──→ autoload.php (class map)
                         ├──→ core/Config/         (rutas, configuración)
                         ├──→ core/Database/       (envoltura PDO)
                         ├──→ core/Cache/          (caché Redis + File)
                         ├──→ core/Auth/           (BruteforceGuard)
                         ├──→ core/Http/           (Request, RequestGuard)
                         ├──→ core/Process/        (ProcessManager)
                         ├──→ core/Logging/        (FileLogger, DatabaseLogger)
                         ├──→ core/Util/           (GeoIP, NetworkUtils, Encryption)
                         ├──→ core/Error/          (ErrorHandler, ErrorCodes)
                         │
                         ├──→ streaming/Auth/      (TokenAuth, StreamAuth, DeviceLock)
                         ├──→ streaming/Delivery/  (LiveDelivery, VodDelivery, SegmentReader)
                         ├──→ streaming/Balancer/  (LoadBalancer, RedirectStrategy)
                         ├──→ streaming/Protection/(RestreamDetector, ConnectionLimiter, GeoBlock)
                         ├──→ streaming/Codec/     (FFmpegCommand, TsParser)
                         │
                         ├──→ domain/Stream/       (StreamProcess, ConnectionTracker, StreamSorter)
                         ├──→ domain/Server/       (ServerRepository)
                         ├──→ domain/Bouquet/      (BouquetMapper — para playlists)
                         ├──→ domain/Vod/          (para entrega VOD)
                         │
                         ├──→ infrastructure/redis/(RedisManager)
                         └──→ resources/data/      (error_codes.php)

console.php cron:* ──→ bootstrap.php (CONTEXT_CLI)
                         └──→ las mismas dependencias + domain/Stream/*

> **Nota:** `crons/*.php` eliminados (Fase 12.8). Todas las llamadas van vía `console.php cron:{name}`.
```

### 8.5. Reglas para desarrollar pensando en el LB

1. ~~**Métodos proxy en `CoreUtilities`/`StreamingUtilities`** deben ser seguros para el LB~~ — **RESUELTO:** los god-objects fueron eliminados (Fase 8), todas las dependencias vía llamadas directas.

2. **Makefile actualizado en sincronía con la migración** ✅ — `LB_DIRS` incluye todos los directorios nuevos. Al añadir nuevos directorios raíz — actualizar `LB_DIRS`.

3. **Testeo del build LB** — tras cada fase de migración hay que comprobar:
   ```bash
   make new && make lb
   # Confirmar que todos los archivos necesarios están presentes
   # Confirmar que los archivos solo-admin están ausentes
   ```

4. **domain/ es parcialmente necesario para el LB** — no se puede excluir `domain/` entero. Solo se excluyen los subdominios admin-specific (`User/`, `Ticket/`, `Device/`).

5. **modules/ se excluye por completo del LB** — todos los módulos actuales (ministra, plex, tmdb, watch, fingerprint, theft-detection, magscan) son funcionalidad admin. En el futuro, si aparece un módulo específico de LB, habrá que añadirlo explícitamente.

### 8.6. Makefile actualizado ✅ (Fase 9.1)

Los 5 cambios aplicados:
1. `LB_FILES` → `LB_DIRS` (14 directorios) + `LB_ROOT_FILES` (5 archivos)
2. `lb_copy_files` — añadido el segundo bucle para archivos raíz
3. `lb_update_copy_files` — comprobación en cascada (dirs → root files)
4. `LB_DIRS_TO_REMOVE` — 6 nuevas exclusiones solo-admin
5. `set_permissions` — permisos correctos en `core/`, `domain/`, `streaming/`, `infrastructure/`, `resources/`, `autoload.php`, `bootstrap.php`

---

## 9. Transacciones y rendimiento

### 9.1. Quién gestiona las transacciones

**Regla:** la transacción la gestiona el **Service**. El controlador y el Repository no abren transacciones.

```
Controller ──→ Service ──→ Repository + Infrastructure
                  │
                  ├── beginTransaction()
                  ├── ... operaciones de negocio ...
                  ├── commit()
                  └── (rollback ante excepción)
```


### 9.2. Patrón de transacción

```php
class StreamService {
    public function massEdit(array $streamIds, array $changes): int {
        $this->db->beginTransaction();
        try {
            $affected = 0;
            foreach ($streamIds as $id) {
                $this->repository->update($id, $changes);
                $affected++;
            }
            $this->db->commit();
            return $affected;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->log('error', "Mass edit failed: " . $e->getMessage());
            throw $e;
        }
    }
}
```

### 9.3. Fronteras por contexto

| Contexto | Transacción | Quién la gestiona | Ejemplo |
|----------|-----------|---------------|--------|
| **Admin CRUD** | Una operación = una transacción | Service | `StreamService::create()` |
| **Mass edit** | Todo el lote = una transacción | Service | `StreamService::massEdit()` |
| **Import** | Chunk de 100 registros | Service | `StreamService::importM3U()` |
| **Cron** | Cada iteración = transacción aparte | CronJob | `ServersCron::processServer()` |
| **Streaming** | ❌ Sin transacciones | — | El hot path no muta vía transacciones |

### 9.4. Procesos externos

La operación "crear stream + arrancar ffmpeg + actualizar nginx" — no es atómica. FFmpeg/nginx son procesos externos, no se puede hacer rollback.

**Patrón:**
1. Operaciones de BD — en transacción
2. Procesos externos — tras el commit, con manejo de errores
3. Si falla el proceso externo — actualizar el estado en BD (`status = 'error'`)

```php
class StreamService {
    public function start(int $streamId): void {
        // 1. BD: actualizar estado
        $this->repository->updateStatus($streamId, 'starting');
        
        // 2. Procesos externos (fuera de la transacción)
        try {
            $pid = $this->processManager->startFFmpeg($streamId);
            $this->repository->updatePid($streamId, $pid);
            $this->repository->updateStatus($streamId, 'running');
        } catch (\Throwable $e) {
            $this->repository->updateStatus($streamId, 'error');
            $this->logger->log('error', "Start failed: {$e->getMessage()}");
            throw $e;
        }
    }
}
```

### 9.5. Dos modos de operación del sistema

| Modo | Ruta | Frecuencia | Latencia admisible | Carga |
|-------|------|---------|-------------------|----------|
| **Hot path** (streaming) | `www/stream/*.php` | ~10K–100K req/min | < 50ms p99 | Bootstrap mínimo, nada de módulos |
| **Cold path** (admin) | `admin/*.php`, API | ~1–100 req/min | < 500ms p99 | Bootstrap completo, todos los módulos |

### 9.6. Presupuesto del hot path

Una petición de streaming (`auth.php` → `live.php`) debe caber en:

```
Bootstrap:     < 5ms  (autoload + constants + DB)
Auth:          < 10ms (token + Redis + bruteforce)
Stream lookup: < 5ms  (caché Redis) | < 15ms (fallback BD)
Delivery:      < 10ms (redirect + headers)
─────────────────────────────────────
Total:         < 30ms (objetivo) | < 50ms (máximo)
```

**NO se puede cargar en el hot path:** Router, EventDispatcher con suscriptores, ServiceContainer con boot completo.
**SE PUEDE:** Database (persistente), Redis (conexión única), GeoIP (mmap), streaming/*.

### 9.7. Async / Queue (futuro)

Las operaciones largas (ffprobe, tmdb, importación masiva) — candidatas a async:

```
HTTP → Service → Queue Job (Redis LPUSH) → Worker (cron) → Result
```

Sin RabbitMQ/Kafka — una cola Redis simple vía `LPUSH`/`BRPOP`.

---

## 10. Reglas para contribuyentes

> Esta sección es el resumen breve para quien no leyó todo el documento. Los detalles, en CONTRIBUTING.md.

### 10.1. Cómo añadir un endpoint nuevo (admin)

1. Encontrar el contexto en `domain/` (p. ej. `domain/Stream/`)
2. Añadir el método en `StreamService.php` (lógica de negocio)
3. Añadir el método SQL en `StreamRepository.php` (si se necesita una consulta nueva)
4. Añadir el action en el Controller o en `admin_api.php` → llamada al Service
5. `php -l` en los archivos modificados

### 10.2. Cómo añadir una tabla nueva

1. Añadir la migración en `infrastructure/install/`
2. Crear el Repository en `domain/{Context}/` con métodos CRUD
3. Registrarlo en `autoload.php`

### 10.3. Cómo NO romper el streaming

- **Nunca** modificar `www/stream/*.php` sin review explícito
- **Nunca** añadir `require` de clases pesadas al bootstrap de streaming
- No usar EventDispatcher / Router / ServiceContainer en el hot path
- Comprobar la latencia: `< 50ms p99`

### 10.4. Reglas de PR

1. Un PR = una feature/bugfix. No mezclar refactor con features.
2. `php -l` en todos los archivos modificados antes del push.
3. Si se toca `core/` o `streaming/` — review obligatorio.
4. Marcar los métodos proxy con `// @legacy-proxy — quitar en Fase 8`.
5. Registrar las clases nuevas en `autoload.php`.

### 10.5. Reglas simples (cheat sheet)

```
✗  No escribas SQL en el Controller        →  Escríbelo en el Repository
✗  No escribas lógica en el Controller     →  Escríbela en el Service
✗  No añadas global                        →  Usa constructor injection
✗  No toques streaming sin review          →  el hot path = territorio sagrado
✗  No modifiques core/ desde los módulos   →  El módulo llama, no modifica
```

---

## Apéndice: Un bootstrap en lugar de tres

`bootstrap.php` provee la clase `XC_Bootstrap` con cuatro contextos de inicialización:

| Contexto | Qué carga | Para qué |
|----------|--------------|----------|
| `CONTEXT_MINIMAL` | autoload + constants + Logger | Scripts que solo necesitan rutas |
| `CONTEXT_CLI` | + Database + CoreUtilities | Tareas cron, scripts CLI |
| `CONTEXT_STREAM` | + Database + StreamingUtilities (caché) | Endpoints de streaming (hot path) |
| `CONTEXT_ADMIN` | + Database + CoreUtilities + API + ResellerAPI + Translator + session + Redis | Panel admin/reseller |

```php
// public/index.php — entry point admin/reseller
require_once '/home/uypanel/bootstrap.php';
XC_Bootstrap::defineStatusConstants();
XC_Bootstrap::boot(XC_Bootstrap::CONTEXT_ADMIN);
// $db, CoreUtilities, API, ResellerAPI, Translator — todo listo
```

```php
// public/stream.php — entry point de streaming (ligero)
require_once '/home/uypanel/bootstrap.php';
XC_Bootstrap::boot(XC_Bootstrap::CONTEXT_STREAM);
// Solo Database + StreamingUtilities, sin admin_api, sin Translator
```

```php
// cli/CronJobs/StreamsCron.php — cron
require_once '/home/uypanel/bootstrap.php';
XC_Bootstrap::boot(XC_Bootstrap::CONTEXT_CLI, [
    'cached'  => true,
    'redis'   => true,
    'process' => 'UyPanel[Streams]'
]);
// Database + CoreUtilities + Redis, sin session ni admin API
```

Un solo autoloader. Un solo conjunto de constantes. Cada punto de entrada llama a `boot()` con el contexto necesario.
Las clases nuevas de la migración se encuentran automáticamente vía `XC_Autoloader` — sin `require_once` manuales.
