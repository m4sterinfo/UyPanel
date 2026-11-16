---
name: uypanel
description: Contexto vivo del proyecto UyPanel (fork independiente del proyecto original, v2.0.1) — arquitectura, comandos, convenciones y registro de cambios propios. Invocar antes de trabajar en el proyecto y actualizar tras cada mejora.
argument-hint: "[tema opcional: build|deploy|arquitectura|migracion]"
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - glob
---

# UyPanel — Panel IPTV (fork independiente del proyecto original)

Eres el agente de desarrollo de **UyPanel**, un fork independiente de
el proyecto original (repo upstream, ya desvinculado) basado en el tag
**2.0.1** (commit `e21f9063`). El objetivo es evolucionar el panel como proyecto
propio: modificar, añadir funciones y mantener el control total del código.

## Protocolo obligatorio (LEER Y CUMPLIR)

1. **SEGURIDAD PRIMERO — PRIORIDAD ABSOLUTA (CRÍTICO)**:
   - **NUNCA exponer herramientas o servicios administrativos en rutas públicas sin autenticación**:
     Cualquier herramienta interna, proxy inverso o endpoint administrativo DEBE estar protegido por el sistema de autenticación de UyPanel (`auth_request /internal_admin_auth` en Nginx o dentro del Front Controller con `requirePermission()`).
   - **NUNCA bindear servicios internos a `0.0.0.0` (todos a `127.0.0.1`)**:
     Todo demonio o servicio auxiliar (Node.js, Python, microservicios) DEBE escuchar EXCLUSIVAMENTE en `127.0.0.1` (localhost). Jamás exponer puertos de soporte al internet público.
   - **Anti-sondeo estricto (Anti-Probe)**:
     Toda petición no autorizada a rutas administrativas o herramientas internas debe responder con `HTTP 404 Not Found` idéntico a Nginx. Nunca revelar la existencia de herramientas ni servicios a escaneadores o atacantes.
   - **Control de acceso por roles**:
     Las herramientas administrativas son exclusivas para usuarios con rol de administrador activo (`is_admin: true`). Los revendedores y usuarios públicos no deben tener acceso a herramientas de gestión ni scrapers.
2. **ANTES de trabajar**: lee `STATE.md` (junto a este archivo) — contiene el
   estado actual, decisiones tomadas y trabajo pendiente.
3. **DESPUÉS de cada cambio significativo**: actualiza `STATE.md`:
   - Añade entrada al "Registro de cambios" (fecha, qué, por qué, archivos).
   - Actualiza secciones afectadas (estado, pendientes, decisiones).
   - Esto es lo que mantiene el skill vivo y actualizado con el proyecto.
4. **Convenciones de código**: el repo hereda reglas estrictas en
   `.github/instructions/*.instructions.md` (arquitectura, PHP, bash, nginx,
   migraciones, build). Léelas antes de tocar código de `domain/`, `core/`,
   `public/` o `streaming/`. La especificación completa está en
   `ARCHITECTURE.md` (~83 KB) y `specs/MODULE_SYSTEM_SPEC.md`.
5. **Commits**: mensajes en inglés, estilo conventional
   (`feat:`, `fix:`, `docs:`, `refactor:`, `chore:`), enfocados al "por qué".
   El usuario hace el push a GitHub; nunca hacer push sin pedido explícito.

## Estructura del repo

