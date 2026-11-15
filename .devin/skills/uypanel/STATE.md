# UyPanel — Estado del proyecto (documento vivo)

> Este archivo lo mantiene el agente (vía skill `uypanel`). Se actualiza tras
> cada cambio significativo. Última actualización: 2026-11-15 (release 2.0.7 publicada).

## 🆕 2026-11-15 — Release 2.0.7: KeyDB→Valkey 9.1.3, nginx 1.30.6 y alertas de software desactualizado

**Pedido del usuario**: "el panel está usando una versión desactualizada de redis; revisar a fondo y actualizar todo lo desactualizado manteniendo compatibilidad, y que en Settings → Info haya alertas de software desactualizado para que el admin pueda actualizarlo".

**Inventario vs últimas versiones (2026-11-15)**:
| Componente | Instalado | Último | Acción |
|---|---|---|---|
| Cache server | KeyDB 6.3.4 (Oct 2023) | Valkey 9.1.3 / Redis 8.10.3 | ✅ migrado a Valkey 9.1.3 |
| Nginx (público) | 1.29.6 | 1.30.6 stable | ✅ actualizado |
| Nginx RTMP | 1.29.6 | 1.30.6 | ✅ actualizado |
| MariaDB | 11.8.9 | 11.8.10 (13 Nov) | ⚠️ mirror deb.mariadb.org aún no lo tiene — pendiente, la alerta lo recordará |
| PHP | 8.3.36 (parcheado PHP2Shell) | 8.3.36 | ✅ al día |
| phpredis | 6.3.0 | 6.3.0 | ✅ al día |
| FFmpeg | 4.0–8.0 incluidos | 8.0.x | ✅ (activo configurable en Streaming) |

**Por qué Valkey y no Redis 8**: KeyDB está abandonado (Snap, último release Oct 2023, su autor John Sully dejó la empresa en 2025 y recomienda Valkey) y arrastra **CVE-2025-49844 "RediShell" (CVSS 9.9, RCE post-auth vía Lua) SIN parche** — el PR del fix lleva abierto desde Oct 2025. Redis 8.10.3 es oficial pero AGPL/RSAL/SSPL (redistribuir el binario en el payload comercial de UyPanel genera obligaciones); Valkey es BSD-3, mantenido por Linux Foundation (AWS/Google), drop-in compatible y el sucesor que recomienda el propio autor de KeyDB. Elección del usuario vía pregunta (Valkey 9.1.3 + nginx 1.30.6 stable).

**Migración KeyDB→Valkey en prod (198.27.81.133)**:
1. Compilado Valkey 9.1.3 en Docker **debian:12** y **ubuntu:20.04** (este último es el del payload: solo requiere GLIBC 2.29 → corre en TODAS las distros soportadas; solo enlaza libc/libm — el KeyDB viejo necesitaba libssl3/libcurl/libstdc++ y el instalador tenía un hack wget de libssl3 para focal que ya no hace falta). `make BUILD_TLS=no`, jemalloc incluido.
2. Config probada en puerto 6380 antes del swap: Valkey acepta el redis.conf del panel (incluye alias `slave-*`) salvo `server-threads`/`server-thread-affinity` (exclusivas de KeyDB) → eliminadas de prod con sed. El template del instalador NUNCA tuvo esas líneas → los demás paneles no necesitan cambio de config.
3. Swap: `kill` limpio del KeyDB (dbsize=0, caché file-based — cero pérdida), binario nuevo en `bin/redis/valkey-server` (+`valkey-cli`), KeyDB conservado como `redis-server_6.3.4` (convención existente con el 6.2.2). `service` actualizado: prefiere `valkey-server`, fallback `redis-server` (compatible adelante y atrás — los paneles que reciban el delta migran solos al reiniciar).
4. **Verificado en prod**: INFO `valkey_version:9.1.3` (compat `redis_version:7.2.4`), phpredis setex/get/del/exists OK, login 200, dashboard/lines/servers/settings 200, **playback HTTP 200 video/mp2t**.

**Alerta de incidente (transparencia)**: durante el swap de nginx maté 2 procesos ffmpeg creyendo que estaban colgados (conexiones CLOSE-WAIT + mi `ls` de segmentos falló en silencio por permisos → asumí "sin output"). El usuario aclaró que eran 2 canales activos de hace ~29 min. Eran jobs de prep HLS en background (el serving es proxy directo de nginx — el playback siguió 200 en todo momento, cero impacto en viewers), pero igualmente fueron terminados por error. El panel los relanza solo por on-demand/watchdog cuando hay clientes. **Lección**: verificar permisos antes de concluir "sin output" y confirmar con el usuario antes de matar procesos del panel.

**nginx 1.30.6 en prod**: build vía `third_party/UyPanel_Binaries` (`versions.json` nginx→1.30.6, `sudo ./build_all.sh debian13`, 25/25 tests). Binarios **estáticos** (nginx) → portable a todas las distros; nginx_rtmp dinámico pero exige el MISMO glibc 2.38 que el actual (sin regresión). Swap con `-s quit` (0 streams activos) + arranque nuevo. **Gotcha crítico**: al salir, el master viejo hace unlink del pidfile que ya pertenece al master nuevo → `nginx -s reload` roto hasta restaurar `logs/nginx.pid` a mano (hecho). Si se repite el procedimiento, restaurar el pidfile SIEMPRE tras la salida del master viejo.

**Feature: alertas de software desactualizado (Settings → Info)** (commit `821b63a2`):
- `src/core/Diagnostics/SoftwareVersions.php`: `getReport($rSettings, $db)` — inventa versiones instaladas (nginx/rtmp vía `-v`, php, phpredis, MariaDB `VERSION()`, cache vía INFO `valkey_version|keydb_version|redis_version`, ffmpeg activo de `ffmpeg_cpu`) y las compara con el manifest (`version_compare` tras normalizar sufijos).
- Manifest: `UyPanel_Update/versions.json` (commit `21d300b`) con caché local 24h (`tmp/software_versions_cache.json`), stale-while-error y fallback incluido `src/config/software_versions.json`. Timeout curl 4s — nunca bloquea la página.
- Estados: `ok` / `outdated` / `eol` (KeyDB marcado EOL en el manifest: alerta aunque esté en su última versión) / `unknown` (sin datos).
- Vista Info: banner `alert-warning` con lista (instalada → disponible, link a notas, hint para el admin) o `alert-success` si todo al día; badges por fila (verde ✓ / ámbar →X.Y.Z / rojo EOL); fila nueva phpredis; label "KeyDB / Redis" → "Cache Server"; footer con fuente del manifest (remote/cache/cache-stale/bundled).
- **Gotcha del prelude legacy**: `settings.php` recomputaba `$RedisVer/$Nginx/$MariaDB/...` en su prelude (`if (!isset($__settingsViewMode))` — el render genérico setea `$__viewMode`, NUNCA `$__settingsViewMode`) y pisaba los valores del controlador → el botón mostraba "Redis 7.2.4" (compat) en vez de "Valkey 9.1.3". Fix: prelude idempotente (`if (!isset($X))` por variable) con lógica valkey-first para el modo legacy.
- El controlador ya no duplica shell_exec de nginx/rtmp (el reporte es fuente única).

**Release 2.0.7**: bump `UyPanel_VERSION` en AppConfig, push main (`821b63a2`), `gh release create 2.0.7` (run `43776369205`), changelog.json con entrada 2.0.7 (commit `70b7bbb` en UyPanel_Update). El delta update.tar.gz incluye los binarios (valkey-server+cli ~40MB, nginx+rtmp ~30MB) → **los paneles instalados migran a Valkey 9.1.3 + nginx 1.30.6 automáticamente** con el update + restart (post-update hace `systemctl restart uypanel` → service start prefiere valkey-server). Los redis.conf de otros paneles no llevan `server-threads` → migración sin fricción. Prod ya desplegado a mano (quedará con versión reportada 2.0.7).

**Pendiente para próxima sesión**: MariaDB 11.8.10 cuando el mirror la tenga (la alerta del panel lo indicará); verificar assets de la release 2.0.7 (CI); pendientes arrastrados: panel del usuario (178.156.194.122) en 2.0.4→2.0.5→2.0.6→2.0.7 secuenciales; verificar que el reaper ampliado no interfiera con el proxy #4 en producción.

## 🆕 2026-11-15 — Release 2.0.6 publicada: fixes de hoy llegan a los paneles instalados

**Pedido del usuario**: continuar con los releases para que los fixes se envíen a los paneles instalados.

**Publicada** (checklist del SKILL.md completo):
1. Bump `UyPanel_VERSION` 2.0.5 → 2.0.6 (`src/core/Config/AppConfig.php`, commit `88ab6059`).
2. Push a main.
3. `gh release create 2.0.6` — CI Build Release Assets (run `43772316742`) verde en 3m2s, 5 assets: UyPanel.zip (470MB), loadbalancer.tar.gz (326MB), update.tar.gz (62KB), loadbalancer_update.tar.gz (42KB), hashes.md5.
4. **Verificación**: MD5 de update.tar.gz y lb_update coinciden con hashes.md5; delta contiene TODOS los fixes de hoy (LineService, reseller_api, ResellerPostController, public/index.php, api_docs ×5, headers, routes, custom.css, admin.php) + `migrations/deleted_files.txt` con los 22 archivos MAG/Enigma2 (el `post-update` los borra automáticamente en cada panel instalado vía `MigrationRunner::runFileCleanup`); `UyPanel_VERSION = '2.0.6'` dentro del zip y del delta; payload completo SIN controladores MAG/E2 (solo queda `MagscanSettingsController`, conservado a propósito).

**Changelog de la notificación de update** (`m4sterinfo/UyPanel_Update/changelog.json`): estaba ATRASADO (solo 2.0.1 y 2.0.2 — las releases 2.0.3–2.0.5 no mostraban changelog en el popup de update del panel). Añadidas entradas 2.0.3, 2.0.4, 2.0.5 y 2.0.6 en español (commit `6d76991` en ese repo). **Lección**: actualizar `UyPanel_Update/changelog.json` es parte del checklist de release (el `getUpdate()` lo filtra por versiones válidas y el UI muestra las entries entre la versión actual y la target).

**Mecánica de distribución confirmada** (revisada antes de publicar): `cron:update` → `GitHubReleases::getUpdate(UyPanel_VERSION)` → `getNextVersion` (la release siguiente en la lista de GitHub, NO la "latest" — publicar 2.0.6 es seguro aunque el Makefile del CI consulte latest; el CI usa `git tag --sort=-v:refname` para el prev tag) → `settings.update_data` → el admin lanza la actualización desde la UI → descarga `update.tar.gz` + MD5 → `src/update` (python) extrae + `console.php update post-update` → migraciones SQL + limpieza de archivos borrados + señales de update a LBs si `auto_update_lbs` + `systemctl restart uypanel` (el restart limpia opcache — no hace falta reset manual tras update).

**Ruta de update del panel del usuario (178.156.194.122)**: está en 2.0.4 → recibirá 2.0.5 primero (cron diario o `console.php update update` manual) y luego 2.0.6 (los deltas son secuenciales por diseño de `getNextVersion`). El panel de prod (198.27.81.133) ya tiene todos los fixes desplegados a mano; puede tomar el update 2.0.6 desde la UI sin riesgo (idempotente — mismos archivos, y `runFileCleanup` comprueba `file_exists` antes de unlink) y así queda con la versión reportada correcta.

**Pendiente para próxima sesión**: verificar que el panel del usuario quedó en 2.0.6 tras los dos updates secuenciales; verificar que el reaper ampliado no interfiera con el proxy #4 en producción.

## 🆕 2026-11-15 — Fix: popup "An error occured" al guardar perfil de reseller (el tema SÍ se aplicaba)

**Reporte del usuario**: en el perfil de un super reseller nuevo, cambiar el tema a dark y Save → popup "An error occured while processing your request."; tras refresh el tema SÍ estaba aplicado.

**Causa raíz (2 bugs encadenados)**:
1. **Fatal después del UPDATE**: el formulario del perfil reseller NO tiene campo `lang`, pero `ResellerPostController::edit_profile` llama `$language::setLanguage($rData['lang'])` con clave inexistente → null → `Translator::setLanguage(string $lang)` es non-nullable → **TypeError fatal** en PHP 8.3 → respuesta vacía → el catch del JS (`post.scripts.php` submitForm) hace parseJSON('') → `{"status":0,"result":false}` → `callbackForm` sin location → `showError("An error occured...")`. El UPDATE ya había corrido dentro de `editResellerProfile` → el tema se aplicaba igual. (El form del ADMIN sí tiene lang → por eso el flujo admin nunca falló.)
2. **lang siempre reseteado a 'en'**: además, la whitelist de `ResellerAPI::processData('profile')` no incluía 'lang' → el campo se eliminaba del POST → el UPDATE de `editResellerProfile` guardaba lang='en' siempre (la validación `in_array(lang, $allowedLangs)` con clave indefinida → null → no match → 'en').

**Fix** (commit `b70e80f6`): (1) `ResellerPostController` — si no viene lang, usar el actual del usuario (`$rUserInfo['lang'] ?? 'en'`); setcookie de hue/theme con `?? ''`; (2) whitelist 'profile' + 'lang' en `reseller_api.php`; (3) selector Language añadido al form del perfil reseller (paridad con admin, markup idéntico).

**Gotchas de depuración**: (a) el error_log de prod se rota/vacía (TmpCronJob) — los históricos desaparecen; (b) hay **4 pools FPM** y `opcache_reset()` vía web solo limpia el pool que sirve la petición — repetir el reset ~8 veces (least_conn) o reiniciar FPM tras desplegar; (c) `Translator` default `$langsDir = libs/lang/` NO existe — el init correcto lo hace `XC_Bootstrap::initTranslator()` con `includes/langs/` (admin_runtime.php:123 solo en la rama de bootstrap fallido).

**Verificación en prod** (super reseller temporal, grupo 3): POST sin lang → JSON válido + lang conservado; POST con lang=es → persistido; theme aplicado sin popup; selector lang renderiza. Usuario temporal eliminado.

## 🆕 2026-11-15 — MAG y Enigma2 DESACTIVADOS en todo el panel (seguridad)

**Pedido del usuario**: los portales MAG son un vector de fuerza bruta (scripts que simulan MACs de dispositivos) y no tiene clientes Enigma2 — desactivar todo lo relacionado, incluida la documentación del API.

**Mecanismo del portal MAG (Stalker)**: se sirve vía symlinks `www/c` → `ministra/` y `www/portal.php` → `ministra/portal.php`, creados/borrados por la señal `enable_ministra`/`disable_ministra` del RootSignalsCronJob según el setting `mag_legacy_redirect`. portal.php además respeta el setting `disable_ministra`. Enigma2: endpoint `/enigma2.php` → FC → Enigma2ApiController, bloqueado por el setting `disable_enigma2`.

