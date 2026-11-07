# UyPanel — Estado del proyecto (documento vivo)

> Este archivo lo mantiene el agente (vía skill `uypanel`). Se actualiza tras
> cada cambio significativo. Última actualización: 2026-11-04 (fix watch/plex
> en blanco + Watch Folder/Plex scanning NUNCA ejecutaba — globals huérfanos y
> paths fantasma del upstream; escaneo end-to-end verificado: 123/123 archivos).

## ⏭️ CONTINUAR AQUÍ (próxima sesión)

1. **LOAD BALANCER — hilo activo (2026-11-07)**: ver entrada del changelog
   del 2026-11-07. Build ubuntu20/22/24 en marcha; al terminar: verificar
   ubuntu20 en contenedor, desplegar al LB 164.37.105.75 (SSH root, credenciales
   usadas en la sesión — NO están guardadas), restart + startup + cron:servers,
   verificar phone home (fila 7: uypanel_version + last_check_ago) y streams.
   Falta también: rebuild del payload LB (`make lb`) con binarios ubuntu20 +
   release nuevo para que futuras instalaciones de LB funcionen de fábrica.
2. **LB01/PRX01 (208.110.84.125) y EU-LB (64.37.105.75)**: SSH 22 CERRADO
   desde el panel → los installs mueren en "Failed to connect". El usuario debe
   abrir SSH/firewall hacia 198.27.81.133 antes de reintentar.
3. **Limpieza de artefactos QA** (confirmar con el usuario): usuarios
   `qa_admin`/`qa_reseller`, líneas `qa_line1..4`/`qa_adminline`, access
   codes `qaResT3st` (tipo 1) y `qaApiT3st` (tipo 3 — expone la REST API,
   borrar antes de producción). Tras borrar códigos: `console.php tools
   access` + verificar.
4. **QA de playback VOD** (la biblioteca YA existe: 678 películas + 123
   episodios vía Watch Folder): reproducir una película y un episodio desde
   el panel/admin y con una línea real (vauth + symlink directo).
5. **QA pendiente** (mismo método: setup + REST API + panel + playback):
   MAG/enigma2, trials (el grupo 2 tiene total_allowed_gen_trials=0 —
   habilitar para probar), sub-resellers, bouquets múltiples, EPG
   (re-importar: BD fresca sin URLs de EPG).
6. **Panel (lado del usuario)**: revisar Settings (defaults del seed),
   re-importar EPG.
7. **Limpieza pendiente**: borrar `/home/uypanel.pre_reinstall` (rollback de
   la instalación anterior) cuando el usuario confirme; payloads viejos de
   /home/debian (~895 MB) si quiere.
8. **Roadmap**: READMEs de los forks de soporte; release GeoLite opcional en
   UyPanel_Update para que `binaries` pueda restaurar mmdb.
9. **Watch Folder — notas operativas**: thread_count=8 y
   max_encode_movies=2 (decisión usuario); el force mientras corre un
   escaneo ahora da toast de error (lock exclusivo, commit 5e59a45d); los
   23 no-match originales se resolvieron con parse_type=guessit +
   fallback_title=1 en el folder de series. Quedan 4 archivos A-Team
   multi-episodio importados como primer episodio del rango (S03E02-03 →
   E02) — si se quiere el rango completo habría que partir los archivos.


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
- MariaDB `max_connections = 400` (2026-11-04; el instalador ponía 200 y el
  incidente de los CacheHandler lo agotó; dinámico + persistente en
  /etc/mysql/mariadb.cnf)
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
  PRX01(fallo SSH), 5 EU-LB(fallo SSH), 7 EU-LB(operativo roto).
- **Builds ubuntu20/22/24 lanzados** (`sudo ./build_all.sh ubuntu20|ubuntu22|
  ubuntu24` en third_party/UyPanel_Binaries, rama b224e4b con prefix uypanel +
  PHP 8.3.36; docker en el host; ccache por target). Salida esperada:
  `out/ubuntu_20.tar.gz` etc. El build enlaza libcurl4-gnutls-dev → runtime
  Ubuntu = `libcurl3-gnutls`.
- **Fix BalancerCommand** (commit pendiente de pushear): tras detectar la
  distro remota (`lsb_release -rs`), Debian 12/13 → quita mcrypt/php-ssh2/
  software-properties-common/libgeoip-dev/python/cpufrequtils y añade
  `libcurl3t64-gnutls`; resto (Ubuntu) → añade `libcurl3-gnutls`. Desplegado a
  `/home/uypanel/cli/Commands/BalancerCommand.php` (php -l OK).
- **Plan de despliegue al LB** (pendiente del build): scp `out/ubuntu_20.tar.gz`
  → extraer sobre /home/uypanel (el tarball NO pisa php/etc/*.conf generadas ni
  php.ini — verificado en el listing: solo bin/{php,nginx,nginx_rtmp}, php.ini
  viene del payload con extensiones "bare" + extension_dir compilado) →
  `chown -R uypanel:uypanel /home/uypanel/bin` → restart uypanel → startup +
  cron:servers → verificar pools/502/phone home.
- **Pendiente**: rebuild del payload LB (`make lb`) con binarios ubuntu20 +
  release nuevo (los LBs del usuario son Ubuntu; un payload Debian los rompe —
  decidir si el payload LB pasa a ser ubuntu20 o se hace multi-distro).
- Monitor en vivo usado: `/tmp/lb_monitor.sh` (log tail + estados BD + proceso
  instalador); diagnóstico SSH: `/tmp/lb_diag.php` (borrar tras usar).

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