```
UyPanel/
├─ src/                  # Código fuente del panel (lo que se despliega en /home/uypanel)
│  ├─ core/              # Infraestructura compartida (Database, Cache, Events) — no depende de nada externo
│  ├─ domain/            # Lógica de negocio por contexto (Controller → Service → Repository)
│  ├─ public/            # Presentación (www/, admin, player_api, proxies)
│  ├─ streaming/         # Motor de streaming
│  ├─ modules/           # Módulos aislados (tmdb, watch, ministra, plex...) — solo MAIN, no van al LB
│  ├─ infrastructure/    # Bootstrap legacy, nginx templates, redis manager
│  ├─ ministra/          # Portal Ministra/MAG
│  ├─ cli/               # Comandos CLI (console.php ...)
│  ├─ crons/  signals/  migrations/  tools/  bin/
├─ lb_configs/           # nginx.conf + live.conf para el build del Load Balancer
├─ tools/                # build_ffmpeg.sh, tests de player API, scripts varios
├─ docs/                 # Documentación (mkdocs, en/ y es/)
├─ third_party/          # SUBMÓDULOS git (sistemas de build de binarios)
│  ├─ UyPanel_Binaries/  # Build de PHP/nginx/nginx_rtmp por distro (docker; versions.json = fuente de verdad)
│  └─ UyPanel_FFMPEG/    # Build de FFmpeg 8.0/7.1/4.0 (docker)
├─ install               # Instalador Python (Debian 12/13 + Ubuntu; instala las deps runtime del payload: libcurl3t64-gnutls, libsodium23)
├─ Makefile              # Sistema de build de payloads
├─ ARCHITECTURE.md       # Especificación técnica completa
└─ .devin/skills/uypanel/  # Este skill (SKILL.md + STATE.md)
```

## Comandos clave

```bash
# Build de payloads (generan dist/)
make main        # → dist/uypanel.tar.gz + dist/UyPanel.zip (panel principal)
make lb          # → dist/loadbalancer.tar.gz (load balancer; excluye modules/, admin, etc.)
make main_update # → dist/update.tar.gz (actualización de panel instalado)
make lb_update   # → dist/loadbalancer_update.tar.gz

# Binarios (PHP 8.3, nginx, nginx_rtmp) y FFmpeg:
cd third_party/UyPanel_Binaries && ./build_all.sh  # usa docker/; versions.json manda
cd third_party/UyPanel_FFMPEG && make             # ver su README/Makefile
# Valkey (servidor de caché) NO lo builda UyPanel_Binaries — compilar directo:
#   docker run --rm -v /tmp/vk-src:/src -v /tmp/vk-out:/out ubuntu:20.04 bash -c
#   "apt-get update && apt-get install -y build-essential && cd /src && make -j$(nproc) BUILD_TLS=no
#    && cp src/valkey-server src/valkey-cli /out/"   (focal → binario portable glibc 2.29)

# Sintaxis PHP (validar antes de commitear cambios PHP):
tools/php_syntax_check.sh   # o: find src -name "*.php" | xargs -n1 php -l

# Servidor en producción (Debian 13, panel instalado en /home/uypanel):
sudo systemctl start|stop|restart uypanel
sudo /home/uypanel/status 1                          # estado: DB + migraciones
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php <comando>   # CLI del panel
journalctl -u uypanel -f
```

## Entorno de producción (servidor actual)

- Debian 13 (trixie), 8 cores, 32 GB RAM, IP `198.27.81.133`
- **Política de SO (2026-11-15, decisión del usuario)**: SOLO **Debian 13** y
  **Ubuntu 24.04 LTS** — Ubuntu 20.04/22.04 y Debian 12 SIN soporte (EOL/vulnerables;
  nginx_rtmp exige GLIBC 2.38). El instalador rechaza el resto y `bin/distros/`
  solo lleva `debian_13` + `ubuntu_24`. Un LB en Ubuntu viejo → reinstalar en
  Debian 13/Ubuntu 24.04.
- UyPanel 2.0.9 (base original 2.0.1) instalado en `/home/uypanel` (nginx 1.30.6 en 80/443,
  nginx_rtmp 1.30.6, 4 pools php-fpm 8.3.36, **Valkey 9.1.3** en 6379 con auth,
  MariaDB 11.8.9 — 11.8.10 pendiente de mirror; ffmpeg activo 8.0). Binarios de
  prod = payload limpio (sin strings del proyecto original, swap 2026-11-16).
  Contrato nginx↔PHP: **`UY_SCOPE`/`UY_CODE`/`UY_API`** (fallback `XC_*` legacy
  hasta fase 2 — ver STATE.md).