**Aplicado (commit `07ea35c2`)**:
- **Datos prod**: `settings.disable_ministra=1`, `settings.disable_enigma2=1`; `users_packages.is_mag=0, is_e2=0` en TODOS los paquetes (los permisos reseller `create_mag`/`create_enigma` derivan de los paquetes del grupo vía getGroupPermissions → con 0 paquetes MAG/E2 quedan false). Caché de permisos regenerada (`cron:cache_engine groups` como uypanel — como root falla el assertRunAsXcVm). Verificado vía API: create_mag/create_enigma=false para grupos 2 y 3.
- **Código eliminado** (22 archivos, -4669 líneas): controladores+ vistas de mag/mags/mag_mass/mag_events/enigma/enigmas/enigma_mass (admin) y mag/mags/enigma/enigmas (reseller); rutas correspondientes; acciones y métodos wrapper MAG/Enigma de AdminApiController y ResellerRestApiController; casos 'mag'/'enigma' de ResellerPostController; casos mag/enigma de checkPermissions/checkResellerPermissions; submenús MAG/Enigma de ambos headers; mag_events de la lista de logs admin; secciones MAG/Enigma2 de la documentación del API.
- **Conservado**: `magscan_settings` (herramienta de SEGURIDAD anti-clonado MAG); rutas muertas en archivos compartidos (table.php secciones mags/enigmas, api.php acciones mag, footer JS case "enigma", formatos de playlist Enigma en el selector de descargas, dashboard reseller links condicionados a is_mag) — inertes sin las páginas; `MagService::syncLineDevices` en el flujo de líneas (no-op sin dispositivos).
- **Estado del portal**: symlinks `www/c` y `www/portal.php` YA estaban ausentes (mag_legacy_redirect=0); con disable_ministra=1 portal.php sería inerte aunque existieran. Verificado: `/portal.php` y `/c/portal.php` → 404; `/enigma2.php` → 404 (E2_DISABLED).
- **Gotcha opcache**: tras desplegar, FPM sirvió versiones viejas (revalidate_freq=20s por archivo) — reset quirúrgico con `opcache_reset()` vía fichero temporal en www/ (crear, curl, borrar). Considerar `opcache_reset` tras despliegues grandes.

**Verificación en prod**: rutas mag/mags/enigma/enigmas/mag_events → 404; acciones create_mag/create_enigma en ambos APIs → "Invalid action"; doc admin y reseller con 0 menciones MAG/Enigma (solo quedan 2 strings inertes del footer compartido); menús sin MAG/Enigma; regresión OK (dashboard/lines admin+reseller 200, create_line por API → playback 200 → delete OK). Usuarios temporales de prueba eliminados.

**Pendiente para próxima sesión**: desplegar 2.0.5 al panel del usuario (178.156.194.122) — el update llega solo por el cron diario o manual via `console.php update update`; verificar que el reaper ampliado no interfiera con el proxy #4 en producción. Los cambios de hoy (fixes API + api_docs + desactivación MAG/E2) irán en la próxima release.

## 🆕 2026-11-15 — Ajuste visual de API Docs (ancho completo + tipografía)

**Feedback del usuario**: la fuente se veía muy pequeña y la página no usaba todo el ancho.

**Causas**: (1) la vista usaba `wrapper boxed-layout` → `.boxed-layout .container-fluid { max-width: 840px }` (app.css:480); (2) tablas con `table-sm` + `code { font-size: 87.5% }` del bootstrap → ~13px.

**Fix** (commit `f50c8456`): wrapper → `wrapper docs-page` (ancho completo) en ambas vistas; estilos `.docs-*` añadidos a `public/assets/{admin,reseller}/css/custom.css` (estaban vacíos, lo cargan ambos layouts; en el repo son hardlink — una escritura cubre los dos): texto 15px (base del panel, app.css:148), código 14/14.5px, bloques `.docs-curl` con fondo rgba neutro (válido en claro y oscuro — el dark usa CSS files separados, no body class), tablas con padding .6rem y headers uppercase 13.5px. `table-sm` eliminado de las tablas docs. Verificado con sesión temporal: ambas páginas a ancho completo, custom.css servido por nginx vía los codes (`/CODE/assets/css/custom.css` → alias por panel). Usuarios temporales eliminados.

## 🆕 2026-11-15 — Sección "API Docs" en el panel (admin + reseller)

**Pedido del usuario**: documentación del API dentro del panel, con ejemplos para admin y reseller (crear demos, renovar líneas, crear líneas, todas las acciones funcionando) y explicación de cómo generar la propia api_key.

**Implementado** (commit posterior a `52d085f9`):
- **Controladores**: `public/Controllers/Admin/ApiDocsController.php` y `public/Controllers/Reseller/ResellerApiDocsController.php` — preparan endpoint(s) dinámicos (codes type 3/4 habilitados → `site_url + code + '/'`), la api_key del usuario logueado, paquetes (`users_packages` / `getPackages()` del grupo), outputs, y la referencia de acciones como arrays de datos. El reseller ve solo las acciones de SU API y las recetas condicionadas a permisos (`create_sub_resellers`, trials, bouquets, restrictions).
- **Vistas**: `public/Views/admin/api_docs.php` (estilo legacy self-contained: guard `$__viewMode` + footer + `<script id="scripts">` inline, patrón codes.php) y `public/Views/reseller/api_docs.php` + `api_docs.scripts.php` (contenido puro + JS separado, patrón lines.scripts.php). Helper compartido `public/Views/api_docs_common.php` (tabla STATUS_*, botones copiar, bloque curl).
- **Contenido** (español): autenticación (api_key por usuario + paso a paso para generarla en Edit Profile + regeneración), formato JSON de respuesta, tabla de errores STATUS_*, referencia completa de acciones por categorías (Admin: ~120 acciones en 9 categorías; Reseller: líneas/MAG/E2/sub-resellers/logs), recetas curl con botón copiar (demo trial=1, crear línea con package, renovar = edit_line con package, editar sin coste, disable/enable/delete, MAG, líneas para sub-resellers con member_id, crear sub-reseller, adjust_credits), endpoints de reproducción para clientes (get.php m3u_plus, player_api, live TS/HLS), notas de seguridad y problemas frecuentes.
- **Rutas**: `api_docs` en `public/routes/admin.php` y `reseller.php` (GET).
- **Menús**: item "API Docs" (icono fa-book) al final del menú admin (`Views/admin/header.php`, tras Suppliers) y en el menú reseller (`Views/layouts/reseller/header.php`, tras Logs).
- **Gotchas encontrados**: (1) los controladores necesitan `global $db` ANTES de consultar (el bootstrap conecta en el FC 4b, pero sin `global` la var local es null → EXCEPTION query-on-null); (2) `getPermissions()['subresellers']` ya viene decodificado a array (no re-hacer json_decode); (3) opcache `revalidate_freq=20s` — tras desplegar, esperar ~20s antes de verificar; (4) el login del panel exige el campo `login=1` en el POST (botón submit).

**Verificación en prod**: páginas renderizadas con sesión real creada vía usuarios temporales (testdocs/testdocsres/testdocssres, grupo 1/2/3, login por curl) — admin 141KB con ambos endpoints; reseller grupo 2 sin secciones sub-reseller; grupo 3 con ellas; menú presente en las 3. Usuarios temporales ELIMINADOS (BD con los 4 users reales). Regresión: APIs user_info OK ×3, playback línea 8 HTTP 200, panels 302→login OK.

**Pendiente para próxima sesión**: desplegar 2.0.5 al panel del usuario (178.156.194.122) — el update llega solo por el cron diario o manual via `console.php update update`; verificar que el reaper ampliado no interfiera con el proxy #4 en producción. Los fixes de hoy (señal update_line, dispatch API, api_docs) irán en la próxima release.

## 🆕 2026-11-15 — Líneas de reseller sin playback + APIs REST (admin/reseller) arreglados

**Pedido del usuario**: los resellers y super resellers loguean y crean líneas, pero las líneas creadas por un reseller NORMAL no reproducen nada (las del super reseller sí). Además pidió dejar funcionales el API de admin y el de resellers (había creado access codes type 3/4 para ellos).

**Diagnóstico (3 causas independientes)**:
1. **Señal `update_line` faltante en `ResellerAPI::processLine`** (`src/includes/reseller_api.php`): el flujo admin (`LineService::process`) llama `LineService::updateLineSignal()` tras el REPLACE, y `processMAG`/`processEnigma` insertan la señal a mano — pero `processLine` no. Con `enable_cache=1` el streaming solo ve la línea vía los ficheros `tmp/cache/lines/line_i_<id>` + `line_c_<user>_<pass>`; sin señal, esos ficheros no existen hasta que `cache_engine` corre (cron cada 5 min, `getChangedLines()` detecta el fichero faltante). Ventana muerta de hasta 5 min tras cada línea de reseller → «no reproduce nada» si se prueba enseguida. El super reseller "funcionaba" por timing (probado tras un ciclo de caché). **Fix**: `LineService::updateLineSignal($rInsertID)` tras el REPLACE en `processLine` (misma llamada que el admin). Verificado: línea creada por API de reseller tiene caché en ~2s (SignalsCommand daemon) y reproduce inmediato (HTTP 200 MPEG-TS).
2. **FC: guard «raíz del code → /login» antes del dispatch REST API** (`src/public/index.php`): para codes type 3/4 (API), la URL raíz `/CODE?action=...` resolvía `pageName='index'` + `scope='admin'` (scopeMap) → el guard GET redirigía 302 a `/CODE/login` ANTES de llegar al bloque 3a → el API era inaccesible por su URL raíz (la que anuncia la UI en Edit Profile como "API URL"). **Fix**: bloque 3a (dispatch `AdminApiController`/`ResellerRestApiController`) movido ANTES del guard. Los codes de PANEL (type 0/1) siguen redirigiendo a login (regresión verificada: 7JDEeDMG, uyresellers, uysresellers → 302 login OK). `/CODE/api.php?action=...` ya funcionaba (pageName='api').
3. **APIs autentican por `users.api_key`, NO por access codes**: los codes type 3 (`uyadminapi`) y 4 (`uyresellersapi`) solo crean el endpoint nginx (alias → `includes/api/admin|reseller` → FC). La sesión del API sale de `AdminAPIWrapper::createSession()` / `ResellerAPIWrapper::createSession()` → `WHERE api_key = ? AND LENGTH(api_key) > 0 AND is_admin/is_reseller = 1`. Todos los usuarios tenían `api_key` vacío → «Invalid API key». **Fix (datos)**: api_key 32-hex generada para jadmin4407 (id 1), jreseller (id 4), jsreseller (id 5). El sub-reseller jsubreseller (id 6) queda sin key (su owner puede generársela desde su perfil). NOTA: el campo `api_key` de Edit Profile (botón generate/clear) es la vía UI.

**Hardening adicional del Admin API `create_line`** (`src/domain/Line/LineService.php`): `LineService::process` es un creador de líneas CRUDO (no aplica el paquete, a diferencia del flujo reseller) y fatalizaba en silencio (HTTP 200 vacío, TypeError `array_values(null)` en línea 182) si faltaba `bouquets_selected`, y creaba líneas inertes (`allowed_outputs='[]'` → USER_DISALLOW_EXT → 404) si faltaba `access_output`. **Fix**: nuevo helper público `LineService::normalizeIDList()` (acepta array PHP | JSON `"[1,2]"` | lista por comas `"1,2"`); `bouquets_selected` vacío → `STATUS_INVALID_INPUT` JSON claro; `access_output` ausente → TODOS los outputs (paridad con el formulario del panel, todo checked por defecto). El mismo `normalizeIDList` se aplicó a las 3 ocurrencias de `bouquets_selected` en `reseller_api.php` (line/MAG/E2) — protege grupos con `allow_change_bouquets=1` del TypeError con strings.

**Verificación end-to-end (prod 198.27.81.133)**: admin API `user_info` OK; reseller API `user_info` OK (jreseller y jsreseller); `create_line` reseller → línea reproduce inmediato; `delete_line` OK en ambos APIs; líneas de prueba 11-15 eliminadas (BD restaurada a 4 líneas originales). Desplegado a mano a `/home/uypanel` (public/index.php, includes/reseller_api.php, domain/Line/LineService.php, chown uypanel).

**URLs del API (para el usuario)**:
- Admin API: `http://198.27.81.133/uyadminapi?api_key=<key de jadmin4407>&action=...` (también `/uyadminapi/api.php?...`)
- Reseller API: `http://198.27.81.133/uyresellersapi?api_key=<key del reseller>&action=...`
- `create_line` (reseller): `&package=<id>` basta (aplica paquete). `create_line` (admin): exige `bouquets_selected` (JSON o comas) y opcional `access_output` (default: todos), `exp_date`, `max_connections`, `member_id`.
- Las api_keys actuales están en la BD (`users.api_key`); el usuario puede regenerarlas desde Edit Profile en cada panel.

**Pendiente para próxima sesión**: desplegar 2.0.5 al panel del usuario (178.156.194.122) — el update llega solo por el cron diario o manual via `console.php update update`; verificar que el reaper ampliado no interfiera con el proxy #4 en producción. Estos 3 fixes irán en la próxima release (los paneles instalados los reciben vía update).

## 🆕 2026-11-14 — Release 2.0.5: huecos anti-fantasma cerrados + auditoría $__viewMode completa

**Pedido del usuario**: continuar los pendientes; garantizar que una instalación fallida de LB/proxy NUNCA deje servers fantasma en la BD.

**Auditoría del código anti-fantasma existente (commit `79560b8d`, 2.0.4)** — 3 huecos encontrados y cerrados (commit `b86daeb3`):
1. **Instalación "termina OK" pero el server nunca reporta** (caso 502 Bad Gateway de la sesión anterior): la fila quedaba `status=1` con "Down since: Always" para siempre — el reaper no toca status=1. **Fix**: `BalancerCommand` tras el install hace poll de `last_check_ago` hasta 240s (señal universal: WatchdogCommand la actualiza en LBs, `proxy_api` en proxies; `DatabaseHandler::query()` tiene auto-reconnect para el poll largo). Si nunca reporta → `failInstall` (fila eliminada, feedback inmediato). `$rInstallStart` capturado al inicio de execute() para el caso re-instalación.
2. **Proceso de install vivo pero colgado** (SSH trabado, DROP de firewall): `pgrep` lo encuentra → el reaper lo saltaba para siempre. **Fix**: reaper con hard-kill — `status=3` + log `.install` sin writes > 3600s (1h) → kill -9 del proceso + deleteById. Un install activo escribe output constantemente (echo por paquete); el único tramo mudo largo es el SCP del payload (~65MB, <30min incluso en links malos).
3. **Proceso muerto antes de crear el log**: el reaper exigía `file_exists(log)` → fila `status=3` eterna. **Fix**: `ServerService::install()` hace `touch()` del `.install` antes del `shell_exec` (ambas rutas: nuevo y edit) → el log siempre existe desde la creación de la fila y el mtime da la ventana de gracia (300s). Además el reaper ahora elimina `status=3` sin proceso + sin log + `last_check_ago=0` (nunca reportó).

**Reaper reescrito** (`ServersCronJob.php:193-224`): tabla de decisión — `status=4` → reap (kill si vivo); `!alive && logAge>300` → reap; `!alive && sin log && last_check_ago=0` → reap; `alive && logAge>3600` → reap+kill. El log `.install` se CONSERVA (debug, como en failInstall). Verificado contra prod: solo main #1 online, nada que tocar.

**deleteById ya cubre lo crítico** (streams_servers, lines_live, lines_activity, servers_stats, signals, parent_id de proxies). Huérfanos menores aceptados (mysql_syslog, panel_logs, streams_errors — filtrados por subquery `IN (SELECT id FROM servers)` en la UI). `grantPrivileges` es un GRANT local (sin riesgo de hang entre INSERT y launch).

