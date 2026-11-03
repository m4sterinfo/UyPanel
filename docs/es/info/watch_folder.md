# 🎬 Watch Folder — Importación automática de medios

Watch Folder es el sistema de importación automática de contenido. Monitorea directorios locales (o remotos rclone) en busca de nuevos archivos de video, parsea los nombres para extraer metadatos (título, año, temporada, episodio), consulta TMDB para obtener carátulas y descripciones, y crea las entradas de películas/series en la base de datos — sin intervención manual.

---

## Cómo funciona

```
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│  Watch Folder    │────▶│   WatchCron       │────▶│  WatchItem       │
│  (directorio en  │     │  escanea archivos │     │  parsea el nombre│
│  disco / rclone) │     │  nuevos, filtra   │     │  consulta TMDB   │
│                  │     │  los ya importados│     │  crea la entrada │
└──────────────────┘     └──────────────────┘     └──────────────────┘
                                                          │
                                                          ▼
                                                   ┌──────────────────┐
                                                   │  Actualización   │
                                                   │  de bouquets     │
                                                   │  (auto-vinculo)  │
                                                   └──────────────────┘
```

### Paso a paso

1. **El admin crea un Watch Folder** en el panel admin (Watch Folder → Add) o vía API (`create_watch_folder`). La configuración incluye: ruta del directorio, tipo de contenido (movie/series), categoría destino, bouquets, parser y servidor asignado.
2. **El cron `cron:watch`** se ejecuta periódicamente (el intervalo lo controla `scan_offset` — segundos entre escaneos). Selecciona de `watch_folders` las carpetas activas cuyo `last_run` superó el offset.
3. **Detección de archivos** — el cron usa `find` para directorios locales o `rclone lsjson` para almacenamientos en la nube/remotos. Los archivos se filtran por las extensiones permitidas (por defecto: `mp4, mkv, avi, mpg, flv, 3gp, m4v, wmv, mov, ts`). Los archivos ya presentes en `streams.stream_source` se omiten.
4. **Comprobación de estabilidad** — los archivos modificados hace menos de 30 segundos se omiten (para no importar archivos a medio descargar).
5. **Procesado en paralelo** — cada archivo nuevo se envía al comando `watch_item` (vía `shell_exec`); hasta `thread_count` elementos se lanzan en paralelo vía `Multithread`.
6. **WatchItem** parsea el nombre del archivo con PTN o guessit (ver la documentación de parsers más abajo), consulta los metadatos vía la API de TMDB e inserta la entrada en `streams` (películas) o en `streams_series` + `streams_episodes` (series).
7. **Vinculación a bouquets** — los elementos importados se añaden automáticamente a los bouquets configurados.

---

## Configuración

### Ajustes de Watch Folder (por carpeta)

| Ajuste | Descripción |
|-----------|----------|
| `directory` | Ruta local a escanear (p. ej., `/mnt/media/movies/`) |
| `rclone_dir` | Ruta del remoto rclone (alternativa al directorio local) |
| `type` | Tipo de contenido: `movie` o `series` |
| `server_id` | Servidor donde se ejecuta el escaneo |
| `category_id` | Categoría destino para el contenido importado |
| `bouquets` | Vinculación automática a estos bouquets |
| `fb_category_id` | Categoría de fallback (si el mapeo de géneros TMDB falla) |
| `fb_bouquets` | Bouquets de fallback |
| `allowed_extensions` | Extensiones de archivo a escanear (vacío = lista por defecto) |
| `language` | Idioma preferido de TMDB para los metadatos |
| `active` | Activar/desactivar esta carpeta |

### Opciones booleanas