- Base de datos migrada desde XUI (27,672 líneas, 240,557 streams)
- Acceso admin: `http://198.27.81.133/7JDEeDMG` (código tras la reinstalación
  limpia del 2026-11-04; el `x9access9x` anterior murió con la BD vieja)
- Credenciales MariaDB: `/home/debian/credentials.txt` (BD: `uypanel`) (NO commitear nunca)
- Binarios nativos Debian 13 (PHP 8.3.36 hardened, nginx/nginx_rtmp 1.30.6 estáticos).
  El instalador (desde 2.0.3) instala sus deps runtime: `libcurl3t64-gnutls` +
  `libsodium23` (verificado en containers debian:13/debian:12 limpios). El hack de
  `libjpeg.so.8` en `/usr/local/lib` solo aplica al rollback de binarios 8.1
  viejos — ver `/home/debian/AGENTS.md`.
- **Estado funcional (2026-11-15)**: resellers/super resellers operativos
  (grupos 2/3, codes `uyresellers`/`uysresellers`), APIs REST funcionales
  (codes `uyadminapi` type 3 + `uyresellersapi` type 4; auth por `users.api_key`),
  sección **API Docs** en ambos paneles (menú), **MAG/Enigma2 DESACTIVADOS**
  (portal Stalker = vector de fuerza bruta por MAC; `disable_ministra=1`,
  `disable_enigma2=1`, paquetes sin is_mag/is_e2, páginas/rutas/acciones API
  eliminadas — ver STATE.md).

## Servidor de caché: Valkey (NO KeyDB — migrado 2026-11-15)

- **Valkey 9.1.3** (fork de Redis mantenido por Linux Foundation, BSD-3) reemplazó a
  KeyDB 6.3.4 (abandonado desde Oct 2023, CVE-2025-49844 "RediShell" RCE sin parche).
- Binarios del payload: `bin/redis/valkey-server` + `bin/redis/valkey-cli`
  (compilado en Docker ubuntu:20.04 → solo requiere GLIBC 2.29, corre en todas las
  distros soportadas; solo enlaza libc/libm — sin libssl/libcurl).
- `src/service` (start) **prefiere `valkey-server` con fallback a `redis-server`**
  → los paneles viejos migran solos al recibir el binario por delta + restart.
- `bin/redis/redis.conf` se acepta tal cual (alias `slave-*` OK); las directivas
  `server-threads`/`server-thread-affinity` son EXCLUSIVAS de KeyDB → si existen,
  Valkey no arranca (removidas de prod; el template del instalador nunca las tuvo).
- KeyDB queda como `bin/redis/redis-server_6.3.4` (rollback) y `redis-server`
  sigue en el payload como fallback del service.
- INFO server: `valkey_version:9.1.3` + compat `redis_version:7.2.4` — el panel
  muestra "Valkey 9.1.3" (SettingsController y prelude de settings.php chequean
  valkey_version → keydb_version → redis_version, en ese orden).

## Alertas de software desactualizado (Settings → Info)

- `core/Diagnostics/SoftwareVersions.php` — `SoftwareVersions::getReport($rSettings, $db)`:
  inventa versiones instaladas (nginx/nginx_rtmp vía `-v`, php, phpredis, MariaDB
  `VERSION()`, cache vía INFO, ffmpeg activo de `ffmpeg_cpu`) y las compara con el
  manifest usando `version_compare` (normaliza sufijos tipo `-MariaDB`).
- **Manifest de últimas versiones**: `UyPanel_Update/versions.json` (repo hermano, como
  changelog.json/api_server.json) → caché local 24h en `tmp/software_versions_cache.json`,
  stale-while-error, fallback incluido `src/config/software_versions.json`. Curl timeout 4s
  — nunca bloquea la página. Fuente reportada en el footer de la pestaña
  (remote/cache/cache-stale/bundled).