**Auditoría $__viewMode (pendiente de la sesión anterior) — completada** con scripts desechables (/tmp/viewmode_audit.py + /tmp/view_audit2.py, no commiteados):
- 60 vistas usan el guard; ~55 asignan datos dentro (patrón legacy normal). Cruce vista↔controlador (render() $data): **2 bugs reales + 1 hardening + 2 falsos positivos**.
- **review.php (import M3U) — página MUERTA en FC**: el data-prep completo (~160 líneas: submit post_data → StreamService/MovieService::process, upload m3u_file → M3UParser + dedup + $rImport) estaba DENTRO del guard. Tanto `render()` como el fallback legacy de `index.php:425` setean `$__viewMode=true` → el guard es código muerto en AMBAS rutas → el import no hacía nada en el panel. **Fix**: data-prep movido fuera del guard (patrón proxies.php), layout header en segundo guard propio (preserva el orden legacy: redirect antes de output), redirects con fallback JS `headers_sent()` (no hay output_buffering en el runtime, implicit_flush=On). ReviewController sigue siendo stub deliberado.
- **theft_detection.php**: el controlador pasaba claves `theftDetection`/`range` pero la vista lee `$rTheftDetection`/`$rRange` → página rota en FC. Claves corregidas en `TheftDetectionController`.
- **servers.php**: `$rServers = getAll(true)` movido fuera del guard (funcionaba por coincidencia de filtros type=0, igual que proxies.php antes del fix).
- **Falsos positivos descartados**: dashboard.php `$i` (reasignado en body línea 393); line_activity.php `rSearchStream/rSearchUser` (uso con `isset()` — FC es incluso más seguro).
- **Side-effects en guard (scan adicional)**: 7 vistas con `header('Location')` dentro del guard (epg_view, ips, line, movie_mass, process_monitor, record, stream_review) — TODAS replicadas en sus controladores FC (IpController→flush, MovieMassController→massEdit, ProcessMonitor→clear/clear_s, StreamReview→submit, EpgView→mobile, Line→mag/e2, Record→validación). Migración FC completa, sin acción.
- **Deuda conocida (no urgente)**: RecordController:45 `header('Location: record')` sin exit (Location+200, browsers redirigen igual); data-prep en vistas viola Controller→Service (deuda del patrón legacy, revisar en futura migración MVC).

**Release 2.0.5 publicada**: bump en `AppConfig.php`, commit `b86daeb3`, tag 2.0.5, CI Build Release Assets (run 43563483851). Acumula: fix proxies.php (`4561e364`), usage update (`dfbf2831`), ExecRestart (`9d87ce41`) + todo lo de arriba.

**Pendiente para próxima sesión**: desplegar 2.0.5 al panel del usuario (178.156.194.122) — el update llega solo por el cron diario o manual via `console.php update update`; verificar que el reaper ampliado no interfiera con el proxy #4 en producción.

## 2026-11-14 — Lista de proxies vacía en el WebUI: bug de migración al Front Controller

**Problema del usuario**: el proxy instalado (Debian 13, funcionando y reportando) no aparecía en "Manage Proxies" — la tabla renderizaba tbody vacío.

**Diagnóstico (con SSH al panel del usuario, 178.156.194.122)**: la BD tenía el proxy con `server_type=1, status=1, enabled=1` y reportando (`last_check_ago` fresco); la cache de servers también lo tenía. El dato estaba — el render no.

**Causa raíz** (migración FC): `bootstrapAdminRuntime()` (`includes/bootstrap/admin_runtime.php:142`) pisa la **global** `$rServers` con `getStreamingSimple()` = `WHERE server_type = 0` (solo main+LBs; los proxies van en `$rProxyServers` aparte). La vista `proxies.php` tenía su propio `$rServers = ServerRepository::getAll(true)` pero DENTRO del guard `if (!isset($__viewMode))` — y `BaseAdminController::render()` setea `$__viewMode = true` antes de requerir la vista → la asignación se salteaba → el foreach iteraba la global pisada (solo type=0) → el filtro `server_type == 1` nunca matcheaba → lista siempre vacía. "Manage Servers" funcionaba de casualidad (su filtro type=0 coincide con la global pisada).

**Fix** (commit `4561e364`): en `proxies.php`, la asignación `$rServers = ServerRepository::getAll(true);` movida FUERA del guard `$__viewMode` — la vista se auto-provee la lista completa y fresca en ambos modos (FC y legacy). Verificado que el body solo usa variables cubiertas ($rUserInfo/$rSettings via extract del render, resto computado en el loop). `php -l` OK.

**Despliegue inmediato**: el proxies.php corregido se copió via SSH al panel del usuario (`/home/uypanel/public/Views/admin/proxies.php`, chown uypanel). Además se eliminó el fantasma #3 (PRX01_GE, `last_check_ago=0`, fila del primer intento con payload viejo) con backup en `/root/ghost_proxy_3_backup.sql` del panel; cache servers/proxy_servers limpiada. Estado final del panel del usuario: #1 main, #2 LB01_US, #4 PRX_de (proxy reportando).

**Nota de patrón**: otras vistas que asignen datos en el guard `$__viewMode` y los usen en el body heredan la global del bootstrap — revisar si alguna más quedó desincronizada (servers.php funciona por coincidencia de filtros).

## 2026-11-14 — Infraestructura del usuario (segundo panel) + cierre de sesión

- **Nuevo panel del usuario**: `178.156.194.122` (debian-2gb-ash-1, 2GB, Debian 13) — instalado fresco con 2.0.3, actualizado a 2.0.4. Acceso web con código propio (NO es el de prod). SSH: credenciales provistas ad-hoc por el usuario en el chat — NO registrarlas aquí ni en el repo.
- **Infra del nuevo panel**: MAIN (178.156.194.122, id 1) + LB01_US (5.161.219.208, id 2, Debian 13, instalado OK) + PRX_de (2.31.115.247, id 4, Debian 13, proxy instalado OK y reportando). El fantasma #3 (PRX01_GE, primer intento con payload viejo) fue eliminado con backup en `/root/ghost_proxy_3_backup.sql` del panel.
- **Verificación del proxy en vivo** (script de diagnóstico pasado al usuario): nginx escuchando 80/443 como uypanel, PHP 8.3.36, crontab del callback instalado, POST a `/admin/proxy_api` del main → HTTP 200 + [] (gate anti-probe pasado).
- **Docs**: FAQ (es + en) con nueva entrada "El servidor desapareció de la lista tras una instalación fallida" (comportamiento anti-fantasma 2.0.4 + log en `bin/install/<id>.install`).
- **SKILL.md**: releases actualizadas (2.0.4) + nueva sección "Gotchas del Front Controller" (patrón `$__viewMode`, global `$rServers` pisada, `console.php update` no-op).
- **Pendiente para próxima sesión**: revisar preventivamente otras vistas con asignaciones dentro del guard `$__viewMode` (el patrón roto de proxies.php); la release 2.0.5 acumulará: fix proxies.php (`4561e364`), usage de update (`dfbf2831`), ExecRestart (`9d87ce41`) — el fix de la vista ya está desplegado a mano en el panel del usuario.

## 🆕 2026-11-14 — Proxy (type 1): payload reconstruido — el viejo era upstream roto (PHP 8.1.33/xc_vm)

**Problema del usuario**: en su panel nuevo (2.0.3, Debian 13), agregar LB (type 2) funcionó pero agregar un PROXY (type 1) en Debian 13 falló.

**Causa raíz**: `src/bin/install/proxy.tar.gz` era un asset ESTÁTICO del repo (no lo rebuilda el Makefile) con contenido upstream de **octubre 2025**: binarios PHP **8.1.33** (EOL, sin parches) y nginx 1.28 con prefix `/home/xc_vm` compilado, `nginx.conf` con `user xc_vm` (usuario inexistente en instalación fresca → nginx ni arranca), `service` apuntando a `includes/startup.php` con rutas xc_vm, y en Debian 13 el PHP viejo muere por `libjpeg.so.8` (ABI 8 no existe en trixie). Verificado empíricamente en container debian:13 con la lista de paquetes type-1.

**Fixes** (commit `b017b028`):
- **proxy.tar.gz reconstruido desde el stack actual** (65.5MB): bin/php completo (PHP 8.3.36 hardened, prefix uypanel, etc/1-4.conf), bin/nginx 1.29.6 + conf mínima de proxy (nginx.conf proxy con `user uypanel`/`pool_uypanel`, options.conf del payload viejo, ssl.conf, certs, mime.types, dirs logs/temp), `service`/`includes/startup.php`/`crons/callback.php` uypanel-izados (el callback reporta stats al main vía `/admin/proxy_api` y recibe señales: restart, block_ip, reload_nginx...). Modos estilo Makefile (binarios 0551, dirs 750). Probado en container: php -v OK, lint OK, nginx -t OK, nginx arranca como uypanel y proxyea al main OK.
- **BalancerCommand type-1**: lista de paquetes + `libfreetype6` + `libmaxminddb0` (deps de GD compilado y de la extensión maxminddb — antes las traía `libharfbuzz-dev` que el proxy no instala); chequeo de extracción ahora REMOTO (`test -x bin/nginx/sbin/nginx` — el viejo chequeaba un archivo LOCAL del main y nunca podía fallar); type-1 ahora despliega binarios nativos por distro desde `bin/distros/` (igual que type 2 — necesario para proxies Ubuntu); bootstrap del proxy llama `includes/startup.php` como root (instala el crontab del callback) en vez del inexistente `console.php startup`.
- **Nota**: el endpoint `/admin/proxy_api` del main responde 404 a IPs no-proxy por diseño (anti-probe, `BlocklistService::isProxy`); el callback del proxy registrado pasa el gate. Arquitectura de proxies intacta.
- **Pendiente**: publicar release 2.0.4 para que los paneles instalados reciban el BalancerCommand nuevo + el proxy.tar.gz nuevo (viaja dentro del payload main en `bin/install/`). El panel del usuario puede también recibir el proxy.tar.gz a mano (scp a `/home/uypanel/bin/install/`) mientras sale la release.
- **Resuelto (mismo día)**: release **2.0.4** publicada (bump `ffdbca57`, CI run 43559600772 OK, 5 assets). Verificado el zip de la release: `UyPanel_VERSION = '2.0.4'` ✓, `bin/install/proxy.tar.gz` nuevo (65.5MB) dentro del payload ✓, callback.php sin referencias xc_vm ✓. El delta `update.tar.gz` (64.9MB) incluye el proxy.tar.gz nuevo. El usuario puede actualizar su panel nuevo a 2.0.4 (manual o esperando el cron diario de updates) y reintentar el proxy.
- **Proxy del usuario VERIFICADO FUNCIONANDO (mismo día)**: diagnóstico remoto (script pasado al usuario) sobre su proxy Debian 13 (2.31.115.247, panel en 178.156.194.122): servicio active, nginx como uypanel escuchando 80/443, PHP 8.3.36, nginx -t OK, crontab del callback instalado, y el POST a `/admin/proxy_api` del main responde **HTTP 200 + []** (gate anti-probe pasado, señales vacías). El journal distinguió los intentos: 03:55 = payload viejo ("Starting XC_VM...", 2.0.3), 05:15 = payload nuevo ("Starting UyPanel...", 2.0.4) — el segundo funciona. Lección: el comando `console.php update` a secas es no-op silencioso (espera subcomando `update update`); fix de usage message en `dfbf2831`. Limpieza cosmética: `ExecRestart` (directiva systemd inexistente, warning en cada daemon-reload) eliminada de los units generados por `install` y `BalancerCommand` (commit `9d87ce41`).

## 🆕 2026-11-14 — Anti-servers-fantasma: instalaciones fallidas se auto-eliminan del panel

**Problema del usuario**: servers LB con instalación fallida quedaban para siempre en la lista como fantasmas ("Down since: Always") — rows con `status=4` (fallo explícito) o `status=3` trabado (proceso de install muerto a mitad).

**Causa**: `BalancerCommand` en sus 7 rutas de fallo hacía `UPDATE servers SET status = 4` y `return 1` — la fila quedaba huérfana para siempre. Y si el proceso background moría sin llegar a ningún `return`, la fila quedaba en `status=3` ("Installing...") eternamente.

**Fixes**:
- `cli/Commands/BalancerCommand.php`: nuevo helper privado `failInstall($rServerID, $rMessage)` — borra el `.json` de credenciales SSH, llama `ServerRepository::deleteById()` (elimina la fila + datos relacionados) y devuelve 1. Las 7 rutas de fallo (tipo inválido, SSH connect, SSH auth, extract x2, MD5 x2) ahora usan el helper → el server desaparece de la lista al fallar. El log de instalación queda en `bin/install/<id>.install` para debug.
- `cli/CronJobs/ServersCronJob.php`: reaper en el bloque `is_main` — servers no-main con `status=4` (legacy) o `status=3` sin proceso `UyPanel Install[<id>]` vivo Y log `.install` con mtime > 300s → `deleteById`. Auto-sanativo de fantasmas existentes en otros paneles.
- `domain/Server/ServerRepository.php`: `deleteById()` ahora también hace `DELETE FROM signals WHERE server_id = ?` (higiene, aplica al borrado manual de la UI también).
- **Limpieza manual en prod**: eliminados servers fantasma #2 (LB01), #3 (PRX01) — ambos 208.110.84.125 — y #5 (EU-LB, 64.37.105.75), todos `status=4`/`last_check_ago=0` de instalaciones SSH-bloqueadas (firewall). Cero datos relacionados (streams_servers/lines_live/lines_activity/servers_stats = 0; 25 signals huérfanas eliminadas). Backup: `/home/debian/backups/ghost_servers_20261114.sql`. `sudo /home/uypanel/status` → "All servers are Online".
- **Verificación**: `php -l` OK en los 3 archivos; `console.php cron:servers` corre limpio con el reaper; expresión `pgrep -f 'UyPanel Install\[<id>\]'` validada (el título de proceso reemplaza el cmdline, pgrep -f lo encuentra).

## 🆕 2026-11-14 — Fix instalador: PHP sin `libcurl-gnutls`/`libsodium` en servers nuevos (502 Bad Gateway)

**Síntoma**: Instalación en server nuevo (Debian 13) terminaba con `php: error while loading shared libraries: libcurl-gnutls.so.4` y el panel devolvía **502 Bad Gateway** (php-fpm muere al arrancar).

**Causa raíz**: El binario PHP del payload (y php-fpm/phpdbg) está enlazado contra `libcurl-gnutls.so.4` porque el entorno de build instala `libcurl4-gnutls-dev` (`third_party/UyPanel_Binaries/build/deps_debian.sh:18`). El instalador principal (`install`) solo instalaba `libcurl4(t64)` (variante OpenSSL, NO provee esa lib). En producción funcionaba de casualidad: `git` está instalado y arrastra `libcurl3t64-gnutls` como dependencia.

**Diagnóstico sistemático**: Container Docker `debian:13` limpio + lista exacta de paquetes del instalador + `ldd` de los 20 ELF del payload → faltantes: `libcurl-gnutls.so.4` (paquete `libcurl3t64-gnutls`) y `libsodium.so.23` (paquete `libsodium23`), solo en php/php-fpm/phpdbg. El payload del LB (loadbalancer.tar.gz) tenía el mismo faltante de libsodium (el BalancerCommand ya agregaba libcurl3t64-gnutls desde un fix anterior, pero no libsodium23).

