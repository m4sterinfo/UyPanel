# Sistema de módulos

## Resumen

Un módulo es un directorio aislado en `src/modules/` con un contrato conocido. Eliminar un módulo **no rompe el sistema** — sigue funcionando, degradando funcionalidad.

### Arquitectura

```
modules/
├── my-module/
│   ├── module.json            # Metadatos (name, description, version, requires_core)
│   ├── MyModule.php           # Fuente de verdad (implements ModuleInterface)
│   ├── MyService.php          # Servicios del módulo
│   ├── MyController.php       # Controlador (si tiene páginas)
│   ├── MyCron.php             # Lógica del cron (si tiene)
│   ├── MyCronJob.php          # Envoltura CLI del cron (implements CommandInterface)
│   ├── views/                 # Plantillas de páginas
│   │   ├── my_page.php
│   │   └── my_page_scripts.php
│   └── migrations/            # Migraciones SQL del módulo (si tiene)
│       └── 001_create_table.sql
```

### Principios

| Regla | Descripción |
|---------|----------|
| **PHP — fuente de verdad** | Todo el comportamiento se define en la clase del módulo, no en el JSON |
| **module.json — solo metadatos** | `name`, `description`, `version`, `requires_core` |
| **Auto-detección** | `ModuleLoader` escanea `modules/*/module.json` — no hace falta registro en el config |
| **Aislamiento** | El módulo depende de `core/` y `domain/`, pero NUNCA de otros módulos |
| **Degradación elegante** | Eliminar el directorio del módulo no provoca errores |
| **Sin dependencias inversas** | El núcleo (`core/`) no sabe que los módulos existen |
| **DI vía contenedor** | Los servicios se registran en `boot()`, no vía globals |
| **Registro explícito de comandos** | El módulo registra sus comandos en `registerCommands()`, sin filesystem scanning |

---

## Paso 1. Crear el directorio

```bash
mkdir -p src/modules/my-module
```

El nombre del directorio = nombre del módulo. Usa kebab-case: `my-module`, `theft-detection`.

---

## Paso 2. Crear el manifest `module.json`

```json
{
    "name": "my-module",
    "description": "Breve descripción del módulo",
    "version": "1.0.0",
    "requires_core": ">=2.0"
}
```

### Campos del manifest

| Campo | Tipo | Obligatorio | Descripción |
|------|-----|:---:|----------|
| `name` | `string` | ✅ | Nombre único del módulo (coincide con el directorio) |
| `description` | `string` | ⛔ | Descripción breve legible del módulo |
| `version` | `string` | ✅ | Versión en formato semver (`1.0.0`) |
| `requires_core` | `string` | ✅ | Versión mínima del núcleo (`>=2.0`) |

> **Importante:** `module.json` contiene solo metadatos. Crons, comandos, rutas, eventos, páginas — todo se define en la clase PHP del módulo.

---

## Paso 3. Crear la clase del módulo

Archivo `src/modules/my-module/MyModule.php`:

```php
<?php

class MyModule implements ModuleInterface {

    public function getName(): string {
        return 'my-module';
    }

    public function getVersion(): string {
        return '1.0.0';
    }

    public function boot(ServiceContainer $container): void {
        $container->set('my-module.service', 'MyService');
    }

    public function registerRoutes(Router $router): void {
        $router->get('my-module', [MyController::class, 'index'], [
            'permission' => ['adv', 'my_module'],
        ]);
        $router->api('my_action', [MyController::class, 'apiAction'], [
            'permission' => ['adv', 'my_module'],
        ]);
    }

    public function registerCommands(CommandRegistry $registry): void {
        $registry->register(new MyCronJob());
    }

    public function getEventSubscribers(): array {
        return [];
    }

    public function install(): void {
        // Creación de tablas, datos iniciales, etc.
    }

    public function uninstall(): void {
        // Limpieza de los datos del módulo
    }
}
```

### Contrato `ModuleInterface`

| Método | Descripción |
|-------|----------|
| `getName(): string` | Nombre único (coincide con el directorio) |
| `getVersion(): string` | Versión semver |
| `boot(ServiceContainer)` | Registro de servicios. Se llama una vez al cargar |
| `registerRoutes(Router)` | Rutas HTTP y acciones de API |
| `registerCommands(CommandRegistry)` | Registro explícito de comandos CLI y tareas cron |
| `getEventSubscribers(): array` | Suscripciones a eventos del núcleo |
| `install(): void` | Instalación del módulo (migraciones, datos iniciales) |
| `uninstall(): void` | Eliminación de los datos del módulo |

---

## Paso 4. Registro automático

**No hace falta registrar el módulo en el config.** `ModuleLoader` detecta automáticamente todos los módulos desde `modules/*/module.json`.

Para **desactivar** un módulo — añade en `src/config/modules.php`:

```php
return [
    'my-module' => ['enabled' => false],
];
```

`config/modules.php` solo contiene overrides. Si el archivo está vacío o no existe — se cargan todos los módulos detectados.

### Cómo funciona la carga

1. `ModuleLoader::loadAll()` escanea `modules/*/module.json`
2. Comprueba los overrides en `config/modules.php`
3. Determina la clase por convención: `my-module` → `MyModule` (kebab-case → PascalCase + Module)
4. Crea la instancia del módulo

En el contexto web (bootstrap.php):
- `bootAll($container, $router)` → llama a `boot()`, `registerRoutes()`, `getEventSubscribers()`

En el contexto CLI (console.php):
- `registerAllCommands($registry)` → llama a `registerCommands()` de cada módulo

---

## Paso 5. Añadir una tarea cron (opcional)

### 5.1 Clase cron (lógica) — en el módulo