- **Para actualizar las versiones de referencia**: editar `versions.json` en
  `m4sterinfo/UyPanel_Update` (campos: `latest`, `notes` URL, `hint` para el admin,
  `eol: true` para productos muertos — KeyDB está marcado EOL). NO requiere release del panel.
- Vista Info: banner `alert-warning` (outdated/EOL con notas + hint) o `alert-success`;
  badges por fila (verde ✓ / ámbar →X.Y.Z / rojo EOL); fila phpredis; label "Cache Server".
- **Gotcha del prelude**: `settings.php` recomputaba `$RedisVer/$Nginx/$MariaDB/...` en su
  prelude y pisaba los valores del controlador (el guard chequea `$__settingsViewMode` que
  el render genérico NUNCA setea — setea `$__viewMode`). Fix: prelude idempotente
  (`if (!isset($X))` por variable). Si se añaden variables nuevas al Info tab, hacerlas
  idempotentes en el prelude.

## APIs REST externos (Admin + Reseller)

- **Endpoints**: los Access Codes type 3 (`includes/api/admin`) y type 4
  (`includes/api/reseller`) crean la URL nginx `/<code>/` → Front Controller
  (bloque 3a, ANTES del guard de login — fix `52d085f9`). Variante
  `/<code>/api.php` también válida.
- **Auth**: `api_key` (32-hex) en `users.api_key` — NO son los access codes.
  Se genera desde Edit Profile (botón) o SQL. Admin API exige `is_admin=1`,
  Reseller API `is_reseller=1`.
- **Controladores**: `public/Controllers/Api/AdminApiController.php` y
  `ResellerRestApiController.php` (con `AdminAPIWrapper`/`ResellerAPIWrapper`).
  El reseller delega en `includes/reseller_api.php` (`ResellerAPI`), que aplica
  paquetes y descuenta créditos; el Admin API (`LineService`/`UserService`...)
  crea con campos crudos.
- **Documentación**: página **API Docs** en ambos paneles
  (`ApiDocsController`/`ResellerApiDocsController` + vistas `api_docs.php`,
  helper compartido `public/Views/api_docs_common.php`, estilos en
  `assets/{admin,reseller}/css/custom.css`) y `docs/{es,en}/api/rest_api.md`.
- **Señal de caché**: tras crear/editar una línea SIEMPRE llamar
  `LineService::updateLineSignal($id)` — sin ella, con `enable_cache=1` la
  línea no reproduce hasta el ciclo de `cache_engine` (5 min).

## MAG / Enigma2 — DESACTIVADOS (decisión de diseño)

- Motivo: el portal Stalker permite fuerza bruta simulando MACs; sin clientes E2.
- Settings: `disable_ministra=1` (portal.php inerte; symlinks `www/c` +
  `www/portal.php` ausentes, el watchdog los mantiene muertos con
  `mag_legacy_redirect=0`), `disable_enigma2=1` (`/enigma2.php` → 404).
- Paquetes: `is_mag=0, is_e2=0` → permisos reseller `create_mag`/`create_enigma`
  = false (derivados de los paquetes del grupo, NO de columnas de users_groups).
- Código eliminado: páginas/controladores/rutas de dispositivos (admin +
  reseller), acciones MAG/E2 de ambos APIs, menús, casos de
  checkPermissions/checkResellerPermissions. Conservado: `magscan_settings`
  (herramienta de seguridad) y rutas muertas inertes en archivos compartidos.
- Para reactivar: settings + restaurar desde git history.

## Relación con upstream

- `origin` = https://github.com/m4sterinfo/UyPanel (NUESTRO repo; push normal).
- El proyecto original solo se consulta si se quiere
  traer fixes: añádelo como remote aparte (`git remote add upstream ...`),
  compara con `git log 2.0.1..upstream/main --oneline` y cherry-pick selectivo
  (NO merge ciego: rompería nuestras modificaciones).
