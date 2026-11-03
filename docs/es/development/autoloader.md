# Autoload — Registro de clases

UyPanel usa su propio autoload (`src/autoload.php`) en lugar de Composer.
Las clases se detectan **automáticamente** — no hace falta registro manual.

---

## Cómo funciona

```
Petición → se incluye autoload.php
              │
              ├── ¿Existe el archivo de caché?
              │       SÍ  → cargar caché igbinary → búsqueda O(1)
              │       NO  → warmCache(): escanear todos los directorios vía token_get_all()
              │                          → construir el mapa ClassName → filePath
              │                          → guardar en tmp/cache/autoload_map
              │
              └── PHP pide una clase
                      │
                      ├── 1. Mapa explícito (addClass())
                      ├── 2. Caché (de archivo o de una búsqueda previa)
                      └── 3. Búsqueda viva por directorios (fallback, se cachea)
```

## Añadir una clase nueva

**Simplemente crea el archivo.** Eso es todo.

Coloca el archivo PHP en cualquier directorio registrado (o su subdirectorio):

| Directorio | Propósito |
|-----------|---------|
| `src/core/` | Núcleo del framework (Database, Cache, Auth, Http, Process, etc.) |
| `src/domain/` | Lógica de negocio (Services, Repositories) |
| `src/infrastructure/` | Adaptadores externos (DatabaseFactory, CacheReader, Redis) |
| `src/streaming/` | Subsistema de streaming (Auth, Delivery, Codec, Health) |
| `src/modules/` | Módulos opcionales (Plex, Watch, TMDB, Ministra, etc.) |
| `src/public/` | Controladores y Views |
| `src/includes/` | Código legacy |
| `src/includes/libs/` | Librerías de terceros |

### Ejemplo

```php
// src/domain/Billing/InvoiceService.php
class InvoiceService {
    public static function generate($userId) { ... }
}
```

Tras crear el archivo **elimina la caché** para que el autoload detecte las clases nuevas:

```bash
rm -f /home/uypanel/tmp/cache/autoload_map
```

En la siguiente petición `warmCache()` se lanzará automáticamente, encontrará `InvoiceService` y la cacheará.

## Invalidación de la caché

El archivo de caché `tmp/cache/autoload_map` es un archivo binario (formato igbinary).
Hay que eliminarlo cuando:

- Se añade un archivo nuevo con una clase
- Se renombra o mueve un archivo con una clase
- Se elimina un archivo con una clase

```bash
# Eliminar manualmente
rm -f /home/uypanel/tmp/cache/autoload_map

# O vía PHP
XC_Autoloader::clearCache();
```

> **Nota:** Si se pide una clase que no está en la caché, el autoload hace una búsqueda viva por los directorios y cachea el resultado. Por eso la caché solo hay que resetear al mover/renombrar/eliminar — las clases nuevas se encuentran vía el fallback.

## Registro manual (raro)

Para casos especiales donde un archivo contiene varias clases o el nombre del archivo no coincide con el de la clase:

```php
XC_Autoloader::addClass('DropboxClient', '/home/uypanel/includes/libs/Dropbox.php');
XC_Autoloader::addClass('DropboxException', '/home/uypanel/includes/libs/Dropbox.php');
```

Esto hace falta sobre todo para librerías legacy con varias clases en un archivo (p. ej. `iptables.php`, `m3u.php`).

## Añadir un directorio nuevo

Edita `registerDirectories()` en `src/autoload.php`:

```php
private static function registerDirectories() {
    $base = self::$basePath;

    self::addDirectory($base . 'includes');
    self::addDirectory($base . 'includes/libs');
    self::addDirectory($base . 'core');
    self::addDirectory($base . 'domain');
    self::addDirectory($base . 'infrastructure');
    self::addDirectory($base . 'streaming');
    self::addDirectory($base . 'modules');
    self::addDirectory($base . 'public');

    // Añade el directorio nuevo:
    self::addDirectory($base . 'my_new_dir');
}
```

Luego elimina la caché.

## Reglas de nombrado

| Regla | Ejemplo |
|---------|--------|
| El nombre del archivo **debe** coincidir con el de la clase | `InvoiceService.php` → `class InvoiceService` |
| Una clase por archivo (recomendado) | Los archivos con varias clases requieren `addClass()` |
| PascalCase | `StreamService`, `DatabaseHandler` |
| Sin namespace | `class StreamService { }` — sin la palabra clave `namespace` |
| Sin `declare(strict_types=1)` | Convención del proyecto |

## Duplicación de nombres de clases

Si dos archivos definen el mismo nombre de clase, **gana el primero encontrado** según el orden de escaneo de directorios. Esto es frágil — evita los duplicados:

```
✗  domain/Stream/StreamService.php  ← ya existe
✓  modules/plex/PlexController.php                    ← único
```

## Depuración

```php
// Todos los directorios registrados
print_r(XC_Autoloader::getDirectories());

// Mapa explícito de clases
print_r(XC_Autoloader::getClassMap());

// Re-escaneo completo forzado
XC_Autoloader::clearCache();
XC_Autoloader::warmCache();
```