| Opción | Descripción |
|-------|----------|
| `disable_tmdb` | Omitir la consulta a TMDB — importar solo con el nombre parseado |
| `ignore_no_match` | Importar aunque TMDB no devuelva resultado |
| `auto_subtitles` | Detectar automáticamente `.srt`, `.sub`, `.sbv` junto al video |
| `fallback_title` | Usar el nombre de la carpeta como título si el parser no lo extrajo |
| `read_native` | Leer el título original de TMDB |
| `movie_symlink` | Crear symlinks en lugar de enlazar la ruta original |
| `auto_encode` | Recodificar automáticamente el contenido importado |
| `auto_upgrade` | Reemplazar la versión existente de peor calidad si coincide el TMDB ID |
| `duplicate_tmdb` | Permitir importaciones múltiples con el mismo TMDB ID |
| `ffprobe_input` | Ejecutar ffprobe sobre el archivo para extraer metadatos del códec |
| `extract_metadata` | Extraer metadatos adicionales del archivo |

### Ajustes globales

| Ajuste | Dónde | Descripción |
|-----------|-----|----------|
| `tmdb_api_key` | Admin → Ajustes | **Obligatorio** — clave de la API TMDB. Sin ella Watch no arranca |
| `fallback_parser` | Admin → Ajustes | Parser a usar cuando el principal falla |
| `alternative_titles` | Admin → Ajustes | Buscar títulos alternativos en TMDB |
| `max_genres` | Admin → Ajustes | Máximo de géneros a vincular al elemento |

---

## Panel admin y API

### Páginas del panel admin

| Página | Descripción |
|----------|----------|
| Watch Folder → List | Lista de todas las carpetas configuradas con su estado |
| Watch Folder → Add | Creación/edición de watch folder |
| Watch Folder → Settings | Ajustes globales (parser, TMDB) |
| Watch Folder → Logs | Consulta de logs de escaneo y errores |

### Acciones de la API

| Acción | Descripción |
|----------|----------|
| `get_watch_folders` | Lista de todos los watch folders |
| `get_watch_folder` | Obtener una carpeta por ID |
| `create_watch_folder` | Crear una watch folder nueva |
| `edit_watch_folder` | Actualizar una existente |
| `delete_watch_folder` | Eliminar una watch folder |
| `reload_watch_folder` | Re-escaneo forzado |
| `enable_watch` | Activar todos los watch folders |
| `disable_watch` | Desactivar todos los watch folders |
| `kill_watch` | Matar todos los procesos watch en marcha |

### CLI

```bash
# Ejecución normal del cron (normalmente automática)
sudo -u uypanel /home/uypanel/bin/php/bin/php /home/uypanel/console.php cron:watch

# Escaneo forzado de una carpeta concreta (por ID)
sudo -u uypanel /home/uypanel/bin/php/bin/php /home/uypanel/console.php cron:watch 5
```

---

## Parsers

Hay dos parsers de nombres de archivos disponibles. El parser extrae metadatos estructurados (título, año, temporada, episodio, resolución, códec) del nombre del archivo de video.

### Elección del parser

| Parser | Mejor para |
|--------|-------------------|
| **PTN** | Nombres simples con espacios: `San Andreas 2015 720p.mkv` |
| **guessit** | Nombres con puntos: `The.Matrix.1999.1080p.BluRay.mkv` |

El parser principal se configura por cada watch folder. El ajuste global `fallback_parser` se usa cuando el principal no encuentra coincidencia.

---

## 1️⃣ PTN Parser

El parser PTN soporta el parseo de archivos de películas y series con nombres típicos.

### 🎥 Películas

| Ejemplo de nombre | Parseo |
|------------------|-------|
| `San Andreas 2015 720p WEB-DL x264 AAC-JYK.mkv` | Título: *San Andreas*, Año: 2015, Resolución: 720p, Video: x264, Audio: AAC, Grupo: JYK |
| `The Martian 2015 540p HDRip KORSUB x264 AAC2 0-FGT.mp4` | Título: *The Martian*, Año: 2015, Resolución: 540p, Video: x264, Audio: AAC2.0, Grupo: FGT |

### 📺 Series