- Ver qué hemos cambiado respecto al original: `git diff 2.0.1..HEAD`
- Repos de soporte propios (forks de upstream renombrados):
  `m4sterinfo/UyPanel_Update`, `m4sterinfo/UyPanel_Binaries`,
  `m4sterinfo/UyPanel_FFMPEG` — el sistema de updates del panel (AppConfig)
  ya apunta a ellos.

## Publicar releases

- El Quick Install del README descarga de `releases/latest` de NUESTRO repo.
- El CI (`.github/workflows/build-release.yml`) se dispara al publicar la
  release y builda/sube TODOS los assets automáticamente
  (`make lb/main/main_update/lb_update` desde el tag).
- **Checklist obligatorio** (lecciones de la 2.0.3 —publicada 2 veces— y de la 2.0.7):
  1. Bump de `UyPanel_VERSION` en `src/core/Config/AppConfig.php` **ANTES del push** —
     si el push que dispara la release sale sin el bump, el delta no lleva AppConfig.php
     y los paneles quedan en loop de "update disponible" reportando la versión vieja
     (caso 2.0.7: release reconstruida). El bump SIEMPRE en el commit previo a crear la release.
  2. Commit + push a main.
  3. `gh release create <tag> --title "UyPanel <tag>" --notes ...` — el tag se
     crea en HEAD de main; el CI arma y sube los assets.
  4. **AUDITAR el delta descargándolo** (no confiar en el CI verde): descargar
     `update.tar.gz` + `hashes.md5`, verificar MD5, listar el tar y confirmar que
     `AppConfig.php` dentro dice la versión nueva.
  5. Actualizar `UyPanel_Update/changelog.json` (entrada de la versión nueva) —
     el popup de update del panel la lee de ahí.
- Releases: 2.0.1 (2026-11-02), 2.0.2, 2.0.3 (2026-11-14: fix instalador —
  `libcurl3t64-gnutls` + `libsodium23` faltantes causaban 502 en servers
  nuevos; política de SO en docs: Debian 13 recomendado, Ubuntu 22.04 y
  anteriores deprecados), 2.0.4 (2026-11-14: anti-servers-fantasma — las
  instalaciones fallidas se auto-eliminan del panel; payload de proxy
  reconstruido con el stack actual), 2.0.5 (2026-11-14: anti-fantasma
  completado — verificación post-instalación de primer reporte 240s,
  reaper con hard-kill de installs colgados >1h y muertos sin log, touch
  del `.install` antes del launch; import M3U de review.php revivido
  (data-prep muerto dentro del guard), claves de theft_detection,
  servers.php auto-provisión), 2.0.6 (2026-11-15: playback instantáneo de
  líneas de reseller — señal `update_line` en `ResellerAPI::processLine`;
  APIs REST admin/reseller accesibles por URL raíz — dispatch antes del
  guard de login; hardening `create_line` — `normalizeIDList`; sección
  API Docs admin+reseller; MAG/Enigma2 eliminados del panel — 22 archivos
  viajan en `migrations/deleted_files.txt` del delta; fix popup al guardar
  perfil reseller — setLanguage null), 2.0.7 (2026-11-15: **Valkey 9.1.3
  reemplaza KeyDB 6.3.4** — binario portable glibc 2.29, service con
  fallback, migración automática al restart post-update; **nginx+rtmp
  1.30.6 stable** — 5 CVEs Oct 2026 incl. CVE-2026-15176 alias+rewrite;
  **alertas de software desactualizado** en Settings → Info —
  SoftwareVersions + manifest remoto UyPanel_Update/versions.json con
  caché 24h y fallback incluido; check_versions.sh elige rama stable de
  nginx). El changelog de la notificación de
  update vive en `UyPanel_Update/changelog.json` (repo aparte — actualizarlo
  TAMBIÉN en cada release; estaba atrasado hasta 2.0.6).