**Fixes aplicados** (sin commitear aún):
- `install`: añadidos `libcurl3-gnutls` + `libsodium23` a `rPackages`; lógica t64 condicionada correctamente (nombres t64 solo en Debian 13/Ubuntu 24.x; Debian 12 usa `libcurl4`/`libcurl3-gnutls`/`libssh2-1` — antes el instalador forzaba `libcurl4t64`/`libssh2-1t64` también en bookworm, donde no existen).
- `src/cli/Commands/BalancerCommand.php`: añadido `libsodium23` en las 3 ramas de distro; Debian 12 ahora usa `libcurl3-gnutls` en vez del t64 inexistente en bookworm.
- **Verificación**: container debian:13 fresco con la lista corregida → 0 libs faltantes en ambos payloads y `php -v` ejecuta (PHP 8.3.36). Container debian:12 → confirmado que `libcurl4t64`/`libcurl3t64-gnutls`/`libssh2-1t64` no existen y `libcurl4`/`libcurl3-gnutls`/`libsodium23`/`libssh2-1` sí. `php -l` OK en BalancerCommand; `py_compile` OK en install.
- **Pendiente**: rebuild del payload (`make main`) para que el fix llegue al `UyPanel.zip` de releases; commitear cambios. Quick-fix para el server afectado: `apt-get install -y libcurl3t64-gnutls libsodium23 && sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php startup && sudo systemctl restart uypanel`.
- **Resuelto (mismo día)**: commit `a9da3cff` pusheado a main; release **2.0.3** publicada (CI `Build Release Assets` OK en 2m56s, 5 assets: UyPanel.zip, loadbalancer.tar.gz, loadbalancer_update.tar.gz, update.tar.gz, hashes.md5). Verificado descargando el `UyPanel.zip` y `update.tar.gz` de la release: ambos contienen los fixes (libcurl3-gnutls/libsodium23 en install; libsodium23 en BalancerCommand). El server afectado fue formateado por el usuario; reinstalar desde la release 2.0.3.
- **Release 2.0.3 REHECHA (mismo día)**: la primera publicación salió sin el bump de `UyPanel_VERSION` (payload se autoreportaba 2.0.2 → los paneles quedarían en loop de "update disponible" porque el delta 2.0.2→2.0.3 no lleva AppConfig.php; detectado al revisar el SKILL.md, que documenta el bump como paso del proceso). Fix: bump a 2.0.3 (commit `7003911d`), release+tag viejos borrados (con OK del usuario), release recreada en HEAD → CI verde (run 43550007690). Verificado descargando el zip de la release final: `UyPanel_VERSION = '2.0.3'` dentro del payload.
- **SKILL.md actualizado (mismo día)**: código de acceso admin corregido (`x9access9x` → `7JDEeDMG`, vigente tras reinstalación 2026-11-04), versión del entorno 2.0.1 → 2.0.3, `docs/` en/ y es/ (ru/ eliminado), sección "Publicar releases" reescrita con el checklist obligatorio del bump + CI automático de assets, nota de deps runtime del instalador (libcurl3t64-gnutls + libsodium23).

## 2026-11-14 — Política de SO en docs: Debian 13 recomendado, versiones viejas deprecadas

A pedido del usuario (razón: vulnerabilidades/librerías viejas), se actualizó la documentación:
- `README.md`: sección renombrada a "Supported Operating Systems" — **Debian 13 (Trixie) = recomendado**, Ubuntu 24.04 LTS = soportado, Debian 12 = compatible (LTS), Ubuntu 22.04 y anteriores = *Legacy/Outdated — Not Recommended*, con sección "Why older releases are discouraged" (librerías del sistema desactualizadas + fin de parches; el PHP 8.3.36 hardened del payload no cubre el resto del stack). Quick Install ahora dice "Debian 13 (recommended) or Ubuntu 24.04".
- `docs/es/README.md` y `docs/en/README.md`: misma política en las guías publicadas.
- Limpieza: `python3-pip` quitado de las dependencias de instalación (el instalador solo usa stdlib de Python). Tabla de Technology Stack corregida a las versiones reales del payload (PHP 8.3.36 hardened, Nginx 1.29.6+RTMP, MariaDB 11.8).
- Commit `6ea2d6c8` pusheado a main (el sitio de docs se redespliega solo vía pages-build-deployment). El instalador sigue aceptando las versiones viejas (no se bloquean en el script); si el usuario quiere enforcement duro en el instalador, queda como pendiente opcional.



## 🆕 2026-11-14 — Eliminación completa del Stream Extractor

A petición del usuario (no quedó satisfecho con el resultado), se desactivó y eliminó por completo la aplicación de extracción de streams (`/stream_extractor`), tanto del servidor en producción como del repo fuente:

- **Servicio systemd**: `uypanel-extractor.service` detenido, deshabilitado, unit eliminado y `daemon-reload`. Puerto 3200 liberado.
- **App Node.js**: directorio `/home/uypanel/extractor/` eliminado (no formaba parte del payload del build; se había desplegado a mano).
- **Nginx**: bloque `location ^~ /stream_extractor/`, `@extractor_denied` y redirect 301 eliminados de `custom.conf` (prod y `src/infrastructure/nginx/custom.conf`). También se quitó `location = /internal_admin_auth` (solo lo usaba el extractor). `custom.conf` queda vacío con comentario; el `include` en `nginx.conf` se conserva. `nginx -t` OK + reload sin downtime.
- **Panel PHP** (prod `/home/uypanel` y repo `src/`): ruta en `public/routes/admin.php`, controlador `StreamExtractorController.php`, vista `stream_extractor.php`, entrada de menú en `Views/admin/header.php` y clave `stream_extractor` en los 7 idiomas (`includes/langs/*.ini`) eliminados.
- **Verificación**: `php -l` OK en todos los archivos editados; `http://198.27.81.133/stream_extractor/` → 404; `/7JDEeDMG/stream_extractor` → 302 (ruta inexistente, redirect home); login del panel intacto; `systemctl is-active uypanel-extractor` → not-found.
- **Backup rollback**: `/home/debian/backups/stream_extractor_removed_20261114.tar.gz` (código del extractor sin node_modules + unit + custom.conf + archivos del panel afectados).

## 2026-11-12 — Blindaje Crítico de Seguridad y Fix de Copiado de URLs

Tras la integración inicial, se detectaron y corrigieron de inmediato dos incidencias críticas reportadas por el usuario:

### 1. Blindaje de Seguridad Integral (Acceso No Autenticado Bloqueado):
- **Problema**: El proxy inverso `/stream_extractor/` permitía que cualquier visitante en internet accediera directamente sin pasar por el login del panel ni el código de acceso. Además, Node.js escuchaba en `0.0.0.0:3200` exponiendo el puerto en la interfaz pública.
- **Solución implementada (Defensa en Profundidad)**:
  1. **Binding Exclusivo a Localhost**: Se configuró `server.js` y el servicio systemd (`uypanel-extractor.service`) con `HOST=127.0.0.1`, asegurando que el puerto 3200 escuche única y exclusivamente en la interfaz de bucle invertido local (inacesible desde fuera).
  2. **Gate de Autenticación en Nginx (`auth_request`)**: Se añadió `auth_request /internal_admin_auth;` al bloque `location ^~ /stream_extractor/` en `custom.conf`. Toda petición HTTP entrante es interceptada y verificada mediante un subrequest FastCGI antes de permitir el proxy.
  3. **Script de Verificación de Sesión (`public/auth_check.php`)**: Valida la existencia de la cookie `PHPSESSID`, timeout de sesión, coincidencia de hash `verify` y que el usuario pertenezca al grupo de administradores (`is_admin: true`). Si no hay sesión o no es admin, devuelve HTTP 403 en microsegundos sin carga a la BD.
  4. **Anti-sondeo (Anti-Probe)**: Nginx intercepta cualquier 401/403 de `/stream_extractor/` y devuelve un **HTTP 404 Not Found** nativo idéntico al de Nginx (`error_page 401 403 =404 @extractor_denied;`). Un atacante o escaneador no autenticado no recibe ni el portal ni indicaciones de que la herramienta existe.
  5. **Verificación QA**:
     - Petición sin cookies a `http://127.0.0.1/stream_extractor/`: **HTTP 404 Not Found**.
     - Petición con sesión de Reseller (grupo 2): **HTTP 404 Not Found**.
     - Petición con sesión de Administrador (grupo 1): **HTTP 200 OK**.

### 2. Fix de Copiado de URLs al Portapapeles (Universal Clipboard Fallback):
- **Problema**: `navigator.clipboard.writeText` solo está disponible en contextos seguros (HTTPS o localhost). Al acceder al panel vía HTTP (`http://198.27.81.133/...`) o dentro de un `iframe`, `navigator.clipboard` es `undefined`, lo que provocaba que los botones "Copiar" fallaran en silencio con un error de JS.
- **Solución implementada**:
  - Implementada función universal `copyToClipboard(text, msg)` en `public/app.js` con:
    1. Verificación segura de `navigator.clipboard` si `window.isSecureContext`.
    2. Fallback mediante elemento `<textarea>` temporal con `document.execCommand('copy')` que funciona en el 100% de los navegadores en HTTP y dentro de iframes.
    3. Fallback interactivo con `window.prompt` en caso de restricción extrema del navegador.
  - Añadido `clipboard-read; clipboard-write` al atributo `allow` del `<iframe>` en `src/public/Views/admin/stream_extractor.php`.
  - Corregido `fullStreamUrl` en caso de URLs relativas del proxy para incluir `${API_BASE}`.

### 3. Actualización de Reglas de Seguridad en Skills (Prioridad Absoluta):
- Actualizados `/home/debian/.gemini/config/skills/uypanel/SKILL.md` y `.devin/skills/uypanel/SKILL.md` estableciendo la **Seguridad como Regla de Oro #1 Obligatoria**:
  - Prohibido exponer herramientas o proxies sin autenticación gate (`auth_request` / `requirePermission`).
  - Prohibido bindear servicios a `0.0.0.0` (estrictamente `127.0.0.1`).
  - Obligatorio responder con `404 Not Found` ante peticiones no autenticadas (anti-sondeo).
  - Control estricto de roles: herramientas administrativas restringidas a `is_admin: true`.

## 2026-11-12 — Integración de Radio & Stream Extractor en Gestión / Herramientas

