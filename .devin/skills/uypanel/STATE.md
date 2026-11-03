# UyPanel — Estado del proyecto (documento vivo)

> Este archivo lo mantiene el agente (vía skill `uypanel`). Se actualiza tras
> cada cambio significativo. Última actualización: 2026-11-03 (fin de sesión —
> comentarios traducidos, Pages activo, binarios PHP 8.3.36 con prefix uypanel).

## ⏭️ CONTINUAR AQUÍ (próxima sesión)

1. **Video del login (acción del USUARIO)**: el usuario va a pegar su propio
   video en `/home/uypanel/public/assets/admin/videos/login-bg.mp4` (servidor,
   el dir ya tiene permisos debian:debian) y en el repo
   `src/public/assets/admin/videos/login-bg.mp4`. Cuando lo pegue en el repo:
   commitear + pushear (es archivo LFS). El video ACTUAL en ambos sitios es el
   del original y muestra el logo 3D de XC_VM en su frame central (~3.5s).
2. **Roadmap** (ver sección Trabajo pendiente): rebuild de binarios con prefix
   uypanel, EPG re-import, revisión de Settings, READMEs de los forks de soporte.

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

## Trabajo pendiente / Roadmap

- [x] **Comentarios en ruso en ~200 archivos PHP** — traducidos al español
      (2026-11-03, commit 037f50f6; datos funcionales MAG/langs conservados)
- [ ] **Video del login**: usuario pega el suyo (servidor + repo) → commit+push
- [x] **GitHub Pages** — activado (main, /docs); sitio verificado en línea
- [x] **Reconstruir binarios con prefix /home/uypanel** — HECHO (2026-11-03,
      PHP 8.3.36 + nginx 1.29.6, symlink /home/xc_vm eliminado)
- [ ] Personalizar READMEs/branding de los forks de soporte cuando se toque
- [ ] Re-importar EPG desde las 22 URLs migradas (los datos EPG no se migran)
- [ ] Revisar página de Settings del panel (quedó en defaults tras migración)
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