## Tono de trabajo

- Cambios pequeños y enfocados; un tema por commit.
- Validar sintaxis PHP y probar en el servidor antes de commitear.
- Ante duda de arquitectura: ARCHITECTURE.md manda; luego las instructions.
- Registrar TODO cambio en STATE.md — el skill es la memoria del proyecto.

## Gotchas del Front Controller (lecciones aprendidas)

- **Patrón `$__viewMode`**: `BaseAdminController::render()` setea `$__viewMode = true`
  antes de requerir la vista → el bloque `if (!isset($__viewMode)):` del top de la
  vista SE SALTEA (bootstrap, header y asignaciones de datos). Las vistas NO deben
  depender de asignaciones dentro de ese guard: los datos que el body usa deben
  asignarse FUERA del guard o venir del `extract()` de render() (que pasa las
  globals listadas en `$viewGlobals`). Casualidad que lo delata: una lista que
  renderiza vacía aunque la BD tenga los datos.
- **El guard es código muerto en AMBAS rutas**: el fallback legacy de
  `public/index.php` (~línea 425) TAMBIÉN setea `$__viewMode = true` antes de
  requerir el archivo legacy. Cualquier data-prep o POST-handling dentro del guard
  nunca corre (caso review.php: el import M3U entero estaba muerto, fix `b86daeb3`).
  Si una vista tiene side-effects en el guard, verificar que su controlador FC los
  replique (IpController→flush, MovieMassController→massEdit, etc. ya lo hacen).
- **Redirects en vista FC**: no hay output_buffering (0) ni implicit_flush off →
  un `header('Location:')` tras el HTML del layout falla. Patrón aceptado:
  `if (!headers_sent()) { header(...); } else { echo '<script>window.location.replace(...)</script>'; } exit();`
- **Global `$rServers` pisada**: `bootstrapAdminRuntime()` sobreescribe la global
  `$rServers` con `getStreamingSimple()` (solo `server_type = 0`: main + LBs). Los
  proxies (server_type=1) viven en `$rProxyServers`. Una vista que filtre
  `server_type == 1` sobre `$rServers` NUNCA matchea (caso proxies.php, fix
  `4561e364`). "Manage Servers" funciona por coincidencia de filtros.
- **`console.php update` a secas es no-op**: espera subcomando (`update update` /
  `post-update`); imprime usage desde `dfbf2831`. La notificación de update la
  escribe `cron:update` (cron diario 00:00) escribiendo `settings.update_data`.
- **4 pools FPM y opcache por pool**: `opcache_reset()` vía un fichero temporal
  en `www/` solo limpia el pool que sirve ESA petición (least_conn). Tras un
  despliegue de código, repetir el reset ~8 veces (o reiniciar FPM) antes de
  verificar; `revalidate_freq=20s` por archivo también retrasa el pickup.
- **Controladores FC necesitan `global $db`**: el bootstrap conecta la BD en el
  paso 4b del FC, pero dentro de un método `$db` es local-null sin `global $db`
  → "Call to a member function query() on null" (EXCEPTION en error_log).
- **Login del panel por curl**: el POST exige el campo `login=1` (nombre del
  botón submit) además de username/password.
- **error_log de prod se rota**: `tmp/logs/error_log.log` lo vacía el TmpCronJob
  — los históricos desaparecen; capturar errores en el momento.
- **Permisos reseller derivados de paquetes**: `create_line`/`create_mag`/
  `create_enigma` del reseller salen de `getGroupPermissions()` leyendo los
  `users_packages` del grupo (con caché igbinary `tmp/cache/permissions_<gid>`
  — regenerar con `cron:cache_engine groups` como uypanel, como root falla el
  assertRunAsUyPanel). Cambiar paquetes NO surte efecto hasta invalidar esa caché.
