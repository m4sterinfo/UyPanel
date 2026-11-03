# UyPanel — Especificación técnica: Sistema de módulos (integración gradual)

> **Versión:** 1.0.0 | **Fecha:** 2026-03-17

---

## Contenido

0. [Análisis del estado actual](#0-análisis-del-estado-actual)
1. [Arquitectura de módulos](#1-arquitectura-de-módulos)
2. [Compatibilidad con el código actual](#2-compatibilidad-con-el-código-actual)
3. [Soporte de entornos (MAIN / LB)](#3-soporte-de-entornos-main--lb)
4. [Sistema de gestión de módulos](#4-sistema-de-gestión-de-módulos)
5. [Sistema de configuración de módulos](#5-sistema-de-configuración-de-módulos)
6. [Extensión de la interfaz (Navbar)](#6-extensión-de-la-interfaz-navbar)
7. [Enrutado de módulos](#7-enrutado-de-módulos)
8. [Aislamiento y dependencias](#8-aislamiento-y-dependencias)
9. [Problemas y bugs detectados](#9-problemas-y-bugs-detectados)
10. [Plan de implementación por etapas](#10-plan-de-implementación-por-etapas)
11. [Riesgos](#11-riesgos)

---

## 0. Análisis del estado actual

### 0.1. Qué ya existe

El sistema de módulos está **parcialmente implementado** en las Fases 5–6 de MIGRATION.md. Existen:

| Componente | Archivo | Estado |
|-----------|------|-----------|
| Contrato de módulo | `src/core/Module/ModuleInterface.php` | ✅ Interfaz completa: 8 métodos |
| Cargador de módulos | `src/core/Module/ModuleLoader.php` | ⚠️ Funciona en CLI, **NO se invoca en web** |
| Configuración overrides | `src/config/modules.php` | ✅ Funciona (enabled/disabled/class override) |
| Contenedor DI | `src/core/Container/ServiceContainer.php` | ✅ Singleton, set/get/factory/tag |
| Event Dispatcher | `src/core/Events/EventDispatcher.php` | ⚠️ Completamente static, API mismatch (ver §9.1) |
| HTTP Router | `src/core/Http/Router.php` | ✅ get/post/api/group/dispatch/dispatchApi |
| CLI Registry | `src/cli/CommandRegistry.php` | ✅ Los módulos registran comandos vía `console.php` |
| 7 módulos | `src/modules/{name}/` | ⚠️ Solo los comandos CLI funcionan; las rutas web son código muerto |

### 0.2. Los 7 módulos existentes

| Módulo | module.json | Clase | boot() | registerRoutes() | registerCommands() | getEventSubscribers() |
|--------|:-----------:|:-----:|:------:|:----------------:|:------------------:|:--------------------:|
| `watch` | ✅ | ✅ | Registra 3 servicios | 5 GET + 4 API | `WatchCronJob`, `WatchItemCommand` | `[]` |
| `plex` | ✅ | ✅ | Registra 3 servicios | 3 GET + 5 API | `PlexCronJob`, `PlexItemCommand` | `[]` |
| `tmdb` | ✅ | ✅ | Registra 1 servicio | Vacío (TODO) | `TmdbCronJob`, `TmdbPopularCronJob` | `[]` |
| `ministra` | ✅ | ✅ | Vacío | Vacío (standalone) | Vacío | `[]` |
| `fingerprint` | ✅ | ✅ | Vacío | 1 GET + 1 API | Vacío | `[]` |
| `theft-detection` | ✅ | ✅ | Vacío | 1 GET | Vacío | `[]` |
| `magscan` | ✅ | ✅ | Vacío | 1 GET + 1 API | Vacío | `[]` |

### 0.3. Brechas críticas

1. **ModuleLoader NO se invoca en el contexto web.** `public/index.php` carga las rutas solo desde `public/routes/{scope}.php`. `ModuleLoader::bootAll($container, $router)` no se llama en ningún punto de la vía HTTP. Las rutas registradas por los módulos vía `registerRoutes()` **nunca llegan al Router**.

2. **Las rutas de módulos están duplicadas en archivos estáticos.** Las rutas de watch, plex, fingerprint, etc. están registradas tanto en `WatchModule::registerRoutes()` como en `public/routes/admin.php`. El registro modular es código muerto.

3. **EventDispatcher API mismatch.** `ModuleLoader::bootAll()` (línea 141) llama a `$dispatcher->listen()`, pero `EventDispatcher` tiene el método `subscribe()`, no `listen()`. El primer módulo con event subscribers provocará un Fatal Error.

4. **Navbar — HTML hardcoded.** La navegación en `public/Views/admin/header.php` (~500 líneas de HTML) es completamente estática. Los módulos no pueden añadir entradas de menú dinámicamente. Las entradas de watch/plex/fingerprint están cosidas directamente en el HTML de header.php.

5. **No hay integración web de `boot()`.** Los servicios modulares (registrados en `boot()`) no están disponibles en el contexto web porque `bootAll()` no se invoca.

6. **`events` no está registrado en el contenedor.** `bootstrap.php::populateContainer()` no registra `EventDispatcher` bajo la clave `events`. Aunque se llamara a `bootAll()`, `$container->has('events')` devolvería `false`.

### 0.4. Qué funciona ahora mismo

- ✅ Comandos CLI de módulos (`console.php` → `ModuleLoader::loadAll()` → `registerAllCommands()`)
- ✅ Auto-discovery de módulos vía `modules/*/module.json`
- ✅ Desactivación de módulos vía `config/modules.php`
- ✅ Las rutas de módulos **de facto** funcionan, pero registradas estáticamente en `routes/admin.php`, no vía `ModuleInterface::registerRoutes()`

---

## 1. Arquitectura de módulos

### 1.1. Formato del módulo (existente — sin cambios)

```
src/modules/{module-name}/
├── module.json                    # Metadatos (name, version, description, requires_core, environment)
├── {ModuleName}Module.php         # Punto de entrada: implements ModuleInterface
├── {ModuleName}Service.php        # Lógica de negocio (opcional)
├── {ModuleName}Repository.php     # Consultas SQL (opcional)
├── {ModuleName}Controller.php     # Handlers HTTP (opcional)
├── config.php                     # Configuración del módulo (NUEVO — §5)
├── navbar.php                     # Entradas de menú (NUEVO — §6)
├── views/                         # Plantillas de páginas (opcional)
│   ├── {page}.php
│   └── {page}_scripts.php
└── assets/                        # CSS/JS/imágenes (opcional)
```

### 1.2. module.json (extensión del formato)

**Formato actual:**
```json
{
    "name": "watch",
    "description": "Watch activity tracking module",
    "version": "1.0.0",
    "requires_core": ">=2.0"
}
```

**Extensión (retrocompatible):**
```json
{
    "name": "watch",
    "description": "Watch activity tracking module",
    "version": "1.0.0",
    "requires_core": ">=2.0",
    "environment": "main",
    "has_settings": true,
    "has_navbar": true,
    "dependencies": []
}
```

| Campo | Tipo | Obligatorio | Por defecto | Descripción |
|------|-----|:-----------:|:------------:|----------|
| `name` | string | ✅ | — | Coincide con el nombre del directorio |
| `description` | string | ❌ | `""` | Para gestión en la UI |
| `version` | string | ✅ | — | semver |
| `requires_core` | string | ❌ | `"*"` | Versión del núcleo |
| `environment` | string | ❌ | `"main"` | `"main"` \| `"lb"` \| `"any"` (NUEVO — §3) |
| `has_settings` | bool | ❌ | `false` | Tiene config.php (NUEVO — §5) |
| `has_navbar` | bool | ❌ | `false` | Tiene navbar.php (NUEVO — §6) |
| `dependencies` | string[] | ❌ | `[]` | Otros módulos (para el orden de carga) |

### 1.3. Contrato del módulo (`ModuleInterface` existente — sin cambios)

```php
// src/core/Module/ModuleInterface.php — YA EXISTE
interface ModuleInterface {
    public function getName(): string;
    public function getVersion(): string;
    public function boot(ServiceContainer $container): void;
    public function registerRoutes(Router $router): void;
    public function registerCommands(CommandRegistry $registry): void;
    public function getEventSubscribers(): array;
    public function install(): void;
    public function uninstall(): void;
}
```

### 1.4. Ciclo de vida (existente — precisión del orden)

```
1. ModuleLoader::loadAll() — escanea modules/*/module.json
   ├── Lee config/modules.php (overrides: enabled => false)
   ├── Filtra por environment (NUEVO — §3)
   ├── Resuelve dependencias (topological sort, NUEVO — §8.3)
   └── Crea instancias: new {ModuleName}Module()

2. ModuleLoader::bootAll($container, $router)
   ├── module->boot($container)          — registro de servicios (try-catch por módulo)
   ├── module->registerRoutes($router)   — rutas HTTP
   └── EventDispatcher::subscribe(...)   — suscripciones a eventos

3. NavbarBuilder::getItems($cache, $loader)
   ├── Calcula modules_hash (name:version ordenados)
   ├── Consulta FileCache  →  HIT → devuelve items
   └── MISS → buildFromModules()
       ├── Para cada módulo: require navbar.php (try-catch por módulo)
       ├── validateItem() → schema estricto, unicidad de id, profundidad máxima
       └── Agrupación por position, ordenamiento por order

4. (solo CLI) ModuleLoader::registerAllCommands($registry)
   └── module->registerCommands($registry)

5. Runtime — Router dispatch → Controller → Service → Repository
   └── header.php → NavbarBuilder::render($position, $can)
       ├── Filtrado por permisos vía closure $can
       └── Render HTML de las entradas modulares en los injection points

6. (al desactivar)
   ├── module->uninstall() — limpieza de tablas/datos
   └── NavbarBuilder::invalidate($cache) — invalidación de la caché del navbar
```

---

## 2. Compatibilidad con el código actual

### 2.1. Principio: «dos vías funcionando simultáneamente»

La fase de integración **no conmuta** el enrutado de legacy a modular de un solo golpe. En su lugar:

1. Las rutas legacy en `routes/admin.php` siguen funcionando
2. `ModuleLoader::bootAll()` registra las rutas modulares en paralelo
3. En el dispatch — el Router busca primero la ruta modular, luego fallback a legacy
4. A medida que se migra, las rutas legacy se eliminan de `routes/admin.php`

### 2.2. Mapa de cambios

| Archivo | Acción | Fase |
|------|----------|:----:|
| `src/public/index.php` | Añadir llamada a `ModuleLoader::loadAll()` + `bootAll()` | M-1 |
| `src/bootstrap.php` :: `populateContainer()` | Registrar `events` en el contenedor | M-1 |
| `src/core/Module/ModuleLoader.php` :: `bootAll()` | Corregir `listen` → `subscribe` | M-1 |
| `src/core/Events/EventDispatcher.php` | Añadir alias `listen()` → `subscribe()` | M-1 |
| `src/core/Http/NavbarPositions.php` | Crear: constantes de injection points (NUEVO) | M-3 |
| `src/core/Http/NavbarBuilder.php` | Crear: builder con FileCache + validación (NUEVO) | M-3 |
| `src/public/Views/admin/header.php` | Añadir 4 injection points `NavbarBuilder::render()` | M-3 |
| `src/core/Module/ModuleLoader.php` | Añadir filtrado `environment`, orden por dependencias | M-2 |
| `src/config/modules.php` | Sin cambios | — |
| `src/core/Module/ModuleInterface.php` | Sin cambios | — |
| `src/core/Container/ServiceContainer.php` | Sin cambios | — |
| `src/core/Http/Router.php` | Sin cambios | — |
| `src/cli/CommandRegistry.php` | Sin cambios | — |
| `src/console.php` | Sin cambios | — |

### 2.3. Archivos sin cambios

- Los 7 `{Module}Module.php` existentes — sin cambios después de M-1
- `domain/`, `core/` (excepto lo indicado arriba) — sin cambios
- `streaming/` — sin cambios
- `infrastructure/` — sin cambios
- `includes/` — sin cambios

---

## 3. Soporte de entornos (MAIN / LB)

### 3.1. Definición actual de entorno

El entorno se define en la fase de build (`Makefile`):
- **MAIN** — build completo (admin + streaming + módulos)
- **LB (LoadBalancer)** — solo streaming, sin `modules/`, sin `public/`, sin admin

En runtime: archivo-constante o `SERVER_TYPE` de la BD (tabla `servers`, flag `is_main`).

### 3.2. Filtrado de módulos por entorno

**Comportamiento actual:** `modules/` está completamente excluida del build LB en el Makefile. En el LB los módulos no existen físicamente.

**Extensión (para futuros módulos LB):**

Añadir en `ModuleLoader::loadAll()` la comprobación del campo `environment` de `module.json`:

```php
// En ModuleLoader::loadAll(), tras leer module.json:
$manifest = json_decode(file_get_contents($jsonFile), true);
$env = $manifest['environment'] ?? 'main';

// Determinamos el entorno actual
$currentEnv = defined('SERVER_TYPE') && SERVER_TYPE === 'lb' ? 'lb' : 'main';

// Filtrado
if ($env !== 'any' && $env !== $currentEnv) {
    continue;
}
```

| `environment` | MAIN | LB |
|:------------:|:----:|:--:|
| `"main"` | ✅ Se carga | ❌ Se omite |
| `"lb"` | ❌ Se omite | ✅ Se carga |
| `"any"` | ✅ Se carga | ✅ Se carga |

### 3.3. Dónde se guarda

- `module.json` → campo `environment` (declaración por módulo)
- Runtime: `SERVER_TYPE` / constante `MAIN_SERVER` (definida en `core/Config/AppConfig.php`)
- Build time: `Makefile` → `LB_DIRS_TO_REMOVE` (exclusión física)

### 3.4. Impacto en el Makefile

Si en el futuro hacen falta módulos LB:

```makefile
# Ahora — los módulos están completamente excluidos del LB:
# modules/ no está en LB_DIRS

# En el futuro — copiar modules/ al LB, eliminando los main-only:
LB_DIRS += modules
LB_MODULE_DIRS_TO_REMOVE := modules/watch modules/plex modules/tmdb \
    modules/fingerprint modules/theft-detection modules/magscan
```

**En esta etapa no se requieren cambios en el Makefile.** Los 7 módulos actuales son `environment: "main"`.

---

## 4. Sistema de gestión de módulos

### 4.1. Almacenamiento del estado (on/off)

**Implementación actual (se conserva):**

```php
// src/config/modules.php
return [
    // 'theft-detection' => ['enabled' => false],
];
```

Por defecto, todos los módulos detectados están **activados**. `config/modules.php` solo guarda las desactivaciones.

### 4.2. Activación/desactivación (ya funciona)

```
Activar:     1. Colocar el módulo en modules/{name}/
              2. Añadir module.json
              3. El módulo se detecta automáticamente en la siguiente petición

Desactivar:  1. Añadir en config/modules.php: 'name' => ['enabled' => false]
              2. El módulo no se carga, pero los archivos quedan

Eliminar:    1. Llamar module->uninstall() (si hace falta limpiar la BD)
              2. Eliminar el directorio modules/{name}/
```

### 4.3. API de gestión (NUEVO)

Nuevo controlador: `public/Controllers/Admin/ModuleManagerController.php`

```php
class ModuleManagerController {

    /**
     * GET /modules — lista de módulos con su estado
     */
    public function index() {
        $loader = new ModuleLoader();
        $loader->loadAll();

        // Recopilamos la información de todos los módulos (incluidos los desactivados)
        $allModules = [];
        $modulesDir = MAIN_HOME . 'modules';
        foreach (glob($modulesDir . '/*/module.json') as $jsonFile) {
            $manifest = json_decode(file_get_contents($jsonFile), true);
            $name = $manifest['name'] ?? basename(dirname($jsonFile));
            $allModules[$name] = [
                'name'        => $name,
                'description' => $manifest['description'] ?? '',
                'version'     => $manifest['version'] ?? '0.0.0',
                'environment' => $manifest['environment'] ?? 'main',
                'has_settings' => $manifest['has_settings'] ?? false,
                'enabled'     => $loader->isLoaded($name),
            ];
        }

        // Pasamos a la view
        $rModules = $allModules;
        include VIEW_PATH . '/admin/modules/list.php';
    }

    /**
     * API: enable/disable del módulo
     * POST action=module_toggle, module={name}, enabled={0|1}
     */
    public function apiToggle() {
        $name = $_POST['module'] ?? '';
        $enabled = (bool)($_POST['enabled'] ?? true);

        // Validación: el módulo existe
        $modulePath = MAIN_HOME . 'modules/' . basename($name);
        if (!is_dir($modulePath) || !file_exists($modulePath . '/module.json')) {
            echo json_encode(['result' => false, 'error' => 'Module not found']);
            exit;
        }

        // Actualizamos config/modules.php
        $configPath = CONFIG_PATH . 'modules.php';
        $overrides = file_exists($configPath) ? require $configPath : [];
        if (!is_array($overrides)) {
            $overrides = [];
        }

        if ($enabled) {
            unset($overrides[$name]);
        } else {
            $overrides[$name] = ['enabled' => false];
        }

        // Guardamos
        $content = "<?php\nreturn " . var_export($overrides, true) . ";\n";
        file_put_contents($configPath, $content, LOCK_EX);

        echo json_encode(['result' => true]);
        exit;
    }
}
```

**Rutas:**
```php
// En public/routes/admin.php:
$router->get('modules', [ModuleManagerController::class, 'index'], [
    'permission' => ['adv', 'settings'],
]);
$router->api('module_toggle', [ModuleManagerController::class, 'apiToggle'], [
    'permission' => ['adv', 'settings'],
]);
```

### 4.4. Degradación elegante

Cuando un módulo está desactivado o eliminado:

1. `ModuleLoader` lo omite en `loadAll()` — sus rutas no se registran
2. Los navbar items de `navbar.php` no se cargan
3. Los comandos CLI no se registran
4. El controlador del módulo no se instancia → 404 al intentar acceder a la página del módulo
5. Los event subscribers no se suscriben → el núcleo no llama al módulo

**Criterio:** eliminar `rm -rf modules/watch` no provoca ningún error ni en web ni en CLI.

---

## 5. Sistema de configuración de módulos

### 5.1. Condiciones para tener configuración

Un módulo **tiene configuración** si:
- `module.json` contiene `"has_settings": true`
- El archivo `modules/{name}/config.php` existe y devuelve un array de campos

### 5.2. Contrato de config.php (formato de descripción de campos)

```php
// modules/watch/config.php
return [
    'section' => 'Watch Folder Settings',
    'fields' => [
        [
            'key'         => 'watch_enabled',
            'label'       => 'Enable Watch Folders',
            'type'        => 'toggle',       // toggle | text | number | select | textarea
            'default'     => 0,
            'description' => 'Enable or disable all watch folder monitoring',
        ],
        [
            'key'         => 'watch_interval',
            'label'       => 'Scan Interval (seconds)',
            'type'        => 'number',
            'default'     => 300,
            'min'         => 60,
            'max'         => 3600,
        ],
        [
            'key'         => 'watch_format',
            'label'       => 'Output Format',
            'type'        => 'select',
            'options'     => ['ts' => 'MPEG-TS', 'hls' => 'HLS', 'dash' => 'DASH'],
            'default'     => 'ts',
        ],
    ],
];
```

### 5.3. Almacenamiento de datos

La configuración de los módulos se guarda en la **tabla `settings` existente** con el prefijo `module_{name}_`:

```sql
-- Ejemplo para el módulo watch:
INSERT INTO settings (`key`, `value`) VALUES ('module_watch_enabled', '1');
INSERT INTO settings (`key`, `value`) VALUES ('module_watch_interval', '300');
```

Acceso vía el `SettingsManager` existente:

```php
// Lectura:
$enabled = SettingsManager::get('module_watch_enabled', 0);

// Escritura (desde el controlador de configuración):
SettingsManager::update('module_watch_enabled', $value);
```

### 5.4. Generación de UI (automática)

Nuevo partial: `public/Views/admin/modules/settings_form.php`

Genera el formulario HTML desde el descriptor `config.php`, de forma análoga a como los actuales `settings_*.php` funcionan en el núcleo. Tipo de campo → input HTML:

| `type` | HTML |
|--------|------|
| `toggle` | `<input type="checkbox" data-plugin="switchery">` |
| `text` | `<input type="text" class="form-control">` |
| `number` | `<input type="number" class="form-control" min=... max=...>` |
| `select` | `<select class="form-control">` |
| `textarea` | `<textarea class="form-control">` |

**Ruta:** Cada módulo con `has_settings: true` obtiene automáticamente la ruta `settings/{name}`, atendida por `ModuleManagerController::settings($name)`.

### 5.5. Alternativa: página de configuración propia

El módulo **puede** NO usar la UI auto-generada y en su lugar proveer una view propia vía `registerRoutes()`. Ejemplo: `WatchModule` ya registra `settings/watch` → `WatchController::settings()` con una view propia.

La auto-generación es el fallback para módulos simples sin controlador de configuración propio.

---

## 6. Extensión de la interfaz (Navbar)

### 6.1. Análisis de la implementación actual

Archivo: `src/public/Views/admin/header.php`, líneas 273–714.

La navegación es **HTML totalmente hardcoded** dentro de `<ul class="navigation-menu">`. Estructura:

```
Dashboard → (submenu)
Servers → Install LB, Manage, Proxies, Order, Process Monitor
Users → Lines (add/manage/mass), MAG (add/manage/mass), Enigma (add/manage/mass), Resellers
Content → Streams, Created Channels, Movies, Series, Radio, Archive, EPG View
Bouquets → Add, Manage, Order
Management → Service Setup (Packages, Categories, Groups, EPG, Profiles, Plex, Watch),
             Access Codes, Security, Tools (Channel Order, Fingerprint, Mass Delete, ...),
             Logs (17 entradas), Tickets
Providers → Add, Manage
```

Cada entrada está envuelta en `Authorization::check('adv', '...')`. Las entradas modulares (Plex, Watch, Fingerprint, Theft Detection) están cosidas directamente en el HTML.

### 6.2. Por qué hace falta un enfoque data-driven

La estructura actual es HTML monolítico. Los módulos no pueden añadir entradas de menú sin hardcodear en header.php.

Requisitos:
- Los módulos declaran navbar items vía `navbar.php`
- Los datos (estructura de items) se cachean — rebuild solo cuando cambia la lista de módulos
- El HTML se renderiza en cada petición con filtrado por los permisos del usuario actual (no se puede cachear el HTML — cada admin tiene sus permisos)
- Un módulo roto no rompe todo el navbar — fail-safe por módulo
- Validación estricta del formato de items — el módulo inválido se loguea y se omite

### 6.3. Arquitectura: 4 puntos de inyección en header.php

**Estrategia: inyección puntual, no reescritura completa.**

No tocamos el 90% de header.php (entradas core: Dashboard, Servers, Users, Content, Bouquets, Management, Providers). Añadimos **4 injection points** dentro de las secciones existentes, donde ahora viven las entradas modulares hardcodeadas:

```php
// header.php — dentro de Management > Service Setup, tras "Watch Folders":
<?php NavbarBuilder::render(NavbarPositions::MANAGEMENT_SERVICE_SETUP, $can); ?>

// header.php — dentro de Management > Tools, tras "Stream Tools":
<?php NavbarBuilder::render(NavbarPositions::MANAGEMENT_TOOLS, $can); ?>

// header.php — dentro de Management > Logs, antes del </ul> de cierre:
<?php NavbarBuilder::render(NavbarPositions::MANAGEMENT_LOGS, $can); ?>

// header.php — tras la sección Providers, antes del </ul> de cierre de navigation-menu:
<?php NavbarBuilder::render(NavbarPositions::TOP, $can); ?>
```

La variable `$can` se define una vez al inicio de header.php:

```php
$can = function ($type, $code) {
    return Authorization::check($type, $code);
};
```

Es un injection point: en tests o en un futuro refactor de ACL, `$can` se sustituye sin cambiar NavbarBuilder.

**Core navbar — arquitectura transicional.** Los injection points vía `NavbarPositions` formalizan el contrato. Si en el futuro el core navbar también pasa a ser data-driven, las posiciones ya están estables. Pero reescribir el core navbar es una tarea aparte, no parte de M-3.

### 6.4. Clase NavbarPositions

Archivo nuevo: `src/core/Http/NavbarPositions.php`

```php
class NavbarPositions {

	/** Tras la sección Providers — menús modulares de nivel superior */
	const TOP = 'top';

	/** Dentro de Management → Service Setup */
	const MANAGEMENT_SERVICE_SETUP = 'management.service_setup';

	/** Dentro de Management → Tools */
	const MANAGEMENT_TOOLS = 'management.tools';

	/** Dentro de Management → Logs */
	const MANAGEMENT_LOGS = 'management.logs';

	/** Todas las posiciones válidas (para validación) */
	const ALL = [
		self::TOP,
		self::MANAGEMENT_SERVICE_SETUP,
		self::MANAGEMENT_TOOLS,
		self::MANAGEMENT_LOGS,
	];

	/** Posición por defecto si el módulo no indicó una */
	const DEFAULT_POSITION = self::TOP;
}
```

### 6.5. Clase NavbarBuilder

Archivo nuevo: `src/core/Http/NavbarBuilder.php`

Separación de responsabilidades:
- **Data layer** (`buildFromModules`, `getItems`) — recolección y caché de la estructura de items
- **Validation** (`validateItem`, `validatePosition`) — comprobación estricta del formato
- **Presentation** (`render`) — render HTML con filtrado por permisos

```php
class NavbarBuilder {

	/** Profundidad máxima de anidamiento (límite de UI) */
	const MAX_DEPTH = 2;

	/** Clave de caché */
	const CACHE_KEY = 'navbar_modules';

	/** Safety net TTL — 1 hora. La invalidación principal es vía modules_hash */
	const CACHE_TTL = 3600;

	/** @var array|null Items cacheados (in-memory por petición) */
	private static $cachedItems = null;

	// ─── Data Layer ────────────────────────────────────────────────

	/**
	 * Obtener items de la caché o reconstruirlos.
	 *
	 * La caché se invalida automáticamente al cambiar la lista de módulos
	 * (añadir, eliminar, activar, desactivar, actualizar versión).
	 *
	 * @param FileCache $cache Driver de caché
	 * @param ModuleLoader $loader Cargador de módulos (ya con loadAll)
	 * @return array Items agrupados por position
	 */
	public static function getItems(FileCache $cache, ModuleLoader $loader) {
		if (self::$cachedItems !== null) {
			return self::$cachedItems;
		}

		$currentHash = self::getModulesHash($loader);

		// Lectura de caché
		$cached = $cache->get(self::CACHE_KEY, self::CACHE_TTL);
		if ($cached !== false && isset($cached['hash']) && $cached['hash'] === $currentHash) {
			self::$cachedItems = $cached['items'];
			return self::$cachedItems;
		}

		// Cache miss o hash mismatch → rebuild
		$items = self::buildFromModules($loader);
		$cache->set(self::CACHE_KEY, [
			'hash' => $currentHash,
			'items' => $items,
		]);

		self::$cachedItems = $items;
		return $items;
	}

	/**
	 * Construir items desde el navbar.php de todos los módulos cargados.
	 *
	 * Fail-safe: un error en el navbar.php de un módulo no rompe los demás.
	 * Los items inválidos se loguean y se omiten.
	 *
	 * @param ModuleLoader $loader
	 * @return array Items agrupados por position: ['top' => [...], 'management.tools' => [...]]
	 */
	public static function buildFromModules(ModuleLoader $loader) {
		$grouped = array_fill_keys(NavbarPositions::ALL, []);
		$globalIds = [];

		foreach ($loader->getModules() as $name => $module) {
			try {
				$modulePath = $loader->getModulePath($name);
				$file = $modulePath . '/navbar.php';

				if (!file_exists($file)) {
					continue;
				}

				$items = require $file;
				if (!is_array($items)) {
					error_log("NavbarBuilder: module '{$name}' navbar.php must return array");
					continue;
				}

				foreach ($items as $item) {
					$item['_module'] = $name;

					$errors = self::validateItem($item, 0, $globalIds);
					if (!empty($errors)) {
						foreach ($errors as $err) {
							error_log("NavbarBuilder: module '{$name}': {$err}");
						}
						continue;
					}

					$position = $item['position'] ?? NavbarPositions::DEFAULT_POSITION;
					$grouped[$position][] = $item;
				}
			} catch (\Throwable $e) {
				error_log("NavbarBuilder: module '{$name}' failed: " . $e->getMessage());
			}
		}

		// Ordenamiento por order dentro de cada posición
		foreach ($grouped as $pos => &$items) {
			usort($items, function ($a, $b) {
				return ($a['order'] ?? 100) - ($b['order'] ?? 100);
			});
		}

		return $grouped;
	}

	/**
	 * Invalidación explícita de la caché del navbar.
	 *
	 * Se llama desde ModuleManagerController::apiToggle() al activar/desactivar un módulo.
	 * Safety net: incluso sin la llamada, el hash fingerprint detectará el cambio.
	 *
	 * @param FileCache $cache
	 */
	public static function invalidate(FileCache $cache) {
		$cache->delete(self::CACHE_KEY);
		self::$cachedItems = null;
	}

	// ─── Cache Fingerprint ─────────────────────────────────────────

	/**
	 * Calcular el hash de la lista actual de módulos.
	 *
	 * El hash cambia al: añadir/eliminar módulo, activar/desactivar,
	 * actualizar versión, cambiar has_navbar en module.json.
	 *
	 * @param ModuleLoader $loader
	 * @return string md5 hash
	 */
	private static function getModulesHash(ModuleLoader $loader) {
		$parts = [];
		foreach ($loader->getModules() as $name => $module) {
			$parts[] = $name . ':' . $module->getVersion();
		}
		sort($parts);
		return md5(implode('|', $parts));
	}

	// ─── Validation ────────────────────────────────────────────────

	/**
	 * Validación de un item.
	 *
	 * Comprueba:
	 * - Campos obligatorios (id, label, url)
	 * - Unicidad de id (global)
	 * - Validez de position (en NavbarPositions::ALL)
	 * - Profundidad máxima de anidamiento (MAX_DEPTH)
	 * - Comprobación recursiva de children
	 *
	 * @param array $item Elemento de navegación
	 * @param int $depth Profundidad actual (0 = top-level)
	 * @param array &$globalIds Set de ids ya ocupados (para deduplicación)
	 * @return array Array de errores (vacío = válido)
	 */
	public static function validateItem(array $item, int $depth, array &$globalIds) {
		$errors = [];

		// Campos obligatorios
		if (empty($item['id']) || !is_string($item['id'])) {
			$errors[] = "missing or invalid 'id'";
		}
		if (empty($item['label']) || !is_string($item['label'])) {
			$errors[] = "missing or invalid 'label'";
		}
		if (empty($item['url']) || !is_string($item['url'])) {
			$errors[] = "missing or invalid 'url'";
		}

		// Unicidad de id
		if (!empty($item['id'])) {
			if (isset($globalIds[$item['id']])) {
				$errors[] = "duplicate id '{$item['id']}' (already registered by module '{$globalIds[$item['id']]}')";
			} else {
				$globalIds[$item['id']] = $item['_module'] ?? 'unknown';
			}
		}

		// Validación de position (solo para items top-level)
		if ($depth === 0 && !empty($item['position'])) {
			if (!in_array($item['position'], NavbarPositions::ALL, true)) {
				$errors[] = "invalid position '{$item['position']}'. Allowed: " . implode(', ', NavbarPositions::ALL);
			}
		}

		// Comprobación del formato de permission
		if (isset($item['permission'])) {
			if (!is_array($item['permission']) || count($item['permission']) !== 2) {
				$errors[] = "'permission' must be [type, code] array";
			}
		}

		// Comprobación recursiva de children
		if (!empty($item['children'])) {
			if ($depth >= self::MAX_DEPTH - 1) {
				$errors[] = "children exceed max depth " . self::MAX_DEPTH;
			} else {
				foreach ($item['children'] as $child) {
					$child['_module'] = $item['_module'] ?? 'unknown';
					$childErrors = self::validateItem($child, $depth + 1, $globalIds);
					$errors = array_merge($errors, $childErrors);
				}
			}
		}

		return $errors;
	}

	private static function validatePosition($position) {
		return in_array($position, NavbarPositions::ALL, true);
	}

	// ─── Presentation ──────────────────────────────────────────────

	/**
	 * Renderizar los items de un injection point concreto.
	 *
	 * Se llama desde header.php. El filtrado por permisos — vía closure $can.
	 *
	 * @param string $position Posición de NavbarPositions (p. ej. 'management.tools')
	 * @param callable $can fn(string $type, string $code): bool — comprobación de permisos
	 */
	public static function render(string $position, callable $can) {
		if (self::$cachedItems === null) {
			return;
		}

		$items = self::$cachedItems[$position] ?? [];
		if (empty($items)) {
			return;
		}

		foreach ($items as $item) {
			if (!self::checkPermission($item, $can)) {
				continue;
			}

			if (!empty($item['children'])) {
				self::renderSubmenu($item, $can);
			} else {
				self::renderItem($item);
			}
		}
	}

	private static function renderItem($item) {
		echo '<li><a href="' . htmlspecialchars($item['url']) . '">';
		if (!empty($item['icon'])) {
			echo '<i class="' . htmlspecialchars($item['icon']) . '"></i>';
		}
		echo htmlspecialchars($item['label']) . '</a></li>';
	}

	private static function renderSubmenu($item, callable $can) {
		echo '<li class="has-submenu">';
		echo '<a href="#">';
		if (!empty($item['icon'])) {
			echo '<i class="' . htmlspecialchars($item['icon']) . '"></i>';
		}
		echo htmlspecialchars($item['label']);
		echo ' <div class="arrow-down"></div></a>';
		echo '<ul class="submenu">';

		foreach ($item['children'] as $child) {
			if (!self::checkPermission($child, $can)) {
				continue;
			}
			echo '<li><a href="' . htmlspecialchars($child['url']) . '">' 
				. htmlspecialchars($child['label']) . '</a></li>';
		}

		echo '</ul></li>';
	}

	private static function checkPermission($item, callable $can) {
		if (empty($item['permission'])) {
			return true;
		}
		return $can($item['permission'][0], $item['permission'][1]);
	}

	/**
	 * Reset de la caché in-memory (para tests)
	 */
	public static function reset() {
		self::$cachedItems = null;
	}
}
```

### 6.6. Schema estricto de items

Cada elemento de `navbar.php` DEBE cumplir el schema:

| Campo | Obligatorio | Tipo | Restricción |
|------|:----------:|------|--------------|
| `id` | ✅ | string | Único globalmente, namespaced: `watch.folders`, `plex.sync` |
| `label` | ✅ | string | Non-empty, texto visible |
| `url` | ✅ | string | Non-empty, URL relativa o `#` para parent |
| `permission` | ❌ | array | `[type, code]` — dos elementos, o null (= visible para todos) |
| `icon` | ❌ | string | Clase CSS del icono: `fas fa-folder` |
| `position` | ❌ | string | Una de `NavbarPositions::ALL` (default: `top`) |
| `order` | ❌ | int | Orden dentro de la posición (default: 100) |
| `children` | ❌ | array | El mismo schema, PERO sin children (max depth = 2) |

**La validación se ejecuta en el build.** El item inválido se loguea en `error_log` con el nombre del módulo y se omite. Todo el navbar sigue funcionando.

### 6.7. Caché

#### Estrategia: fingerprint modules_hash

Un solo mecanismo en lugar de tres:

```
getItems(cache, loader)
  ├── hash = md5(lista ordenada de "name:version" de todos los módulos cargados)
  ├── cached = FileCache::get('navbar_modules', TTL=3600)
  ├── if cached.hash === hash → return cached.items     (HIT)
  └── else → buildFromModules() → validate → cache.set() (MISS)
```

**Cuándo cambia el hash automáticamente:**
- Añadir/eliminar el directorio de un módulo
- Activar/desactivar vía `config/modules.php`
- Actualizar la versión del módulo

**Invalidación explícita (adicional):**
`NavbarBuilder::invalidate($cache)` se llama desde `ModuleManagerController::apiToggle()` para reacción instantánea en la petición actual.

**Safety net:** TTL = 3600 segundos. Incluso con caché corrupta o copia manual de módulos — rebuild en 1 hora como máximo.

**Qué se cachea:** Solo la estructura de datos (array de items, agrupados por position). **NO** el HTML. El render con filtrado por permisos — en cada petición.

**Formato de almacenamiento:** igbinary vía `FileCache` (como todo el proyecto). Ruta: `CACHE_TMP_PATH . 'navbar_modules'`.

### 6.8. Formato del navbar.php del módulo

```php
// modules/watch/navbar.php
return [
	[
		'id'         => 'watch.folders',
		'label'      => 'Watch Folders',
		'icon'       => 'fas fa-folder-open',
		'url'        => '#',
		'permission' => ['adv', 'folder_watch'],
		'position'   => 'management.service_setup',
		'order'      => 110,
		'children'   => [
			[
				'id'         => 'watch.manage',
				'label'      => 'Manage Folders',
				'url'        => 'watch',
				'permission' => ['adv', 'folder_watch'],
			],
			[
				'id'         => 'watch.add',
				'label'      => 'Add Folder',
				'url'        => 'watch/add',
				'permission' => ['adv', 'folder_watch'],
			],
			[
				'id'         => 'watch.output',
				'label'      => 'Watch Output',
				'url'        => 'watch_output',
				'permission' => ['adv', 'folder_watch'],
			],
			[
				'id'         => 'watch.settings',
				'label'      => 'Settings',
				'url'        => 'settings_watch',
				'permission' => ['adv', 'folder_watch_settings'],
			],
		],
	],
];
```

### 6.9. Integración en el ciclo de vida

**En `index.php` (tras `ModuleLoader::bootAll()`):**

```php
// Navbar: recolectar items (de caché o rebuild)
$navbarCache = new FileCache(CACHE_TMP_PATH);
NavbarBuilder::getItems($navbarCache, $moduleLoader);
```

**En `header.php` (4 injection points):**

```php
// Al inicio de header.php:
$can = function ($type, $code) {
	return Authorization::check($type, $code);
};

// Dentro de Management → Service Setup (tras el Plex/Watch hardcodeado):
<?php NavbarBuilder::render(NavbarPositions::MANAGEMENT_SERVICE_SETUP, $can); ?>

// Dentro de Management → Tools (tras el Fingerprint hardcodeado):
<?php NavbarBuilder::render(NavbarPositions::MANAGEMENT_TOOLS, $can); ?>

// Dentro de Management → Logs (tras el Theft Detection hardcodeado):
<?php NavbarBuilder::render(NavbarPositions::MANAGEMENT_LOGS, $can); ?>

// Tras la sección Providers:
<?php NavbarBuilder::render(NavbarPositions::TOP, $can); ?>
```

**En `ModuleManagerController::apiToggle()` (al activar/desactivar un módulo):**

```php
// Tras escribir config/modules.php:
NavbarBuilder::invalidate(new FileCache(CACHE_TMP_PATH));
```

### 6.10. Migración de las entradas existentes (gradual)

**Fase M-3a:** Crear `NavbarPositions.php` y `NavbarBuilder.php`. Añadir los 4 injection points en header.php. Conectar en `index.php` tras `bootAll()`.

**Fase M-3b:** Crear `navbar.php` para los módulos: watch, plex, fingerprint, theft-detection, magscan. Cada módulo obtiene su `position` y `order` correctos.

**Fase M-3c:** Eliminar las entradas modulares hardcodeadas de header.php (Plex Sync, Watch Folders, Fingerprint, Theft Detection de la sección Management). Ahora llegan vía NavbarBuilder.

**Qué NO tocamos:** Las entradas core (Dashboard, Servers, Users, Content, Bouquets, esqueleto de Management, Providers) quedan hardcodeadas. Son parte del núcleo, no de módulos.

### 7.2. Problema: las rutas no se cargan en web (se corregirá en M-1)

El actual `public/index.php` carga las rutas solo desde los archivos:
```php
require_once $routesDir . $scope . '.php';   // routes/admin.php
require_once $routesDir . 'api.php';         // routes/api.php
```

**Solución (M-1):** Tras cargar las rutas estáticas, llamar a `ModuleLoader::bootAll()`:

```php
// En public/index.php, tras el require de routes/{scope}.php:

$moduleLoader = new ModuleLoader();
$moduleLoader->loadAll();
$moduleLoader->bootAll(ServiceContainer::getInstance(), $router);
```

### 7.3. Cómo evitar conflictos

**Regla 1: Prefijo modular.**
Todas las rutas de un módulo se registran vía `$router->group($moduleName, ...)`, lo que añade el prefijo automáticamente.

**Regla 2: Los módulos no sobrescriben rutas core.**
El Router guarda las rutas en un assoc-array. El primer registro gana. Como `routes/admin.php` se carga ANTES de `ModuleLoader::bootAll()`, las rutas core tienen prioridad.

**Regla 3: Acciones de API con prefijo modular.**
Las rutas de API usan `group()`, el Router añade automáticamente `{prefix}_` al nombre del action:
```php
$router->group('watch', function($r) {
    $r->api('enable', ...);  // → action = "watch_enable"
});
```

**Regla 4: Detección de conflictos.**
`ModuleLoader::bootAll()` loguea un warning ante intento de re-registro:
```php
// En Router::get() / Router::post() — añadir la comprobación:
if (isset($this->getRoutes[$fullRoute])) {
    error_log("Router: duplicate route '{$fullRoute}' — keeping first registration");
    return $this;
}
```

### 7.4. Período de transición (duplicados)

Ahora las rutas de watch/plex/fingerprint/theft-detection/magscan están registradas en DOS sitios:
1. `public/routes/admin.php` (estáticamente)
2. `{Module}Module::registerRoutes()` (vía ModuleInterface)

**Estrategia:**
1. **M-1:** Activar `ModuleLoader::bootAll()` en la vía web. Los módulos registran rutas, pero los duplicados de `routes/admin.php` tienen prioridad (registrados primero).
2. **M-4:** Eliminar las rutas modulares de `routes/admin.php`. Ahora las rutas llegan solo de los módulos.

---

## 8. Aislamiento y dependencias

### 8.1. Reglas de interacción (de ARCHITECTURE.md §6.2 — sin cambios)

```
✅ El módulo PUEDE:
   - Depender de core/ vía constructor injection o llamadas static
   - Llamar a Services de domain/ (operaciones de negocio)
   - Llamar a Repositories de domain/ (lectura de datos)
   - Registrar rutas, comandos, crons, navbar-items
   - Suscribirse a eventos del núcleo
   - Tener sus views, assets, config

❌ El módulo NO PUEDE:
   - Modificar archivos de core/ o domain/
   - Depender de otro módulo (sin declaración explícita en module.json)
   - Sobrescribir rutas o servicios core
   - Acceder a la base de datos directamente (solo vía Repository)
```

### 8.2. Mecanismos de extensión

| Mecanismo | Cuándo usarlo | Ejemplo |
|----------|-------------------|--------|
| Rutas **Router** | El módulo añade sus páginas/API HTTP | `$router->group('watch', ...)` |
| Suscripciones **EventDispatcher** | El módulo reacciona a acciones del núcleo | `StreamStartedEvent` → grabación DVR |
| Registro en **ServiceContainer** | El módulo registra sus servicios | `$container->set('watch.service', ...)` |
| Comandos **CommandRegistry** | El módulo añade comandos/crons CLI | `$registry->register(new WatchCronJob())` |
| Entradas **Navbar** | El módulo extiende la navegación | `navbar.php` devuelve un array |
| Configuración **config.php** | El módulo tiene parámetros configurables | `config.php` devuelve el descriptor de campos |

### 8.3. Dependencias entre módulos

**Estado actual:** Ninguno de los 7 módulos depende de otro. `dependencies: []` en todos.

**Futuro:** Si el módulo A depende del módulo B, `ModuleLoader` debe cargar B antes que A.

Implementación:
```php
// En ModuleLoader::loadAll(), tras recolectar todos los módulos:
private function sortByDependencies() {
    // Topological sort: los módulos con dependencies vacías — primero
    $sorted = [];
    $visited = [];

    foreach ($this->modules as $name => $module) {
        $this->visit($name, $visited, $sorted);
    }

    $this->modules = $sorted;
}
```

**Restricción:** Dependencias circulares — Fatal Error con mensaje. No se admiten.

### 8.4. Dependencias directas prohibidas. Interacción entre módulos — solo vía el núcleo

```php
// ❌ PROHIBIDO — el módulo watch llama a plex directamente:
class WatchService {
    public function scan() {
        $plexService = new PlexService();  // ← violación del aislamiento
        $plexService->sync();
    }
}

// ✅ CORRECTO — vía evento del núcleo:
class WatchService {
    public function scanComplete($folderId) {
        EventDispatcher::publish('watch.scan_complete', ['folder_id' => $folderId]);
    }
}

// El módulo plex se suscribe:
class PlexModule implements ModuleInterface {
    public function getEventSubscribers(): array {
        return [
            'watch.scan_complete' => [PlexService::class, 'onWatchScanComplete'],
        ];
    }
}
```

---

## 9. Problemas y bugs detectados

### 9.1. EventDispatcher: `listen()` vs `subscribe()`

**Archivo:** `src/core/Module/ModuleLoader.php`, línea 141
**Bug:** `$dispatcher->listen($event, $handler)` — el método `listen()` no existe en `EventDispatcher`. El método correcto: `subscribe()`.

**Corrección:**
```php
// ModuleLoader.php, línea 141, ANTES:
$dispatcher->listen($event, $handler);

// DESPUÉS:
$dispatcher->subscribe($event, $handler);
```

**Alternativamente** (menos invasivo): añadir un alias en `EventDispatcher`:
```php
// EventDispatcher.php — añadir el método:
public static function listen($eventName, $listener) {
    return self::subscribe($eventName, $listener);
}
```

### 9.2. `events` no registrado en ServiceContainer

**Archivo:** `src/bootstrap.php` :: `populateContainer()`
**Bug:** `$container->has('events')` devolverá `false`, porque EventDispatcher no se registra en el contenedor en ningún sitio.

**Corrección:** En `populateContainer()` añadir:
```php
$container->set('events', EventDispatcher::class);
```

### 9.3. ModuleLoader no invocado en el contexto web

**Archivo:** `src/public/index.php`
**Descripción:** `ModuleLoader::bootAll()` solo se llama en `console.php` (CLI). En la vía web los módulos no se cargan.

**Corrección:** integrar en la sección 5 de `index.php` (tras la carga de routes).

### 9.4. Navbar — sin punto de extensión

**Archivo:** `src/public/Views/admin/header.php`
**Descripción:** Las entradas de menú modulares (watch, plex, fingerprint, etc.) están hardcodeadas en el HTML. No hay mecanismo de adición dinámica.

**Solución:** descrita en §6.3–6.10.

---

## 10. Plan de implementación por etapas

### Fase M-1: Activar la carga modular en web (Critical Bug Fixes)

**Objetivo:** Las rutas y servicios de los módulos se cargan en el contexto HTTP. Los event subscribers funcionan.

**Tareas:**

| # | Tarea | Archivo | Líneas | Dificultad |
|:-:|--------|------|--------|:---------:|
| 1 | Corregir `listen` → `subscribe` en `ModuleLoader::bootAll()` | `core/Module/ModuleLoader.php` L141 | 1 | Trivial |
| 2 | Registrar `events` en `populateContainer()` | `bootstrap.php` :: `populateContainer()` | 1 | Trivial |
| 3 | Llamar `ModuleLoader::loadAll() + bootAll()` en `index.php` | `public/index.php` sección 5 | ~10 | Media |
| 4 | Añadir protección de rutas duplicadas al Router | `core/Http/Router.php` :: `get/post/api` | ~6 | Fácil |

**Punto de integración en `index.php`:**

```php
// ─── Sección 5: Carga de rutas ───

$router = Router::getInstance();
$routesDir = __DIR__ . '/routes/';

// Rutas estáticas del núcleo (tienen prioridad)
if (file_exists($routesDir . $scope . '.php')) {
    require_once $routesDir . $scope . '.php';
}
if (file_exists($routesDir . 'api.php')) {
    require_once $routesDir . 'api.php';
}

// ── NUEVO: Carga de módulos (rutas + boot + eventos) ──
$moduleLoader = new ModuleLoader();
$moduleLoader->loadAll();
$moduleLoader->bootAll(ServiceContainer::getInstance(), $router);
```

**Comprobación:** Tras M-1 las rutas modulares y estáticas funcionan en paralelo. Duplicados — gana el core.

**Precondiciones:** Ninguna
**Riesgo:** Bajo. Es añadir, no reemplazar. Las rutas legacy quedan.

---

### Fase M-2: Filtrado por environment + orden por dependencias

**Objetivo:** `ModuleLoader` filtra los módulos por `environment` y los ordena por dependencias.

**Tareas:**

| # | Tarea | Archivo | Dificultad |
|:-:|--------|------|:---------:|
| 1 | Leer `environment` de `module.json` + filtrado | `ModuleLoader::loadAll()` | Fácil |
| 2 | Leer `dependencies` + topological sort | `ModuleLoader::sortByDependencies()` | Media |
| 3 | Actualizar `module.json` — añadir `environment: "main"` a los 7 módulos | `modules/*/module.json` | Trivial |

**Precondiciones:** M-1
**Riesgo:** Bajo. Retrocompatible — por defecto `environment = "main"`, `dependencies = []`.

---

### Fase M-3: Navegación dinámica (Navbar)

**Objetivo:** Los módulos registran entradas de menú vía `navbar.php`. Las entradas modulares hardcodeadas se eliminan de header.php. Los datos se cachean vía FileCache con auto-invalidación por modules_hash.

**Tareas:**

| # | Tarea | Archivo | Dificultad |
|:-:|--------|------|:---------:|
| 1 | Crear `NavbarPositions` — constantes de injection points | `core/Http/NavbarPositions.php` NUEVO | Trivial |
| 2 | Crear `NavbarBuilder` — build + cache + validate + render | `core/Http/NavbarBuilder.php` NUEVO | Alta |
| 3 | Añadir 4 injection points en header.php | `public/Views/admin/header.php` | Media |
| 4 | Añadir la llamada `NavbarBuilder::getItems()` en `index.php` | `public/index.php` | Fácil |
| 5 | Crear `navbar.php` para watch, plex, fingerprint, theft-detection, magscan | 5 archivos en `modules/*/` | Media |
| 6 | Eliminar las entradas modulares hardcodeadas de header.php | `public/Views/admin/header.php` | Media |
| 7 | Añadir `NavbarBuilder::invalidate()` en `ModuleManagerController::apiToggle()` | `public/Controllers/Admin/ModuleManagerController.php` | Fácil |

**Subfases:**
- **M-3a:** Crear `NavbarPositions.php`, `NavbarBuilder.php`. Añadir injection points en header.php. Conectar `getItems()` en index.php. En esta etapa los injection points están vacíos — no hay navbar.php en los módulos.
- **M-3b:** Crear `navbar.php` para 5 módulos (watch, plex, fingerprint, theft-detection, magscan). Comprobar el render.
- **M-3c:** Eliminar las entradas modulares hardcodeadas de header.php. Comprobar la navegación.

**Precondiciones:** M-1
**Riesgo:** Medio. Toca la UI. Las subfases permiten revertir M-3c por separado ante una regresión.

---

### Fase M-4: Consolidación de rutas — eliminación de duplicados

**Objetivo:** Las rutas modulares llegan SOLO desde `ModuleInterface::registerRoutes()`. Los duplicados de `routes/admin.php` eliminados.

**Tareas:**

| # | Tarea | Archivo | Dificultad |
|:-:|--------|------|:---------:|
| 1 | Eliminar las rutas watch/* de `routes/admin.php` | `public/routes/admin.php` | Fácil |
| 2 | Eliminar las rutas plex/* de `routes/admin.php` | `public/routes/admin.php` | Fácil |
| 3 | Eliminar las rutas fingerprint, theft_detection, magscan de `routes/admin.php` | `public/routes/admin.php` | Fácil |
| 4 | Comprobar que todas las páginas modulares son accesibles vía Router | Test manual | Media |

**Precondiciones:** M-1, M-3 (el navbar debe funcionar antes de eliminar las rutas estáticas)
**Riesgo:** Medio. Con un error — las páginas modulares darán 404. Solución: feature flag.

---

### Fase M-5: Sistema de configuración de módulos

**Objetivo:** Los módulos declaran su configuración en `config.php`. La UI se genera automáticamente.

**Tareas:**

| # | Tarea | Archivo | Dificultad |
|:-:|--------|------|:---------:|
| 1 | Crear `ModuleManagerController` | `public/Controllers/Admin/ModuleManagerController.php` | Media |
| 2 | Crear la view `modules/list.php` | `public/Views/admin/modules/list.php` | Media |
| 3 | Crear la view `modules/settings_form.php` (auto-render) | `public/Views/admin/modules/settings_form.php` | Media |
| 4 | Rutas: `modules`, `settings/module/{name}`, API `module_toggle` | `public/routes/admin.php` | Fácil |
| 5 | `config.php` para los módulos watch, plex | `modules/watch/config.php`, `modules/plex/config.php` | Media |
| 6 | Actualizar `module.json` — añadir `has_settings: true` | `modules/*/module.json` | Trivial |

**Precondiciones:** M-1
**Riesgo:** Bajo. Funcionalidad nueva, no toca la existente.

---

### Fase M-6: Aislamiento modular completo — migración de las dependencias hardcodeadas restantes

**Objetivo:** El núcleo no contiene referencias directas a clases modulares. Extensión event-based para las entradas que hoy están hardcodeadas en el núcleo.

**Tareas:**

| # | Tarea | Archivo | Dificultad |
|:-:|--------|------|:---------:|
| 1 | Reemplazar las llamadas `TmdbService::` en el replacement de admin_api por evento | `domain/Vod/MovieService.php` | Media |
| 2 | Auditoría: `grep -rn 'Plex\|Watch\|Tmdb\|Ministra\|Fingerprint' src/domain/ src/core/` | Todo `domain/` y `core/` | Media |
| 3 | Migrar las llamadas directas encontradas al modelo de eventos | Según el resultado de la auditoría | Alta |

**Precondiciones:** M-1, M-3
**Riesgo:** Alto. Puede tocar la lógica de negocio. Requiere testeo cuidadoso.

---

### Tabla resumen de fases

| Fase | Nombre | Depende de | Archivos cambiados | Riesgo |
|:----:|----------|:----------:|:---------------:|:----:|
| **M-1** | Activar la carga web de módulos | — | 4 | Bajo |
| **M-2** | Environment + dependencies | M-1 | 8 | Bajo |
| **M-3** | Navegación dinámica (NavbarBuilder + FileCache) | M-1 | 10 | Medio |
| **M-4** | Eliminación de rutas duplicadas | M-1, M-3 | 1 | Medio |
| **M-5** | Sistema de configuración | M-1 | 6+ | Bajo |
| **M-6** | Aislamiento completo núcleo↔módulos | M-1, M-3 | Según auditoría | Alto |

**Orden recomendado:** M-1 → M-2 → M-5 → M-3 → M-4 → M-6

Justificación: M-5 (configuración) no depende del navbar y da beneficio inmediato. M-3 (navbar) requiere más trabajo de UI. M-6 — el último, el más arriesgado.

---

## 11. Riesgos

### 11.1. Riesgos técnicos

| Riesgo | Probabilidad | Impacto | Mitigación |
|------|:----------:|:-------:|-----------|
| Las rutas modulares entran en conflicto con core | Baja | Alto | Detección de duplicados en el Router (M-1.4) |
| `bootAll()` ralentiza cada petición | Media | Medio | `ModuleLoader` cachea la lista de módulos en FileCache |
| El render del navbar rompe el layout | Media | Alto | Validación estricta de items en el build + try-catch por módulo |
| Desync de la caché del navbar | Baja | Medio | Auto-invalidación modules_hash + TTL safety net (3600s) |
| Un navbar.php inválido rompe toda la navegación | Media | Alto | Try-catch por módulo + validateItem() skip + error_log |
| Conflicto de ids entre módulos | Baja | Medio | Comprobación de unicidad global de ids en el build |
| `EventDispatcher` static = no testeable | Baja | Bajo | Suficiente para las necesidades actuales. Refactor — futuro |
| Un módulo con error rompe todo el bootstrap | Media | Alto | try-catch en `ModuleLoader::bootAll()` por módulo |

### 11.2. Riesgos organizativos

| Riesgo | Mitigación |
|------|-----------|
| Los contribuyentes seguirán añadiendo rutas en `routes/admin.php` en lugar de `registerRoutes()` | Documentación + comprobación CI: las rutas nuevas de módulos → solo vía ModuleInterface |
| Módulos de terceros violan el contrato | `ModuleLoader` comprueba `instanceof ModuleInterface` (ya existe) |
| Módulos con `install()` crean tablas pero `uninstall()` no limpia | Recomendación: `uninstall()` debe reflejar `install()`. Pero no se fuerza |

### 11.3. Debilidad: EventDispatcher static

`EventDispatcher` es completamente static → no se puede inyectar por constructor → no se puede mockear en tests → viola §2.3 de ARCHITECTURE.md (strict constructor injection). **Es un compromiso consciente** — refactorizar EventDispatcher a instance-based requeriría cambiar todos los suscriptores. Se aplaza hasta que exista una necesidad real de testear eventos.

### 11.4. Debilidad: falta de compatibilidad por versiones

El campo `requires_core` de module.json **no se comprueba en ningún sitio**. `ModuleLoader` lo lee, pero no lo valida. Mientras todos los módulos estén en el mismo repositorio — no es problema. Se volverá problema al aparecer módulos de terceros/comerciales.

---

## Apéndice A: Checklist para crear un módulo nuevo

```
1. [ ] Crear el directorio src/modules/{name}/
2. [ ] Crear module.json (name, version, description, requires_core)
3. [ ] Crear {Name}Module.php implements ModuleInterface
4. [ ] Implementar getName(), getVersion()
5. [ ] boot() — registrar servicios en ServiceContainer (si hay)
6. [ ] registerRoutes() — registrar rutas GET/POST/API
7. [ ] registerCommands() — registrar comandos/crons CLI (si hay)
8. [ ] getEventSubscribers() — suscribirse a eventos del núcleo (si hace falta)
9. [ ] install() — crear tablas/datos iniciales (si hace falta)
10. [ ] uninstall() — eliminar tablas/datos (espejo de install)
11. [ ] (Opcional) Crear navbar.php para la navegación
12. [ ] (Opcional) Crear config.php para la configuración
13. [ ] (Opcional) Crear views/ para las plantillas HTML
14. [ ] Probar:
        - php -l modules/{name}/{Name}Module.php
        - El módulo se carga (comprobar /modules en el admin)
        - Las páginas son accesibles por sus rutas
        - Desactivar vía modules.php no rompe el sistema
        - Eliminar el directorio no rompe el sistema
```

## Apéndice B: Ejemplo de módulo mínimo

```php
// modules/example/module.json
{
    "name": "example",
    "description": "Example module for demonstration",
    "version": "1.0.0",
    "requires_core": ">=2.0",
    "environment": "main",
    "has_settings": false,
    "has_navbar": true
}

// modules/example/ExampleModule.php
<?php
class ExampleModule implements ModuleInterface {

    public function getName(): string { return 'example'; }
    public function getVersion(): string { return '1.0.0'; }

    public function boot(ServiceContainer $container): void {
        // Nada — no hay servicios
    }

    public function registerRoutes(Router $router): void {
        $router->get('example', [ExampleModule::class, 'page'], [
            'permission' => ['adv', 'settings'],
        ]);
    }

    public function registerCommands(CommandRegistry $registry): void {}
    public function getEventSubscribers(): array { return []; }
    public function install(): void {}
    public function uninstall(): void {}

    // Inline handler (para módulos simples sin Controller aparte)
    public static function page() {
        echo '<h3>Example Module</h3><p>It works!</p>';
    }
}

// modules/example/navbar.php
<?php
return [
    [
        'id'         => 'example.page',
        'label'      => 'Example',
        'icon'       => 'fas fa-puzzle-piece',
        'url'        => 'example',
        'permission' => ['adv', 'settings'],
        'position'   => 'top',       // NavbarPositions::TOP
        'order'      => 200,
    ],
];
```