Archivo `src/modules/my-module/MyCron.php`:

```php
<?php

class MyCron {

    public static function run(): void {
        $items = Database::query("SELECT * FROM my_table WHERE status = 'pending'");
        foreach ($items as $item) {
            self::processItem($item);
        }
    }

    private static function processItem(array $item): void {
        // Procesado del elemento
    }
}
```

### 5.2 Envoltura CronJob — en el directorio del módulo

Archivo `src/modules/my-module/MyCronJob.php`:

```php
<?php

require_once MAIN_HOME . 'cli/CronTrait.php';

class MyCronJob implements CommandInterface {
    use CronTrait;

    public function getName(): string {
        return 'cron:my_task';
    }

    public function getDescription(): string {
        return 'Cron: descripción de la tarea';
    }

    public function execute(array $rArgs): int {
        if (!$this->assertRunAsXcVm()) {
            return 1;
        }

        require INCLUDES_PATH . 'admin.php';
        require_once __DIR__ . '/MyCron.php';

        $this->initCron('UyPanel[MyTask]');

        MyCron::run();

        return 0;
    }
}
```

### 5.3 Registro en el módulo

El comando se registra **explícitamente** en `registerCommands()`:

```php
public function registerCommands(CommandRegistry $registry): void {
    $registry->register(new MyCronJob());
}
```

> **Importante:** No se usa filesystem scanning de módulos. Cada módulo conoce sus comandos y los registra en `registerCommands()`.

### 5.4 Añadir al crontab

En `src/cli/Commands/StartupCommand.php`, método `installCrontab()`, añade la entrada:

```php
$rCrons[] = '*/5 * * * * ' . PHP_BIN . ' ' . MAIN_HOME . 'console.php cron:my_task # UyPanel';
```

---

## Paso 6. Configuración del build (Makefile)

El directorio `modules/` **no** está en `LB_DIRS` — todos los módulos solo están presentes en los builds MAIN por defecto. Los archivos del módulo (crons, comandos, vistas) quedan automáticamente excluidos de los builds LoadBalancer.

---

## Ejemplos completos

### Módulo mínimo (sin crons, sin rutas)

Ejemplo: `fingerprint`, `theft-detection`, `magscan`.

```
modules/my-module/
├── module.json
└── MyModule.php
```

`module.json`:
```json
{
    "name": "my-module",
    "version": "1.0.0",
    "requires_core": ">=2.0"
}
```

`MyModule.php` — implementa todos los métodos de `ModuleInterface`. Los métodos sin comportamiento quedan vacíos.

### Módulo completo (servicios + rutas + comandos + eventos)

Ejemplo: `plex`, `watch`.

```
modules/my-module/
├── module.json
├── MyModule.php
├── MyService.php
├── MyRepository.php
├── MyController.php
├── MyCron.php
├── MyCronJob.php
└── views/
    ├── my_page.php
    └── my_page_scripts.php
```

Todos los archivos del módulo viven dentro de su directorio. Las envolturas CronJob se registran vía `registerCommands()`.

### Módulo con eventos

```php
public function getEventSubscribers(): array {
    return [
        'stream.started'  => [MyHandler::class, 'onStreamStarted'],
        'stream.stopped'  => [MyHandler::class, 'onStreamStopped'],
        'user.connected'  => [MyHandler::class, 'onUserConnected'],
    ];
}
```

---

## Checklist para añadir un módulo

- [ ] Crear el directorio `src/modules/<name>/`
- [ ] Crear `module.json` (`name`, `version`, `requires_core`)
- [ ] Crear `<Name>Module.php` (implements `ModuleInterface`)
- [ ] (Si hay crons) Crear `<Name>Cron.php` + `<Name>CronJob.php` en el módulo
- [ ] (Si hay crons) Registrarlos en `registerCommands()`
- [ ] (Si hay crons) Añadirlos al crontab vía `StartupCommand`
- [ ] Comprobar: `php -l src/modules/<name>/<Name>Module.php`
- [ ] Comprobar: el módulo se carga con `php console.php --list`
- [ ] Comprobar: eliminar el directorio del módulo no provoca fatal error

---

## Eventos del núcleo disponibles

| Evento | Descripción | Datos |
|---------|----------|--------|
| `stream.started` | Stream arrancado | `['stream_id' => int]` |
| `stream.stopped` | Stream detenido | `['stream_id' => int]` |
| `user.connected` | Usuario conectado | `['user_id' => int, 'stream_id' => int]` |
| `cache.rebuilt` | Caché reconstruida | `[]` |

---

## FAQ

**P: ¿Cómo desactivo un módulo?**
R: En `src/config/modules.php` añade `'module-name' => ['enabled' => false]`.

**P: ¿Hay que registrar el módulo en el config?**
R: No. `ModuleLoader` detecta automáticamente todos los módulos desde `modules/*/module.json`. El config solo sirve para desactivar.

**P: Mi módulo depende de otro módulo — ¿cómo?**
R: **No permitas dependencias entre módulos.** Un módulo solo depende de `core/` y `domain/`. Si necesitas funcionalidad común — sácala al núcleo.

**P: ¿Puedo usar `$db` directamente?**
R: Técnicamente sí (vía `global $db`), pero lo arquitectónicamente correcto es usar `Database` vía `ServiceContainer` o Repository.

**P: ¿Cómo accede el módulo a la configuración?**
R: Vía `SettingsManager::getAll()['my_key']`. Las claves de configuración de los módulos se guardan en la tabla común `settings`.

**P: Mi módulo solo hace falta en MAIN — ¿qué hago?**
R: Todos los módulos ya son MAIN-only por defecto — `modules/` no está en `LB_DIRS`.