| Ejemplo de nombre | Parseo |
|------------------|-------|
| `friends.s02e01.720p.bluray-sujaidr.mkv` | Título: *Friends*, Temporada: 2, Episodio: 1, Resolución: 720p, Formato: bluray, Grupo: sujaidr |
| `Mr Robot S01E05 HDTV x264-KILLERS[ettv].mp4` | Título: *Mr Robot*, Temporada: 1, Episodio: 5, Formato: HDTV, Video: x264, Grupo: KILLERS |

---

## 2️⃣ guessit Parser

Guessit soporta formatos complejos, incluyendo separadores por puntos y títulos multiidioma.

### 🎥 Películas

| Ejemplo de nombre | Parseo |
|------------------|-------|
| `The.Matrix.1999.1080p.BluRay.x264.DTS-FGT.mkv` | Título: *The Matrix*, Año: 1999, Resolución: 1080p, Video: x264, Audio: DTS, Grupo: FGT |
| `Inception.2010.720p.BRRip.x264.AAC-ETRG.mkv` | Título: *Inception*, Año: 2010, Resolución: 720p, Video: x264, Audio: AAC, Grupo: ETRG |

### 📺 Series

| Ejemplo de nombre | Parseo |
|------------------|-------|
| `Breaking.Bad.S03E07.720p.BluRay.x264-REWARD.mkv` | Título: *Breaking Bad*, Temporada: 3, Episodio: 7, Resolución: 720p, Video: x264, Grupo: REWARD |
| `Game.of.Thrones.S05E09.1080p.WEB-DL.DD5.1.H.264-NTb.mkv` | Título: *Game of Thrones*, Temporada: 5, Episodio: 9, Resolución: 1080p, Video: H.264, Audio: DD5.1, Grupo: NTb |

---

### 🔄 Fallback al nombre de la carpeta

Si el nombre del archivo no contiene el título de la serie, activa la opción **Fallback to Folder Name**:

| Ejemplo de ruta | Parseo |
|-------------|-------|
| `/path/to/Show Name/S01E01 720p WEB-DL.mkv` | Título: *Show Name*, Temporada: 1, Episodio: 1 |
| `/path/to/Show.Name/S01E01.720p.WEB-DL.mkv` | Título: *Show Name*, Temporada: 1, Episodio: 1 |

#### 🗂 Separación por carpetas de temporadas

Si quieres organizar los episodios por carpetas:

| Ejemplo de ruta | Parseo |
|-------------|-------|
| `/path/to/Show Name/Season 01/Show Name S01E01 720p WEB-DL.mkv` | Título: *Show Name*, Temporada: 1, Episodio: 1 |
| `/path/to/Show.Name/Season.01/Show.Name.S01E01.720p.WEB-DL.mkv` | Título: *Show Name*, Temporada: 1, Episodio: 1 |

---

### 🌐 Idiomas RTL

Para series en idiomas RTL (derecha a izquierda):

- El nombre del archivo **no debe contener el título de la serie**  
- Activa la opción **Fallback to Folder Name**  

| Ejemplo de ruta | Parseo |
|-------------|-------|
| `/path/to/Show Name/S01E01 (year).mp4` | Título: *Show Name*, Temporada: 1, Episodio: 1, Año: `year` |
| `/path/to/Show Name/Season 01/S01E01 (year).mp4` | Título: *Season 01*, Temporada: 1, Episodio: 1, Año: `year` |

> ⚠️ Importante: para idiomas RTL el título de la serie se toma solo del nombre de la carpeta.

---

### ✅ Resumen

- **PTN Parser** — nombres de archivo simples, formatos locales  
- **guessit Parser** — soporte de separadores por puntos, títulos multiidioma, fallback al nombre de la carpeta  
- **Idiomas RTL** — usar obligatoriamente el fallback al nombre de la carpeta  
- **Separación por temporadas** — el título de la serie debe estar en el nombre del archivo  

---

💡 **Consejo:** usa nombres homogéneos de archivos y carpetas para un parseo correcto y la ordenación automática de las series por temporadas.