- **Translator**: default `$langsDir = includes/libs/lang/` NO existe; el init
  correcto lo hace `UyPanel_Bootstrap::initTranslator()` con `includes/langs/`.
  `setLanguage(string)` es non-nullable — nunca llamarlo con posible null.
- **Swap de binarios en caliente** (probado 2026-11-16 con actividad: 2 jobs
  ffmpeg + panel sirviendo — cero downtime):
  1. `cp` sobre el binario en uso falla con "Text file busy". Mejor que rm+cp:
     `install -m <modo> -o uypanel -g uypanel <nuevo> <dest>.new && mv -f
     <dest>.new <dest>` — el rename NO sufre ETXTBSY ni deja ventana sin
     fichero (el proceso vivo conserva su inode viejo).
  2. **Upgrade de proceso zero-downtime = USR2 (re-exec)**, mejor que
     quit+start cuando hay actividad: `kill -USR2 <master_pid>` re-executa el
     binario nuevo (hereda sockets); los masters viejos salen al drenar.
     php-fpm: USR2 a cada master de `bin/php/sockets/{1..4}.pid` (gestiona su
     pidfile solo, sin gotcha). nginx/nginx_rtmp: USR2 + `kill -QUIT <viejo>`.
  3. **CRÍTICO — pidfile de nginx**: al salir, el master viejo hace unlink del
     pidfile que el master nuevo ya reescribió → `nginx -s reload` queda roto.
     Si el master viejo puede tardar en salir (conns ffmpeg activas),
     dejar un watchdog que restaure `bin/nginx/logs/nginx.pid` con el PID del
     master nuevo cuando el viejo salga (ej.: `systemd-run --unit=... <script>`).
  4. NO matar procesos ffmpeg del panel sin confirmar: los jobs de prep HLS
     graban con `-progress http://127.0.0.1/progress?stream_id=N` (POST largo,
     NO keepalive idle — mantienen al worker nginx drenado indefinidamente).
     Si el master viejo sale, esas conns se cierran → ffmpeg muere → **el
     watchdog del panel lo relanza solo (~1 min) ya con el binario nuevo**.
     Verificar con el usuario antes de matar; nunca forzarlo a mano.
  5. Verificación del swap: inodos `stat -Lc %i /proc/<master>/exe` == disco;
     `strings -a <bin> | grep -ci 'xc_vm\|xcvm'` = 0; panel 200; segmentos HLS
     frescos (`ls --time-style=+%H:%M:%S content/streams/` CON sudo — sin sudo
     falla en silencio).
- **404 anti-probe del streaming API**: `ErrorHandler::generate404()` EMULA la
  página HTML 404 de nginx (padding MSIE incluido). Un 404 con ese cuerpo viene
  de PHP, NO de nginx: `player_api.php`/`api.php` responden así a credenciales
  inválidas o probes POR DISEÑO. No confundir con fallo de enrutado — trazar
  por CLI (simular `$_SERVER['UY_*']` + require `public/index.php`) o usar
  credenciales válidas.
- **Contrato nginx↔PHP**: `UY_SCOPE`/`UY_CODE`/`UY_API` (desde 2.0.9; antes
  `XC_*`). PHP lee UY_ primero con fallback XC_ (fase 2 lo eliminará cuando
  todos los installs ≥ 2.0.9). Emisores: `codes/template` (confs generadas),
  `nginx.conf` main, `lb_configs/nginx.conf` (LB). Los proxies type-1 no llevan
  ninguno. El post-update regenera `codes/*.conf` (`AuthRepository::updateCodes()`).
- **`bin/distros/<distro>.tar.gz`** (runtime, no está en git): BalancerCommand
  los despliega por SSH al instalar Proxy/LB según el OS detectado. Tras
  actualizar binarios, REFRESCAR estos tarballs desde
  `third_party/UyPanel_Binaries/out/<target>.tar.gz` o los LBs nuevos
  instalarán binarios viejos.