Se integró exitosamente la aplicación [radio-stream-extractor](https://github.com/m4sterinfo/radio-stream-extractor) como una herramienta nativa accesible desde el menú del panel de administración (`Gestión` -> `Herramientas` -> `Extractor de Streams`).

### Componentes y Configuración Desplegados:
1. **Node.js LTS en Debian 13**:
   - Instalado Node.js 20.19.2 LTS y npm.
2. **Servicio y Demonio (`uypanel-extractor.service`)**:
   - Código desplegado en `/home/uypanel/extractor` (propiedad `uypanel:uypanel`).
   - Dependencias de producción instaladas con npm.
   - Demonio systemd `/etc/systemd/system/uypanel-extractor.service` corriendo en puerto 3200 con reinicio automático.
   - Soporte de subruta `/stream_extractor/` implementado en `server.js`, `lib/upstream.js` (`X-Forwarded-Prefix`) y `public/app.js` (`API_BASE`).
3. **Nginx Reverse Proxy**:
   - Bloque `location ^~ /stream_extractor/` configurado en `/home/uypanel/bin/nginx/conf/custom.conf` con soporte WebSocket, SSE y streaming directo.
   - Nginx recargado limpiamente sin downtime.
4. **Integración en Panel Admin UyPanel**:
   - **Ruta**: Registrada `$router->get('stream_extractor', [StreamExtractorController::class, 'index'])` en `src/public/routes/admin.php`.
   - **Controlador**: Creado `StreamExtractorController` en `src/public/Controllers/Admin/StreamExtractorController.php`.
   - **Vista**: Creada `src/public/Views/admin/stream_extractor.php` con contenedor responsivo iframe, botones de refresco y apertura en ventana completa.
   - **Menú**: Agregado enlace "Extractor de Streams" bajo el menú desplegable `Herramientas` en `src/public/Views/admin/header.php`.
   - **Traducciones**: Agregado `stream_extractor` a los diccionarios (`es.ini`, `en.ini`, `pt.ini`, `ru.ini`, `fr.ini`, `de.ini`, `bg.ini`).
5. **Verificación y QA**:
   - Sintaxis PHP validada con `tools/php_syntax_check.sh`: 627 archivos verificados, 0 errores.
   - Servicio Node.js verificado activo vía systemctl.
   - Endpoints `/stream_extractor/api/status` y extracción directa de streams verificados con HTTP 200 a través de Nginx y del Front Controller admin.

## 2026-11-12 — REVERT: vuelta a main + BD al estado del 2026-11-11

**Decisión del usuario**: el modelo multi-tenant (Super Admin / admins
scoped / Super Resellers) no convencía; se vuelve al modelo simple de main
(admins + resellers) y la BD queda como estaba.

### Qué se hizo
- **Repo**: `main` es la rama activa. La rama `super_admin` (6 commits:
  multi-tenant scoping, super admin, super resellers, access codes por
  categoría, fixes) queda PRESERVADA en git por si se quiere retomar.
- **Fix del loading CONSERVADO en main** (commit `273e5c26`): bug real del
  upstream 2.0.1 — las vistas reseller no llaman al footer y el controlador
  no lo renderizaba → páginas del panel reseller sin JS, colgadas
  "loading". Fix: `BaseAdminController::render` renderiza el footer en
  scope reseller y pasa los datos de la vista vía `$vars`;
  `layouts/footer.php` exporta las `$vars` al scope del footer.
- **Producción** (`/home/uypanel`): restaurados los 39 archivos modificados
  por la rama `super_admin` desde main + borradas las migraciones
  002/003 del directorio `migrations/` (si quedaran, el MigrationRunner las
  re-aplicaría). Verificado: los 39 archivos idénticos a main.
- **BD revert quirúrgico** (estado = backup `backup_2026-11-11_01:46:01.sql`):
  - Borrados usuarios 4,5,6,7,10,11 (QA multi-tenant), 12 (jferreira) y
    16 (yybTQKuDXd) — creados hoy; el usuario prefirió borrarlos.
  - Borradas líneas 8, 9, 12 (QA de hoy).
  - Borrados grupos 3 (QA Admins) y 4 (Super Resellers).
  - `ALTER TABLE users_groups DROP COLUMN is_super_admin`.
  - `access_codes`: borrado `nybPU7Cw` (id 5); `resellers` groups
    restaurado a `[2]` (hoy había quedado `[2,4]`).
  - `users_packages` groups restaurado a `[2]`.
  - `migrations`: borradas filas 002/003 (solo queda 001).
  - AUTO_INCREMENT reseteados (users=4, lines=8, groups=3, codes=5).
  - `users_logs` de los usuarios borrados, limpiados.
- **Nginx**: borrado `codes/nybPU7Cw.conf` + reload OK.
- **Backup de seguridad ANTES del revert**:
  `/home/debian/pre_revert_super_admin_2026-11-12.sql` (49 MB, estado con
  el experimento completo, por si se quiere rescatar algo).

### QA del estado revertido (curl, sesiones reales)
- Admin (grupo 1 temporal): login OK; dashboard/users/lines/settings/
  servers/groups 200 completos. (stream_providers 404 = normal en main,
  era página de la rama revertida.)
- Reseller (grupo 2 temporal): login OK vía código `resellers`; TODAS las
  páginas renderizan completas (42-43 scripts, `</body>`) gracias al fix
  del loading conservado; creación de línea E2E OK (crédito descontado).
- BD final = 3 usuarios (jadmin4407, qa_admin, qa_reseller), 7 líneas
  (1-7), 2 grupos (Administrators, Resellers), 4 access codes, 1 migración.
- Usuarios temporales de QA borrados tras la prueba.

### Nota para el futuro
- El modelo "cualquier reseller crea resellers y líneas" en main = activar
  `create_sub_resellers=1` + `subresellers='[2]'` en el grupo Resellers
  (decisión del usuario: por ahora NO — Resellers solo crea líneas, como
  estaba).
- El grupo Resellers (2) ya podía verse afectado por el bug del loading:
  con el fix conservado, el panel reseller funciona completo.

## ⏭️ CONTINUAR AQUÍ (próxima sesión)

1. **REINSTALACIÓN / ACTUALIZACIÓN DEL LOAD BALANCER POR EL USUARIO**:
   - El usuario actualizará el nodo LB (actualmente Ubuntu 20.04 con GLIBC 2.31) a una distribución moderna (Debian 12/13 o Ubuntu 22.04/24.04).
   - **Requisitos previos**: Verificar que el puerto SSH 22 del nodo LB esté abierto y accesible hacia el panel principal (`198.27.81.133`).
   - **Instalación del LB**: Desde la interfaz del panel (`Servers -> Add Server`) o ejecutando el comando CLI correspondiente.
   - **Despliegue automático verificado**: `BalancerCommand.php` ya está preparado para:
     1. Detectar automáticamente la distro del LB (`lsb_release -is` y `-rs`).
     2. Adaptar la lista de paquetes apt (librerías t64 para Debian 12/13 y Ubuntu 24.04, dependencias `libnuma1`, `libgomp1`, `libexpat1`, `librtmp1`, `libcurl3t64-gnutls`).
     3. Desplegar los binarios nativos compilados (PHP 8.3.36 + Nginx 1.29.6 con prefijo `/home/uypanel`) correspondientes desde `/home/uypanel/bin/distros/{debian_13|ubuntu_20|ubuntu_22|ubuntu_24}.tar.gz`.
   - **Prueba de FFmpeg moderno**: Una vez el LB esté en una distro moderna (Debian 12/13 o Ubuntu 24.04 con GLIBC >= 2.35), se puede cambiar `ffmpeg_cpu` a `v8.0` en Settings (`Streaming -> Preferences -> FFMPEG Version`) y verificar que los streams inicien y reproduzcan correctamente sin error de GLIBC.

2. **GIT & REPO STATUS**:
   - Hay 3 commits locales en `main` listos para push:
     - `4229374e`: docs(skill): record MariaDB 11.8.9 security upgrade and system packages update
     - `ac9802a7`: feat(settings): display MariaDB, Redis, FFmpeg and RTMP versions in info tab
     - `be3fe64a`: feat(installer): add multi-distro MariaDB 11.8 and native binary deployment for LBs
   - *Nota de protocolo*: NO pushear a `origin/main` sin instrucción explícita del usuario.

3. **SERVICIOS Y ESTADO ACTUAL EN SERVIDOR PRINCIPAL**:
   - MariaDB 11.8.9 LTS corriendo y asegurada (`max_connections = 400`).
   - Todos los paquetes vulnerables del sistema actualizados (`apt list --upgradable` = 0).
   - Pestaña Info en Settings muestra versiones activas de MariaDB, Redis, PHP, Nginx, Nginx RTMP y FFmpeg.
   - Binarios nativos multi-distro colocados en `/home/uypanel/bin/distros/`.
   - Payload LB (`dist/loadbalancer.tar.gz`) reconstruido y al día.

4. **Limpieza de artefactos QA** (confirmar con el usuario): usuarios
   `qa_admin`/`qa_reseller`, línea `lbtest1` (creada para la prueba del LB),
   access codes `qaResT3st` (tipo 1) y `qaApiT3st` (tipo 3 — expone la REST
   API, borrar antes de producción). Tras borrar códigos: `console.php tools
   access` + verificar.
5. **QA de playback VOD** (la biblioteca YA existe: 678 películas + 123
   episodios vía Watch Folder): reproducir una película y un episodio desde
   el panel/admin y con una línea real (vauth + symlink directo).
6. **QA pendiente** (mismo método: setup + REST API + panel + playback):
   MAG/enigma2, trials (el grupo 2 tiene total_allowed_gen_trials=0 —
   habilitar para probar), sub-resellers, bouquets múltiples, EPG
   (re-importar: BD fresca sin URLs de EPG).
7. **Panel (lado del usuario)**: revisar Settings (defaults del seed),
   re-importar EPG.
8. **Limpieza pendiente**: borrar `/home/uypanel.pre_reinstall` (rollback de
   la instalación anterior) cuando el usuario confirme; payloads viejos de
   /home/debian (~895 MB) si quiere.


## Identidad

- **Nombre**: UyPanel
- **Base**: fork de XC_VM tag `2.0.1` (commit `e21f9063`), historial git completo conservado
- **Rama de trabajo**: `main` (nuestra)
- **Repo propio (origin)**: https://github.com/m4sterinfo/UyPanel (público, AGPL-3.0)
- **Último commit pusheado**: `4b861388` (docs: translate Russian documentation to Spanish)
- **Upstream original**: Vateron-Media/XC_VM — NO está configurado como remote;
  añadir como `upstream` solo si se quiere mirar fixes (`git remote add upstream ...`)
- **Auth GitHub**: gh CLI autenticado como `m4sterinfo` (credenciales de git vía gh)
- **Identidad git (config local del repo)**: m4sterinfo <jferrjferreira@masterinfo.org>
- **Licencia**: AGPL-3.0 (heredada, mantener — NO cambiar a GPL-3.0)

## Entorno

- Servidor: Debian 13 (trixie), 8 cores, 32 GB RAM, 1.7 TB disco, IP `198.27.81.133`
- Panel instalado en `/home/uypanel` (renombrado desde /home/xc_vm), servicio
  systemd `uypanel.service`, usuario/grupo sistema `uypanel` (uid 105)
- BDs MariaDB: `uypanel` + `uypanel_migrate` (renombradas por RENAME TABLE)
- BD migrada desde XUI el 2026-11-02 (27,672 líneas, 240,557 streams, 2,087 users)
- MariaDB 11.8.9 (actualizado 2026-11-07; repo oficial MariaDB Foundation trixie),
  `max_connections = 400` en /etc/mysql/mariadb.cnf
- Acceso admin: `http://198.27.81.133/x9access9x` (código migrado del panel antiguo)
- Credenciales MariaDB: `/home/debian/credentials.txt` (NO commitear nunca)
- Backups: `/home/debian/pre_rename_backup/dbs_pre_rename.sql.gz` (147 MB, pre-rename)
  y `/home/debian/backup_2026-11-02_15_16_01.sql` (1.2 GB, XUI original)
- **Binarios propios (2026-11-03)**: PHP 8.3.36 + nginx/nginx_rtmp 1.29.6 con
  prefix `/home/uypanel` compilado — NO requieren symlink. Rama de build:
  `build/debian13-php83-uypanel` en third_party/UyPanel_Binaries (`b224e4b`).
  Docker necesario para recompilar (`sudo ./build_all.sh debian13`; borrar
  out/debian_13.tar.gz para forzar rebuild).

## Decisiones tomadas

| Fecha | Decisión | Motivo |
|-------|----------|--------|
| 2026-11-02 | Base del fork = tag 2.0.1 (no main) | Coincide con el panel en producción y la DB migrada |
| 2026-11-02 | Conservar historial git upstream | Permite diff/cherry-pick de fixes del original |
| 2026-11-02 | Binarios/FFmpeg como submódulos en `third_party/` | Repo ligero, actualizables, sin vendor de binarios |
| 2026-11-02 | Instalador con soporte Debian 12/13 | El original 2.0.1 solo soportaba Ubuntu |
| 2026-11-02 | Identificadores técnicos en minúsculas (`uypanel`) | Convención Linux (el original usaba minúsculas para lo técnico) |
| 2026-11-02 | Copyrights del original conservados | Obligación legal AGPL al hacer fork |
| 2026-11-02 | Telemetría al original desactivada | Separación total; los logs quedan locales |
| 2026-11-03 | Packs de idioma ru/bg conservados | Son traducciones runtime para usuarios finales, no docs |

## Registro de cambios (nuestros)

### 2026-11-02 — Instalación y migración de datos
- XC_VM 2.0.1 instalado en Debian 13 con instalador parcheado (soporte Debian
  12/13: detección de distro, paquetes t64, MariaDB/MaxMind del sistema,
  libcap2-bin). Librerías del PHP incluido resueltas: `libcurl3t64-gnutls` +
  `libjpeg.so.8` ABI 8 (de .deb Ubuntu) en `/usr/local/lib`.
- redis.conf: eliminada `lazyfree-lazy-server-delay` (KeyDB 6.3.4 no la soporta).
- BD XUI (1.2 GB, 66 tablas) restaurada en `xc_vm_migrate` y migrada con
  `console.php migrate`: 27,672 lines, 2,087 users, 240,557 streams,
  239,594 streams_servers, 5,062 series, 220,005 episodes, 57 bouquets,
  219 categorías, 18 servers, 22 EPG URLs, 62 packages, 12 groups.
- Optimización usada durante la migración: `SET GLOBAL
  innodb_flush_log_at_trx_commit=2` (13.5x más rápido); revertido a 1 al terminar.

### 2026-11-02 — Creación del fork UyPanel
- Fork desde tag `2.0.1` con historial completo; rama `main` propia.
- Submódulos: `third_party/UyPanel_Binaries` (@ `ff034e5`),
  `third_party/UyPanel_FFMPEG` (@ `43b01ca`) — forks propios de upstream renombrados.
- Skill `.devin/skills/uypanel/` creado (SKILL.md + STATE.md).
- Repo GitHub creado (se creó con errata "UyPnel" → renombrado a UyPanel) y
  pusheado. Initial commit de GitHub (LICENSE GPL-3.0 incorrecta) reemplazado
  por force push con el historial del fork (AGPL-3.0 correcta).

### 2026-11-02 — Rebranding completo y separación total del original
- Barrido en 176 archivos: `XC_VM` → `UyPanel`, `Vateron-Media` → `m4sterinfo`
  (README, docs, UI, langs, CLI, Makefile, instalador, workflows, test_installer).
- Repos de soporte propios (forks renombrados): `m4sterinfo/UyPanel_Update`
  (changelog, GeoLite lists, api_server), `UyPanel_Binaries`, `UyPanel_FFMPEG`.
- Git LFS: 20 archivos (~665 MB) — objetos descargados del LFS del original;
  al pushear se suben al LFS propio. Añadidos GeoLite2-City/Country (extraídos
  del payload oficial 2.0.1) — eran los únicos archivos del payload no trackeados.
- Telemetría desactivada: `CertbotCronJob` ya no llama a
  `DiagnosticsService::submitPanelLogs()`.
- Constantes de update (`src/core/Config/AppConfig.php`): `GIT_OWNER=m4sterinfo`,
  `GIT_REPO_MAIN=UyPanel`, `GIT_REPO_UPDATE=UyPanel_Update`.
- Release 2.0.1 publicado: https://github.com/m4sterinfo/UyPanel/releases/tag/2.0.1
  (UyPanel.zip 435 MB + loadbalancer.tar.gz 292 MB + hashes.md5). El workflow
  build-release se disparó solo y subió un XC_VM.zip del código original —
  eliminado del release; assets re-subidos a mano.

### 2026-11-02 — Renombrado técnico en producción (xc_vm → uypanel)
- Repo: renombradas rutas con `xc_vm` en el nombre (ministra/template/uypanel,
  nginx realip_uypanel.conf) y barrido de identificadores técnicos en 143
  archivos: `/home/uypanel`, servicio `uypanel`, BDs `uypanel`/`uypanel_migrate`,
  usuario/grupo `uypanel`, tarball `uypanel.tar.gz`.
- Servidor migrado: BDs por RENAME TABLE + grants nuevos + drop viejas; usuario/
  grupo renombrados; directorio movido; payload rebrandeado desplegado;
  config.ini (database=uypanel); redis.conf rutas nuevas; crontabs root y
  uypanel actualizados (el del root tiene chattr +i — quitar/poner para editar);
  fstab tmpfs rutas nuevas; unidad `uypanel.service`; setcap nginx;
  message_of_day "Welcome to UyPanel".
- Logo propio: `docs/_media/UyPanel_logo.jpg` (de uy.v2apk.com/logos/
  UyPanel_logo.jpg), referenciado en los 3 READMEs.
- Logos del panel reemplazados (admin + player, logo.png y logo-topbar.png):
  procesados desde el logo propio (recorte 650x640 + fondo oscuro a
  transparencia vía GD). Desplegado en el servidor.
- Favicon regenerado (ICO 32x32+16x16 desde el icono "U") — la primera versión
  tenía offsets mal (22 en vez de 38) y el navegador la ignoraba; corregido.
- Donaciones (Settings > Info, `src/public/Views/admin/settings.php`):
  BTC `bc1qsgsmx8nv03elxypklj4zw5rtaxd9707a0ne0zf`, ETH
  `0x1cc44467FC38aF023463CA71AA9F26A7Bc771872`, USDT **TRC-20**
  `TEVJABnsenDEDVba5teFJdVksppmrc3dVg`; fila Litecoin eliminada. QR dinámico.
- Eliminado `login-bg.webm` (branding original) y su referencia en la vista.

### 2026-11-03 — Seed database.sql rebrandeado + release actualizado
- `database.sql` es LFS: al hacer los barridos era un puntero (grep lo saltó).
  Corregido: placeholder `{XC_VM_ID}` → `{UyPanel_ID}` (el código de
  PlaylistGenerator ya esperaba el nuevo — sin este fix la generación de
  playlists en instalaciones nuevas mostraba el literal), `server_name` y
  `Welcome to UyPanel`.
- BD de producción: escaneo de 322 columnas de texto — única referencia
  residual en `output_devices` (plantilla M3U Plus: `xc_vm-id="{XC_VM_ID}"`),
  corregida en caliente. Playlists funcionando en producción.

### 2026-11-03 — Documentación traducida al español
- Traducidos: `MIGRATION.md`, `ARCHITECTURE.md`, `specs/MODULE_SYSTEM_SPEC.md`
  (en ruso) y el sitio `docs/ru/` (18 archivos) → nuevo sitio `docs/es/`;
  `docs/ru/` eliminado.
- Docsify: `index.html` con ruta `/es-es/`, redirect raíz → guía española,
  `_navbar.md` con switcher Español/English.
- README: enlaces de docs a `m4sterinfo.github.io/UyPanel` (español primero).
- `tools/run_scan.sh`: quitada la ruta hardcodeada del disco del autor original.

### 2026-11-03 — Comentarios en ruso traducidos al español
- Barrido completo: 1.574 líneas en 179 archivos PHP + archivos no-PHP
  (lb_configs/nginx.conf, src/bin/nginx/conf/nginx.conf, test_installer,
  .github/workflows/release-notifier.yml, tools/test-install/*,
  tools/install_xdebug.sh, tools/test_player_api.sh,
  tools/update_top_contributors.py, tools/test-install/README.md).
- Método: inventario por archivo:línea → dicts de traducción (Python) →
  aplicador que preserva la indentación; php -l OK en los 179 archivos.
- Incluye los 6 strings rusos: mensajes de excepción de ServiceContainer y
  un console.error en stream_categories.php.
- Conservados a propósito (datos funcionales, no comentarios): packs de
  idioma ru/bg (src/includes/langs/*.ini), mapa de transliteración y teclado
  cirílico del portal MAG (ministra/global.js, layer.search_box.js), nombres
  nativos de idiomas (ministra/external/settings/g_play.html).
- Commit: `037f50f6` (196 archivos, 3926 líneas cambiadas 1:1). Pusheado.

### 2026-11-03 — Push + GitHub Pages activados
- Push de `689474bd..3cb56994` a origin/main (traducción de comentarios + STATE.md).
- GitHub Pages activado vía API (source: rama `main`, carpeta `/docs`).
  Sitio: https://m4sterinfo.github.io/UyPanel/ — verificado: raíz 200
  (redirect a guía española), ruta `#/es-es/` 200, `es/README.md` 200.

### 2026-11-03 — Binarios reconstruidos con prefix uypanel + PHP 8.3.36 (fix PHP2Shell)
- **Symlink `/home/xc_vm` ELIMINADO** — los binarios nuevos tienen el prefix
  `/home/uypanel` compilado dentro (php ini path/extension_dir, nginx prefix).
- Build 1 (generación de la época): submódulo pineado a `42d5096` (PHP 8.1.34)
  + parche prefix → rama `build/debian13-uypanel-prefix` (83fdff6, luego 9014732
  con pin nginx 1.29.6 + flv 1.2.13 tras fallar nginx 1.31.8 contra flv 1.2.14:
  `NGX_USE_IOCP_EVENT` undeclared). Desplegado y verificado.
- Build 2 (generación nueva): submódulo `ff034e5` (PHP 8.3.36, pecl pins,
  xcvm_extention 2.5.2 Rust ya sincronizado en el fork) + prefix + mismo pin
  nginx → rama `build/debian13-php83-uypanel` (`b224e4b`). Desplegado.
- **Motivo del salto a 8.3: PHP2Shell** (CVE-2026-109618/109619, RCE crítico
  no autenticado, explotado activamente; parcheado 21-oct-2026 en 8.2–8.5).
  PHP 8.1 EOL desde dic-2025 → sin parche posible. 8.3.36 = parcheado.
- Hardening añadido a php.ini: `session.upload_progress.enabled = Off`
  (vector del PoC público; el panel no usa barras de progreso).
- Compatibilidad 2.0.1 con 8.3 verificada: console.php status OK, lint de
  todo src OK (único fallo: doc de igbinary, no ejecutable), login 200 sin
  warnings, 4 pools + nginx 80/443 OK, DB OK, JPEG OK (WebP FAIL = igual que antes).
- `utf8_decode()` (deprecado en 8.2) reemplazado por `mb_convert_encoding()`
  en api.php (commit `db5763a6`).
- Docker instalado en el host (docker.io 26.1.5 de Debian 13) para los builds.
- Backups: `/home/debian/bin_backup_2026-11-03/` (php/nginx/nginx_rtmp 8.1.33
  originales + `php_8.1.34/` intermedio). Rollback = parar panel, restaurar
  bin/, arrancar (requiere recrear symlink /home/xc_vm si se vuelve a 8.1.33).
- Nota: el nuevo PHP enlaza contra libjpeg.so.62 NATIVA de Debian 13 — el hack
  de libjpeg.so.8 en /usr/local/lib ya no es necesario para los binarios nuevos
  (se conserva por si se hace rollback a los binarios viejos).
- Nota: el puerto 80 lo bindea uypanel vía sysctl
  `net.ipv4.ip_unprivileged_port_start=0` (/etc/sysctl.d/50-allports-nonroot.conf),
  NO vía setcap.
- Submódulo repuntado a `b224e4b` en el repo principal.

### 2026-11-04 — FIX CRÍTICO: demonios duplicados agotaban MariaDB (199 CacheHandler)
- **Síntoma**: `Too many connections` en todo el panel; 200/200 conexiones
  MariaDB en `Sleep`, `Aborted_connects` 16k+. Signals/Watchdog/Queue/Network
  a 0 procesos (morían al no conseguir conexión); admin y player API degradados.
- **Causa raíz (bug heredado del upstream 2.0.1)**: `cli_set_process_title()`
  REEMPLAZA el cmdline en `ps` → los greps del cmdline nunca encuentran los
  demonios ya titulados:
  1. `ServersCronJob` (cada minuto): `grep cache_handler` cuenta 0 aunque haya
     199 vivos → lanza OTRO cada minuto.
  2. `DaemonTrait::killStaleProcesses('console.php cache_handler')` no matchea
     el título `UyPanel[CacheHandler]` → las instancias viejas nunca mueren.
  3. Bonus: la lista de PIDs de awk va separada por `\n` → `kill -9 123\n456`
     solo ejecuta el primero (sh trata el resto como comandos).
- CacheHandler es el único demonio de bucle infinito real (Watchdog y Queue
  tienen `break` incondicional tras 1 iteración y se relanzan solos — diseño
  upstream, no leak). Signals lo mata cada minuto RootSignalsCronJob
  (`pgrep -f 'UyPanel\[Signals\]'`) y hace el trabajo él mismo.
- **Fix** (commit `32c6707e`, desplegado a /home/uypanel): títulos en
  minúscula `UyPanel[cache_handler|watchdog|queue|scanner]` (los greps
  existentes en ServersCronJob/api.php siguen funcionando sin tocarlos),
  `killStaleProcesses('cache_handler')` (matchea título Y cmdline) y kill en
  una sola línea. Signals SIN tocar (interplay con el cron de root).
- **Mitigación**: `pkill -9 -f 'UyPanel\[CacheHandler\]'` (los 199) +
  `SET GLOBAL max_connections=400` + persistido en /etc/mysql/mariadb.cnf.
- **Verificado**: 1 solo `UyPanel[cache_handler]` estable (>7 min), watchdog
  ciclando por diseño, Threads_connected 5/400, `status 1` OK (DB +
  migraciones), admin 200/302, player API 200 con credenciales reales
  (`"auth":1` — los 404 con credenciales falsas son anti-probe por diseño;
  verificado con usuario real: `"message":"Welcome to UyPanel"`).
- Lección: al tocar títulos de proceso, TODOS los greps de detección
  (ServersCronJob, api.php, process_monitor) deben matchear el título real.

### 2026-11-04 — Reinstalación limpia validada desde el repo (tras BD vaciada)
- El usuario restauró a mano un backup antiguo sobre la BD `uypanel` y quedó
  VACÍA (0 tablas) — sin datos importantes dentro (confirmado por él; nos
  separamos del proyecto original). Decisión: **reinstalación limpia desde
  NUESTRO repo** para validar el camino de despliegue completo.
- Build fresco: `make main` → `dist/UyPanel.zip` 451 MB desde HEAD (incluye
  video login, comentarios ES, todos los fixes). Instalador del repo
  (`install`) + payload a `/home/debian/reinstall/` (borrado tras instalar).
- Limpieza previa: stop uypanel, umount tmpfs (content/streams, tmp),
  `/home/uypanel` → `/home/uypanel.pre_reinstall` (rollback; borrar cuando se
  confirme). Crontabs se reinstalan solos vía `console.php startup` ✓.
- **GAP de despliegue #1 (FIX en instalador, commit aparte)**: el instalador
  escribía redis.conf con `requirepass #PASSWORD#` LITERAL (nunca reemplazado)
  y el seed trae `redis_password=''` → Redis roto de fábrica. Ahora el
  instalador genera password de 64 chars, lo escribe en redis.conf Y hace
  UPDATE settings.redis_password. Verificado: auth+ping true vía extensión
  redis del panel.
- **GAP de despliegue #2 (conocido, workaround aplicado)**: el payload trae los
  binarios ORIGINALES de upstream (PHP 8.1.33) con prefix `/home/xc_vm`
  compilado → en nuestro fork (instala en /home/uypanel, SIN symlink) el PHP
  no encuentra php.ini ni configs → sin extensiones (fatal igbinary en
  autoload) y pools caídos. El upstream no lo sufre porque instala en
  /home/xc_vm. Solución aplicada: desplegar `out/debian_13.tar.gz` (PHP
  8.3.36 + nginx 1.29.6, prefix uypanel) sobre bin/ + `chown -R uypanel:uypanel
  /home/uypanel/bin` (el tar restaura owner interno del build — hacer chown
  SIEMPRE tras extraer binarios). **PENDIENTE DE DECISIÓN**: reemplazar los
  LFS de src/bin/{php,nginx,nginx_rtmp} por nuestros builds para que el
  payload instale correcto de fábrica (afecta release assets; trade-off Ubuntu).
- **GAP #3 (menor)**: `tar -tvf` del payload confirma que php.ini SÍ va en
  `bin/php/lib/php.ini` (no en etc/) — el binario lo busca ahí.
- Resultado: panel fresco operativo — código de acceso NUEVO generado e
  insertado por el instalador: **`7JDEeDMG`** → `http://198.27.81.133/7JDEeDMG`
  (el anterior `x9access9x` quedó en el backup movido; la BD es nueva). Tabla
  `users` vacía → el primer acceso muestra el flujo setup (SetupController)
  para crear el admin. Credenciales MariaDB nuevas en
  `/home/debian/credentials.txt` (600). BDs recreadas de cero por el
  instalador (uypanel + uypanel_migrate vacía — el origen XUI sigue en
  `/home/debian/backup_2026-11-02_15_16_01.sql` por si hiciera falta).
- Verificación completa: 4 pools, status 1 OK (DB + migraciones), admin 302→
  login 200 (41 KB), redis auth OK, video login desplegado (1,432,592 B),
  crontabs root 2 + uypanel 23, demonios singleton (1 cache_handler, 1 queue,
  1 watchdog — el fix del ciclo de vida se valida en instalación fresca ✓).

### 2026-11-04 — Bugs del panel fresco: idioma bg + bouquet NULL + release 2.0.2
- **Bug #1 — menú en búlgaro al guardar perfil** (reportado por el usuario:
  "cambié el tema y el menú quedó en ruso"): `verifyPostTable()` persistía
  verbatim los `column_default` de information_schema — MariaDB ≥ 10.2
  devuelve los defaults de string ENTRECOMILLADOS (`'en'`) → el admin creado
  por setup quedó con `lang = 'en'` (con comillas) → el select de idioma del
  perfil no matcheaba → select2 caía al primero alfabético (`bg`) → al guardar
  el perfil (para el tema) se escribió `lang='bg'` → menú en cirílico. Fix:
  normalizar defaults entrecomillados en `verifyPostTable` (commit `eac4a201`,
  desplegado). Fila del admin sanada a `lang='es'`.
- **Bug #2 — panel en blanco tras crear un bouquet vacío** ("todo empezó a
  fallar al crear el bouquet test"): el bouquet se crea con
  bouquet_channels/movies/radios/series = NULL (nullable sin default) y
  `BouquetService::getAll():197` hacía `array_merge(json_decode(NULL)...)` →
  TypeError → el boot del contexto admin fallaba en CADA petición (catch →
  camino legacy) y el legacy moría después → páginas admin en blanco (200 con
  0 bytes). Fix: cast `(array)` en los 5 json_decode (commit `bac2def5`,
  desplegado) + fila del bouquet sanada a `'[]'`. Login verificado: 200 con
  8,848 bytes y HTML correcto.
- **Falso positivo — mapa mundial**: la consola del navegador spameaba
  `scale(NaN)` de jvectormap; el `world-mill-en.js` SIEMPRE estuvo en repo y
  payload (un `find` truncado me confundió). Es ruido cosmético del widget
  cuando el contenedor mide 0 (típico del preview del IDE). Sin cambio.
- **Release 2.0.2 publicado**: https://github.com/m4sterinfo/UyPanel/releases/tag/2.0.2
  - Payload con NUESTROS binarios (PHP 8.3.36 + nginx 1.29.6, prefix uypanel)
    — commit `6225c356` (317 archivos: binarios reemplazados, extensiones 8.1
    fuera, headers mbstring 8.3 dentro, ioncube 8.3, php.ini con hardening
    session.upload_progress Off — antes solo vivía en el desplegado).
  - CI: `build-release.yml` ahora hace fetch LFS (commit `a88e3069`) — sin eso
    los assets salían con punteros rotos.
  - **18 objetos LFS nunca se subieron al repo** (solo 4 el Nov 2) — el primer
    run del workflow falló con 404 del LFS; subidos con `git lfs push origin
    2.0.2` (el `--all` falla: el historial referencia versiones viejas sin
    objetos locales — usar push por ref).
  - Tag movido a `bac2def5` (force) para que los assets incluyan el fix del
    bouquet; workflow re-lanzado.
  - `release-notifier.yml` ELIMINADO (commit `1ff29b33`): notificaba al canal
    de Telegram del original y el fork no tiene esos secrets (fallaba siempre).
  - Versión: `UyPanel_VERSION` 2.0.1 → 2.0.2 (commit `308d5f20`).
- Estado del servidor tras todo: panel operativo (login OK, admin `jadmin4407`
  lang es), demonios singleton, BD sana. Pendiente del usuario: revisar Settings
  (defaults del seed), EPG, crear users/streams si quiere.

### 2026-11-04 — Incidente: `chmod -R 777 /home` externo + restauración de permisos
- A las 01:19:47 alguien ejecutó `sudo chmod -R 777 /home` desde una TTY
  interactiva (pts/1, PWD=/home/debian) — NO fue el agente (journalctl lo
  registra: `sudo[2819115]: root : TTY=pts/1 ; COMMAND=/usr/bin/chmod -R 777 /home`).
  **CONFIRMADO por el usuario (2026-11-04): fue él, modificando permisos de
  carpetas vía SSH.** Efectos:
  todo /home a 777 → git veía ~6,000 archivos "modificados" (exec bit),
  `~/.ssh` con perms abiertos (sshd rechaza claves así), credentials.txt,
  server.key de nginx y sockets php-fpm expuestos a cualquier usuario local.
- **Restaurado**:
  - `~/.ssh` → 700, contenido 600, dueño debian (CRÍTICO para SSH).
  - `credentials.txt` → 600.
  - Repo git + submódulos: modos restaurados desde el índice
    (`git ls-files -s`: 100644→644, 100755→755, dirs 755) → `git status` limpio.
  - `/home/uypanel`: modos canónicos aplicados desde los tarballs REALES del
    payload (`dist/UyPanel.zip → uypanel.tar.gz`, 6,321 entradas) y de
    binarios (`third_party/UyPanel_Binaries/out/debian_13.tar.gz`, 597) —
    p. ej. `status/update/service/tools` 770, binarios nginx 550, dirs
    bin 750. Runtime no cubierto por tarballs: dirs 755, files 644
    (309,487 en content/streams tmpfs incluido), sockets 660 (= listen.mode
    de los pools; se recrearán solos igual al reiniciar php-fpm).
- **Verificado tras restaurar**: uypanel active, 4 pools, admin 302, player
  API 200 con credenciales reales, `status 1` OK, `daemons.sh` 770 OK.
  Únicos ELF sin exec: respaldos inofensivos (redis-server_6.2.2 viejo,
  ioncube_loader_lin_8.1.so no cargado — las .so no necesitan exec).
- **Lección**: NUNCA `chmod -R 777 /home` — rompe SSH y expone secretos; para
  poder editar archivos concretos usar `chown debian:debian <archivo>` (como
  ya se hizo con login-bg.mp4). Los modos canónicos del desplegado viven en
  los tarballs del payload, no en el índice git (los entry scripts son 644 en
  git pero 770 desplegados).

### 2026-11-04 — Fix: la notificación de updates nunca aparecía (cron:update roto en CLI)
- **Síntoma** (usuario): panel en 2.0.1 sin aviso del release 2.0.2 publicado.
- **Causa raíz (heredada del upstream 2.0.1)**: `cron:update` (único escritor de
  `settings.update_data`, de donde sale el icono del header y la tarjeta de
  Settings) lee la global `$gitRelease`, que SOLO se inicializaba en el bootstrap
  web (`src/www/init.php:45`). En CLI era siempre null → el cron salía en
  silencio con "GitRelease service not initialized" (exit 1; visible en
  tmp/logs/error_log.log, entradas base64). Además el cron corre `0 0 * * *`
  (medianoche) y los crontabs se instalaron ~02:20 → jamás había corrido.
- **Fix**: `XC_Bootstrap::boot(CONTEXT_CLI)` ahora crea `$gitRelease` global
  tras initCoreUtilities (commit `646d63fa`, desplegado a /home/uypanel/bootstrap.php).
- **Verificado**: `console.php cron:update` → "Update is available!",
  caché `tmp/gitapi_UyPanel_stable` creada, `settings.update_data` =
  `{"version":"2.0.2","changelog":[],"url":".../releases/tag/2.0.2"}` → icono
  de update visible en el header (version_compare 2.0.2 >= 2.0.1 ✓).
- **changelog.json**: `m4sterinfo/UyPanel_Update` NO tenía changelog.json
  (404; el original XC_VM_Update tampoco — changelog vacío era lo normal).
  Creado en el clone local `/home/debian/UyPanel_Update` (commit `dc04e3e`,
  SIN pushear aún): entradas 2.0.1 y 2.0.2 en español, orden ASCENDENTE
  (obligatorio: el filtro del cron recorre el array invertido y corta al
  llegar a la versión actual). Formato: `[{"version","changes":[...]}]`.
- **Flujo de update completo verificado**: release 2.0.2 estable con assets
  `update.tar.gz` + `hashes.md5` (md5 de update.tar.gz incluido) → el botón
  "Update Server" de Settings puede aplicar la 2.0.2 al panel 2.0.1.

### 2026-11-04 — QA: líneas/resellers/playback + 3 bugs corregidos
- **Setup de QA** (artifacts en BD, borrar cuando el usuario decida): admin de
  prueba `qa_admin` (grupo 1, api_key 32-hex), reseller `qa_reseller` (grupo 2,
  10 créditos, creado vía REST API `create_user`), líneas `qa_line1..4`
  (reseller) + `qa_adminline` (REST `create_line`). Access codes de prueba:
  `qaResT3st` (tipo 1) y `qaApiT3st` (tipo 3, REST API) — PENDIENTE borrar.
  El usuario ya tenía: código reseller `resellers` (tipo 1), package "test"
  (users_packages id 1: is_line, 1 crédito/30 días, groups [2], bouquets [1]),
  bouquet "test" (canal 1), canales Bein Xtra (id 1) + RT France (id 2),
  línea `josematest` (admin).
- **Resultados**: login reseller ✓, dashboard ✓, creación de líneas por
  reseller ✓ (créditos 10→9→8..., users_logs correctos), playback TS y HLS
  ✓ para TODAS las líneas (qa_line1/2, josematest, qa_adminline — 200
  video/mp2t ~4 Mbps / playlist HLS válida), lines_live tracking ✓ y reap
  automático al desconectar ✓. `api?action=connections` del reseller da
  result:false — CORRECTO (grupo 2 tiene reseller_client_connection_logs=0).
- **Bug #4 — assets del panel reseller fuera del payload** (commit `86dacde6`):
  `src/public/assets/reseller` era symlink ABSOLUTO a /home/xc_vm (upstream) →
  roto en nuestro prefix; el cp del Makefile lo saltaba → el panel reseller
  salía sin CSS/JS. Fix: symlink relativo `admin` + Makefile cp -P (preserva
  symlinks; el loop de update acepta -L). Desplegado a mano en el server.
- **Bug #5 — login reseller HTTP 500** (commit `baa567ad`): las páginas login
  son noBootstrapPages (el FC no carga bootstrap) y el ResellerLoginController
  migrado no se auto-bootstrappeaba → $db/$language null → fatal tras <title>.
  Fix: require includes/admin.php al inicio de index(). Desplegado.
- **Bug #6 — verifyPostTable inlineaba defaults de función** (commit `c5448d21`):
  information_schema devuelve `current_timestamp()` como string; se insertaba
  como literal → zero date (0000-00-00) en lines.updated y streams.updated de
  filas NUEVAS → rompe la detección de cambios del cache_engine (getChanged-
  Lines/Streams compara mtime vs updated). Fix: defaults con forma de función
  se OMITEN (la BD aplica el suyo). Desplegado + filas dañadas reparadas
  (lines.updated=FROM_UNIXTIME(created_at), streams.updated=FROM_UNIXTIME(added)).
  OJO: opcache revalidate_freq=20s — un test inmediato tras desplegar puede
  usar el código viejo (pasó con qa_line3).
- **Notas del flujo**: la player API imita la página 404 de nginx a propósito
  (generateError/generate404, anti-probe) — un 404 "de nginx" en /live o
  player_api suele ser INVALID_CREDENTIALS u otro deny del panel. Las líneas
  nuevas tardan ≤5 min en entrar a la caché de streaming (cache_engine */5) —
  reproducir inmediatamente tras crear da 404 INVALID_CREDENTIALS: normal.
  REST create_line NO aplica la duración del package (exp_date null salvo que
  se envíe exp_date) — el UI la calcula y la envía; comportamiento upstream.
- **Debug temporal usado y revertido**: debug_show_errors 1→0 (revertido),
  access_log qa_fmt en nginx (quitado). `tools access` regenera confs de
  códigos + reload nginx (útil para códigos nuevos).

### 2026-11-04 — Update 2.0.1→2.0.2 vía UI + incidente de arranque (resuelto)
- El usuario aplicó el update desde la UI (botón "Update Server" → señal
  `{"action":"update"}` → RootSignalsCronJob → `console.php update update` →
  `sudo python3 update` → tar + post-update). **Panel ahora en 2.0.2**
  (`servers.uypanel_version=2.0.2`, AppConfig desplegado 2.0.2, status 1,
  sin migraciones, update_data=NULL — correcto: 2.0.2 es la última).
- **El update SOBRESCRIBIÓ bootstrap.php** con la versión del tag 2.0.2
  (anterior al fix `646d63fa`) → re-desplegado a mano tras el update.
  LECCIÓN: cada update desde un tag anterior al fix revierte bootstrap.php;
  a partir del próximo release (2.0.3+) el fix va incluido y esto deja de pasar.
- **Incidente**: el usuario generó DOS señales de update → dos
  `console.php update` en paralelo → dos `python3 update` compitiendo. El
  `service start` arrancó ANTES de que el update aplicara los chmod →
  `daemons.sh: Permission denied` (pools php-fpm nunca arrancaron) y un
  `nginx.pid` huérfano root-owned en bin/nginx/logs/ bloqueó el arranque de
  nginx como uypanel (Permission denied silencioso: stderr→/dev/null en el
  script service). nginx_rtmp y redis sí arrancaron; panel DOWN (HTTP 000)
  ~4 minutos.
- **Recuperación**: rm del nginx.pid huérfano + chmod 0771 daemons.sh +
  `systemctl restart uypanel` → nginx 80/443 ✓, 4 pools ✓, sockets ✓,
  admin 302→login 200 (8,848 B) ✓, video login intacto ✓, demonios
  singleton ✓ (1 cache_handler, 1 watchdog).
- **Notas del flujo de update**: la señal `update_binaries` que emite
  `status` tras cada update lanza `console.php binaries` (root) — SOLO
  sincroniza GeoLite mmdb desde releases de UyPanel_Update; sin releases
  devuelve null y es no-op silencioso (los mmdb vienen en el payload).
  El `nginx -s reload` de cron:servers corre como root cada minuto.
- Pendiente opcional: publicar release GeoLite en UyPanel_Update para que
  `binaries` pueda restaurar mmdb borrados (requiere refresco manual igual).

### 2026-11-04 — Limpieza de duplicados + lock de escaneo exclusivo (force incluido)
- **Duplicados**: la carrera de los dos escaneos simultáneos dejó 141 películas
  duplicadas (819 streams type=2 para 678 fuentes únicas). Episodios OK
  (123=123 — la serie se escaneó una sola vez).
- **Limpieza** (BD + shell): conservado el id más antiguo de cada
  `stream_source`; borrados dup streams + streams_servers + queue;
  `watch_logs.stream_id` repuntado al stream conservado; 142 symlinks
  huérfanos de content/vod borrados (141 dups + el `31.mp4` del experimento
  S05E01). Estado final: **678 películas + 123 episodios = 801 streams = 801
  symlinks 1:1**; `to_analyze` 0 (las 138 ya analizadas).
- **Aclaración clave**: con `movie_symlink=1` NUNCA se transcodifica —
  `StreamProcess::startMovie` hace `ln -sfn original content/vod/{id}.mp4`
  (los 943 "archivos" eran symlinks, 0 CPU). El `auto_encode` solo encola ese
  paso. El CPU 100% fue SOLO el doble escaneo. Los mp4 h264+aac se sirven
  directos — config óptima tal cual.
- **Fix anti-duplicados** (commit `5e59a45d`, desplegado):
  - `CronTrait::acquirePidLock()`: lock atómico O_EXCL (fopen 'x') con
    limpieza de holder muerto y release en shutdown — sustituye el patrón
    check-then-write que tenía ventana de carrera durante el boot (~1-2s).
  - `WatchCronJob`/`PlexCronJob`: el check "already running" aplica TAMBIÉN
    con force (antes se saltaba con arg).
  - `WatchController::apiFolder`/`PlexController::apiLibrary` (force):
    devuelven `result:false` si ya hay escaneo → la UI muestra toast de error
    en vez de no-op silencioso.
- **Verificado**: test determinista del lock (holder 6s → concurrente
  rechazado; stale → limpiado y adquirido; pidfile borrado al terminar) +
  escaneo real post-limpieza sin nuevos duplicados (678=678).
- Comportamiento nuevo a documentar para el usuario: si pulsa force mientras
  corre un escaneo, verá toast de error — reintentar cuando termine (el cron
  de 5 min cubre los folders activos igualmente).

### 2026-11-04 — Incidente CPU 100%: doble escaneo + encode queue (resuelto)
- **Síntoma** (usuario): escaneo del folder de películas (`/mnt/vod/Peliculas/`,
  684 archivos, folder 2 con auto_encode=1, movie_symlink=1, ffprobe_input=1,
  ignore_no_match=1) → load average 45-68 (8 cores), swap lleno, 33 zombies.
- **Causa**: DOS escaneos concurrentes (el botón force NO tiene lock exclusivo
  — el check "already running" solo aplica al cron programado) × thread_count
  50 → ~100 watch_item concurrentes (cada uno: boot PHP + guessit + ffprobe +
  TMDb) + 2×50 sh wrappers.
- **Resolución**: pkill de los 2 padres + watch_item + guess. Datos intactos
  (636/684 películas ya creadas; 0 filas parciales — verificado streams sin
  servers = 0). Load de vuelta a 10 en ~3 min; zombies reabsorbidos.
- **Prevención aplicada (settings, vía BD + clearSettingsCache)**:
  `thread_count` 50 → **8** (8 cores) y `max_encode_movies` 10 → **2** (decisión
  del usuario: la cola de 138 películas a encode drenará lento pero sin
  saturar; el análisis to_analyze es ffprobe secuencial del cron:vod, ligero).
- **Lecciones**: (1) force de Watch/Plex es acumulativo — no pulsarlo mientras
  corre un escaneo; (2) thread_count alto solo tiene sentido en máquinas
  grandes; (3) el swap 1.5GB se llena en bursts — drena solo, no crítico con
  23 GB RAM disponibles.

### 2026-11-04 — FIX: Watch Folder/Plex nunca escaneaban (globals huérfanos + paths fantasma)
- **Síntoma** (usuario): creó un Watch Folder (series, `/mnt/vod/Series/`) y lo
  forzó — `last_run` seguía en 0, nada se importaba.
- **Causa raíz (upstream 2.0.1, migración de módulos incompleta — verificado
  contra el payload original `xc_vm.tar.gz`: idéntico)**:
  1. `WatchCron::run()`/`PlexCron::run()` leen como globales `$rForce`,
     `$rScanOffset`, `$rThreadCount`, `$rMaxItems` (`$F7fa29461a8a5ee2`
     ofuscado) — definidos en NINGÚN sitio del código. La query programada
     compara `UNIX_TIMESTAMP()-last_run > NULL` → nunca matchea (y
     `last_run` default 0, no NULL); el force pierde el arg CLI.
  2. `WatchItemCommand`/`PlexItemCommand` ponen `$rThreadData` (y
     `$rTimeout`/`$rStreamDatabase`) en scope LOCAL; `WatchItem::run()`/
     `PlexItem::run()` los leen como globales → cada item moriría con null.
  3. Los spawns apuntan a `includes/cli/{watch_item,plex_item,record,certbot}.php`
     — archivos que NO existen (ni en el payload upstream). Los entry points
     reales son comandos de `console.php`: `watch_item`, `plex_item`,
     `certbot`, `record`. Sitios: WatchCron:227, PlexCron:332/334,
     MovieService:648, SeriesService:290, RootSignalsCronJob:502,
     VodCronJob:58.
- **Fix** (commit `acbe002f`, 10 archivos, desplegado): los CronJob publican
  los globals (`$GLOBALS['rForce'/'rScanOffset'/'rThreadCount'/'rMaxItems']`
  desde settings `scan_seconds`/`thread_count`/`max_items`), los ItemCommand
  globalizan sus datos, y TODOS los spawns van vía `console.php <comando>`.
  Renombrado `$F7fa29461a8a5ee2` → `$rMaxItems`.
- **Verificado end-to-end** (folder 1, series, `/mnt/vod/Series/`, 123 archivos):
  3 escaneos → 4 series (El Equipo A 94 eps, Ana 10, Almost Human 13,
  After Life 6), 123 episodios, metadatos TMDb en español + covers descargados
  localmente, `last_run` actualizado, watch_logs 108×status1.
- **Parser**: `settings.parse_type` cambiado `ptn` → `guessit` (PTN no
  reconoce rangos multi-ep `S03E02-03` → título basura → no-match; guessit
  sí: `episode:[2,3]` → importa E02 con display "S03E02-03"). Cambiado por BD
  (UI: Settings → VOD Parser).
- **`fallback_title` activado en el folder 1** (UI: editar folder → "Fallback
  to Folder Name"): archivos `01x01.mp4` (nombre solo en la CARPETA — After
  Life, Almost Human) matchean por nombre de carpeta; S/E persisten del parse
  del filename. Sin esto: 19/123 no-match.
- **Quirk menor observado**: en el primer escaneo con 50 threads paralelos,
  16/123 items crearon su episodio pero no escribieron el log status-1 en
  watch_logs (fallo transitorio bajo burst; datos completos; en re-escaneo el
  log se escribe bien). Cosmético — vigilar si recurrence con bibliotecas grandes.
- Nota: el 404 de `POST /api` con password incorrecta es el anti-probe de
  InternalApiController (imita la página 404 de nginx) — la API interna
  FUNCIONA con el password real (`live_streaming_pass`); `/api` llega a
  `/api.php` vía el fallback `@extensionless-php` de nginx.
- El usuario corrigió a mano el match de "Ana" (PTN la había matcheado a una
  serie vieja; la real es tmdb 102625, 2020).

### 2026-11-04 — Fix: páginas watch/plex del panel en blanco ("congeladas")
- **Síntoma** (usuario): `http://198.27.81.133/7JDEeDMG/watch` se queda
  congelada/en blanco. El menú enlaza `watch` (Watch Folder, bajo Contenido).
- **Causa raíz (bug upstream, commit `23070985` del 15-mar-2026)**: para resolver
  conflictos de autoloader renombraron `Admin/WatchController` →
  `AdminWatchController` y `Admin/PlexController` → `AdminPlexController`, pero
  SOLO actualizaron la ruta de `logout`. Las rutas `watch`/`plex` siguieron
  apuntando a los controladores del MÓDULO (`modules/watch/WatchController`,
  `modules/plex/PlexController`), cuyo `index()` incluye únicamente la vista
  desnuda — sin `renderUnifiedLayoutHeader`, sin footer, sin CSS/JS → página
  sin estilos ni scripts (el usuario la percibe como "congelada").
- Los wrappers correctos (`AdminWatchController`, `AdminPlexController`,
  `BaseAdminController::render()`) existían pero estaban huérfanos (0
  referencias). `watch_add`/`watch_output`/`settings_watch` SÍ usaban wrappers
  correctos — solo `watch` y `plex` estaban rotas.
- **Fix** (commit `131e3c54`): `routes/admin.php` → `watch` →
  `AdminWatchController::index`, `plex` → `AdminPlexController::index`.
  Desplegado a `/home/uypanel/public/routes/admin.php` (chown uypanel).
- **Verificado**: sin sesión ambas rutas 302 → login (gate OK, sin fatal);
  login 200. Verificación con sesión pendiente del usuario (render completo:
  header + tabla + scripts del módulo vía wrapper legacy `Views/admin/watch.php`,
  que en modo `$__viewMode` gestiona footer+scripts él mismo).
- Lección: al renombrar controladores, grep de TODAS las referencias
  `[XxxController::class` en routes/*.php; los módulos y los wrappers pueden
  compartir nombre de clase base.

### 2026-11-07 — Load Balancer: diagnóstico, builds ubuntu20/22/24 y fix del instalador
- **Contexto**: el usuario está añadiendo LBs al panel. Estados previos: LB01/
  PRX01 (208.110.84.125) y EU-LB (64.37.105.75) status 4 — los 3 murieron en
  "Failed to connect" (SSH 22 cerrado hacia el panel; verificado con /dev/tcp:
  sigue cerrado). 164.37.105.75 sí conecta (Ubuntu 20.04).
- **Instalación id=7 (EU-LB, 164.37.105.75)**: completó en ~90 s (status 3→1,
  log en `/home/uypanel/bin/install/7.install`; el instalador corre EN EL PANEL
  como `UyPanel Install[<id>]` con salida a `bin/install/<id>.install`; el
  `<id>.json` de credenciales se borra al terminar OK). nginx arriba (200 en
  80/443) pero **PHP 100% muerto**: `/progress` → 502, 0 procesos php-fpm,
  sockets vacíos, sin crontabs, sin redis, sin phone home
  (`uypanel_version=NULL`, `last_check_ago=0`).
- **Causa raíz (verificada por SSH con el PHP del panel + ssh2)**: el payload
  LB (release 2.0.2, binarios NUESTROS) trae PHP/nginx compilados para
  **Debian 13** → en Ubuntu 20.04 (glibc 2.31) el binario ni carga:
  `GLIBC_2.32/2.33/2.34/2.38 not found` + `libjpeg.so.62 not found`
  (Debian 13 = glibc 2.38/libjpeg62; focal = glibc 2.31/libjpeg-turbo8).
  El GAP #2 del panel (binarios upstream prefix xc_vm) ya no existe en el
  payload 2.0.2 — ahora el gap es el INVERSO: binarios Debian en LBs Ubuntu.
- **Filas duplicadas borradas**: ids 4 y 6 eran el MISMO servidor físico
  (164.37.105.75) que el id 7 (instalado 3 veces). DELETE + cache
  `tmp/cache/servers` refrescado. Quedan: 1 main, 2 LB01(fallo SSH), 3
  PRX01(fallo SSH), 5 EU-LB(fallo SSH), 7 EU-LB.
- **Builds ubuntu20/22/24 COMPLETADOS Y VERIFICADOS** (`sudo ./build_all.sh
  ubuntu20|ubuntu22|ubuntu24` en third_party/UyPanel_Binaries, rama b224e4b
  con prefix uypanel + PHP 8.3.36; docker en el host; ccache por target).
  Salida: `out/ubuntu_{20,22,24}.tar.gz` (~80-90 MB c/u) + hashes.md5
  actualizado. Verificados en contenedores reales de cada distro (ldd sin
  "not found" tras instalar el set runtime: libxml2 libcurl3-gnutls[t64 en
  24] libpng16-16 libjpeg-turbo8 libfreetype6 libonig5 libsodium23
  libxslt1.1 libmaxminddb0 libssh2-1 libzip4[t64 en 24] libargon2-1
  libedit2; php -m con el php.ini del payload carga redis/igbinary/ssh2/
  maxminddb/opcache ✓).
- **Despliegue al LB id=7 HECHO y VERIFICADO end-to-end**: scp del tarball
  (md5 ok) → extract sobre /home/uypanel (el tarball NO pisa php/etc/*.conf
  ni php.ini) → chown bin → fstab dedupe → restart → PHP 8.3.36 arriba,
  pools ondemand OK, phone home OK (`uypanel_version=2.0.2`,
  `last_check_ago` avanzando), player_api del LB responde (404 anti-probe
  sin creds), **playback real verificado**: main `302 → /auth/{token}` en el
  LB → `HTTP 200 video/mp2t` sostenido (~4-6 Mbps).
- **Cadena de arranque de streams en un LB (arquitectura 2.0.x, verificada)**:
  el panel arranca streams posteando a la API interna del server
  (`api_url = http://ip:port/api?password=live_streaming_pass` →
  `public/index.php` FC, InternalApiController). El LB solo sirve URLs con
  TOKEN (`/auth/{token}` etc. — lb_configs/nginx.conf no tiene rewrites
  `/live/`; el main redirige 302 al token del LB). El auth de streaming
  fuerza `enable_cache=false` (www/stream/init.php:86) → BD directa → el LB
  NO necesita CacheEngineCronJob (por eso upstream lo excluye del payload).
- **GAP del payload LB (upstream, heredado)**: el payload no trae `public/`
  → la API interna y player_api del LB dan "File not found" → las órdenes
  de arranque del panel se pierden en silencio. FIX: `public/` añadido a
  `LB_DIRS` (commit `51128e28`); en el LB actual se desplegó a mano
  (/tmp/public_lb.tgz, md5 verificado).
- **Stream 2 (RT France) en el LB**: la fuente
  `rt-fra.rttv.com/dvr/rtfrance` desde la red del LB es POCO FIABLE: probe
  23-40s variable, primer segmento a veces >30s, conexiones al edge
  (178.176.128.128) con Send-Q atascado (~2.5 MB sin ACK). Dos fixes
  aplicados: (1) settings `probe_extra_wait` 10→30 (timeout de probe
  15→35s; para fuentes normales no cambia nada) y (2) MonitorCommand:
  paciencia de arranque cap 30→90 chequeos (commit `31824c6f`, desplegado
  en main y LB). Con ello el stream 1 (amagi, fuente rápida) arranca y
  emite ✓; el stream 2 (RT France) tras varios reintentos del monitor
  (con la paciencia nueva) **LEVANTÓ al cierre de la sesión**: pid estable,
  compatible=1, playback 200 video/mp2t ~4.6 Mbps sostenido ✓. La fuente
  sigue siendo lenta/variable desde esa red (probe ~24s) — los fixes dan
  margen suficiente; vigilar si el CDN vuelve a estancarse.
- **Fix BalancerCommand** (commit `3d4ed485`): tras detectar la distro
  remota (`lsb_release -rs`), Debian 12/13 → quita mcrypt/php-ssh2/
  software-properties-common/libgeoip-dev/python/cpufrequtils y añade
  `libcurl3t64-gnutls`; resto (Ubuntu) → añade `libcurl3-gnutls`.
  Desplegado a `/home/uypanel/cli/Commands/BalancerCommand.php`.
- **Fix fstab duplicado** (commit `23b40642`): `stripos(...) !== true`
  siempre verdadero → las líneas tmpfs se añadían en CADA instalación
  (encontrado triplicado en el LB; dedupe aplicado in situ).
- **Pendiente**: rebuild del payload LB (`make lb`) con binarios ubuntu20 +
  release nuevo para que futuras instalaciones de LB en Ubuntu funcionen de
  fábrica (el payload actual lleva binarios Debian 13 como fallback + el
  instalador aún no descarga binaries_<distro>; diseño distro-aware
  propuesto: assets `binaries_<distro>.tar.gz` en el release + el
  instalador/BalancerCommand descargan el de la distro detectada).
- Monitor en vivo usado: `/tmp/lb_monitor.sh` (log tail + estados BD +
  proceso instalador); scripts SSH de diagnóstico/despliegue en /tmp
  (lb_*.php — BORRADOS al cerrar la sesión; contenían credenciales del LB).

### 2026-11-03 — Video del login reemplazado por el del usuario
- El usuario subió su video personal (temático IPTV: pantallas de fútbol con
  neón) a `/home/debian/login-bg.mp4` — no pudo escribir en el proyecto porque
  el archivo `login-bg.mp4` desplegado era propiedad de `uypanel:uypanel`
  (el directorio sí era de debian; sobrescribir un archivo ajeno falla).
- Video del usuario: H.264/yuv420p 864x480 24fps, 3.75s, AAC, 1.4 MB —
  compatible con navegadores. Remux sin pérdida con `-movflags +faststart`
  (moov al inicio; el original lo tenía tras el mdat, problemático en Safari).
- Desplegado en `/home/uypanel/public/assets/admin/videos/login-bg.mp4`
  (ahora propiedad `debian:debian` 644 para que futuros reemplazos no
  necesiten sudo) y en el repo `src/public/assets/admin/videos/`.
- Verificado por HTTP: `GET /x9access9x/assets/videos/login-bg.mp4` → 200,
  1,432,592 bytes, Accept-Ranges OK, md5 idéntico al remux.
- Commit `f1a3bf51` (LFS 1.4 MB) pusheado a origin/main.
- Nota: las peticiones HEAD a ese URL devuelven Content-Length: 0 (quirk de
  nginx alias+try_files); los GET y Range requests funcionan bien — no afecta
  a los navegadores, que usan GET.

### 2026-11-07 — Actualización de seguridad: MariaDB 11.8.9 y paquetes del sistema
- Backup completo pre-actualización: `/home/debian/backup_pre_upgrade_2026-11-07.sql.gz` (3.9 MB).
- Repositorio oficial MariaDB Foundation para Debian 13 (trixie) configurado en
  `/etc/apt/sources.list.d/mariadb.sources` con keyring GPG oficial.
- MariaDB actualizado de 11.8.6 a 11.8.9+maria~deb13 (corrige CVE-2026-85746,
  CVE-2026-92262, CVE-2026-49261, CVE-2026-107814, etc.).
- `galera-4` actualizado a 26.4.27.
- Actualizados paquetes vulnerables pendientes del sistema: `zlib1g` y `zlib1g-dev`
  (1:1.3.dfsg+really1.3.1-1+deb13u1).
- Parámetro `max_connections = 400` restablecido y verificado en `/etc/mysql/mariadb.cnf`.
- Verificación post-actualización: MariaDB 11.8.9 active, UyPanel status 1 OK,
  demonios singleton OK, login web 200 OK, player_api 200 OK (`auth:1`).

### 2026-11-07 — Instalador y BalancerCommand multi-distro: MariaDB 11.8 oficial + binarios nativos automáticos para LBs
- `install`:
  - Repositorio oficial de MariaDB Foundation integrado con `--mariadb-server-version="mariadb-11.8"`.
  - Configuración MySQL `max_connections` ajustada a 400 (antes 200 en servers >= 8GB).
  - Soporte multi-distro mejorado: Debian 12/13 y Ubuntu 24.04 usan librerías t64 (`libcurl4t64`, `libssh2-1t64`) y omiten paquetes obsoletos/inexistentes (`software-properties-common`, `mcrypt`, `python`, `cpufrequtils`).
  - Sincronizado en `/home/debian/install`.
- `src/cli/Commands/BalancerCommand.php`:
  - Detección precisa de distro en nodo remoto vía `lsb_release -is` y `lsb_release -rs`.
  - Paquetes de streaming y runtime adaptados por distro (Debian 12/13, Ubuntu 20.04/22.04/24.04), agregando dependencias de FFmpeg/Nginx (`libnuma1`, `libgomp1`, `libexpat1`, `librtmp1`, `libcurl3t64-gnutls`).
  - Despliegue automático de binarios nativos: busca en `/home/uypanel/bin/distros/<distro>.tar.gz` (debian_13, ubuntu_20, ubuntu_22, ubuntu_24) y los extrae en el LB remoto, fijando permisos `uypanel:uypanel`.
  - Sincronizado en `/home/uypanel/cli/Commands/BalancerCommand.php`.
- Preparado `/home/uypanel/bin/distros/` con los 4 tarballs de binarios nativos compilados (PHP 8.3.36 + Nginx 1.29.6).
- Sintaxis PHP validada con `tools/php_syntax_check.sh` (625 archivos, 0 errores).

### 2026-11-07 — Ajustes: versiones de MariaDB, KeyDB/Redis, Nginx RTMP y FFmpeg añadidas a Info
- `src/public/Controllers/Admin/SettingsController.php` y `src/public/Views/admin/settings.php`:
  se agregaron a la tarjeta "Versions" de la pestaña Info (Settings) las versiones de
  MariaDB (`SELECT VERSION()`), KeyDB/Redis (vía extensión PHP Redis sobre 127.0.0.1:6379),
  FFmpeg activo (según `settings.ffmpeg_cpu`) y Nginx RTMP (`nginx_rtmp -v`).
- Sintaxis PHP validada con `tools/php_syntax_check.sh` (625 archivos, 0 errores).
- Cambios desplegados en `/home/uypanel` con propiedad `uypanel:uypanel`.

## Trabajo pendiente / Roadmap

- [x] **Comentarios en ruso en ~200 archivos PHP** — traducidos al español
      (2026-11-03, commit 037f50f6; datos funcionales MAG/langs conservados)
- [x] **Video del login**: video personal del usuario desplegado en servidor +
      repo, commit `f1a3bf51` pusheado (2026-11-03)
- [x] **GitHub Pages** — activado (main, /docs); sitio verificado en línea
- [x] **Reconstruir binarios con prefix /home/uypanel** — HECHO (2026-11-03,
      PHP 8.3.36 + nginx 1.29.6, symlink /home/xc_vm eliminado)
- [x] **Binarios propios DENTRO del payload** — HECHO (2026-11-04, commit
      6225c356; instalación fresca funcional de fábrica en Debian 12/13)
- [x] **Release 2.0.2** — publicado con assets del workflow (CI con fetch LFS;
      18 objetos LFS que faltaban subidos)
- [x] **Fix idioma admin (verifyPostTable quoting MariaDB)** — commit eac4a201
- [x] **Fix bouquet NULL (panel en blanco)** — commit bac2def5
- [ ] Personalizar READMEs/branding de los forks de soporte cuando se toque
- [ ] Re-importar EPG (BD fresca: sin URLs de EPG; las 22 del panel antiguo
      están en los backups de /home/debian si se quieren recuperar)
- [ ] Revisar página de Settings del panel (defaults del seed)
- [ ] Borrar `/home/uypanel.pre_reinstall` cuando el usuario confirme el nuevo
      estado; también los payloads viejos de /home/debian (~895 MB) si quiere
- [ ] Nota: si se reconstruyen binarios Debian, las librerías adaptadas
      (`libcurl3t64-gnutls`, `libjpeg.so.8`) dejan de ser necesarias

## Notas técnicas importantes

- El Makefile construye payloads: `make main` (panel → dist/uypanel.tar.gz +
  dist/UyPanel.zip), `make lb` (load balancer → dist/loadbalancer.tar.gz,
  excluye `modules/` y código admin), `make main_update`/`lb_update`.
- Arquitectura obligatoria: Controller → Service → Repository → Database;
  reglas en `.github/instructions/` y `ARCHITECTURE.md` (ya en español).
- Los módulos (`src/modules/`) son solo del panel principal; el LB no los lleva.
- KeyDB 6.3.4 (= Redis 6.2): no soporta directivas de Redis 7+ como
  `lazyfree-lazy-server-delay` (eliminada del redis.conf y del instalador).
- MariaDB 11.8 en el servidor; el instalador escribe `/etc/mysql/my.cnf` propio.
- LFS: los archivos grandes (binarios, mmdb, database.sql, videos) son LFS —
  al hacer grep/sed de barridos masivos, EXCLUIR o verificar primero que no
  sean punteros LFS (lección aprendida: database.sql escapó a dos barridos).
- El workflow `build-release.yml` se dispara al publicar un release y sube
  assets construidos desde el tag — para releases propios funciona bien, pero
  el tag 2.0.1 apunta al commit del original (los assets se subieron a mano).
- Submódulos pineados a builds más nuevos que el 2.0.1 (PHP 8.3 + extensión
  `xcvm_core` en Rust): al reconstruir binarios, decidir si se pinean commits
  de la época (~marzo 2026) o se adopta la generación nueva.
