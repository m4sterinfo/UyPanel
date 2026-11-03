# UyPanel — Estado del proyecto (documento vivo)

> Este archivo lo mantiene el agente (vía skill `uypanel`). Se actualiza tras
> cada cambio significativo. Última actualización: 2026-11-02.

## Identidad

- **Nombre**: UyPanel
- **Base**: fork de XC_VM tag `2.0.1` (commit `e21f9063`), historial git completo conservado
- **Rama de trabajo**: `main` (nuestra)
- **Upstream**: `origin` = https://github.com/Vateron-Media/XC_VM (solo lectura)
- **Repo propio**: https://github.com/m4sterinfo/UyPanel (público, AGPL-3.0, rama main = c19dbf69; pusheado el 2026-11-02)
- **Auth GitHub**: gh CLI autenticado como `m4sterinfo` (credenciales de git vía gh)
- **Licencia**: AGPL-3.0 (heredada, mantener — NO cambiar a GPL-3.0)

## Entorno

- Servidor: Debian 13 (trixie), 8 cores, 32 GB RAM, 1.7 TB disco, IP `198.27.81.133`
- Panel 2.0.1 instalado en `/home/xc_vm` y en producción
- DB migrada desde XUI el 2026-11-02 (27,672 líneas, 240,557 streams, 2,087 users)
- Acceso admin: `http://198.27.81.133/x9access9x` (código migrado del panel antiguo)
- Copia del backup original: `/home/debian/backup_2026-11-02_15_16_01.sql` (1.2 GB)

## Decisiones tomadas

| Fecha | Decisión | Motivo |
|-------|----------|--------|
| 2026-11-02 | Base del fork = tag 2.0.1 (no main) | Coincide con el panel en producción y la DB migrada |
| 2026-11-02 | Conservar historial git upstream | Permite diff/cherry-pick de fixes del original |
| 2026-11-02 | Binarios/FFmpeg como submódulos en `third_party/` | Repo ligero, actualizables, sin vendor de binarios |
| 2026-11-02 | Instalador con soporte Debian 12/13 | El original 2.0.1 solo soportaba Ubuntu |

## Registro de cambios (nuestros)

### 2026-11-02 — Creación del fork UyPanel
- Fork desde tag `2.0.1` con historial completo; rama `main` propia.
- Submódulos añadidos: `third_party/XC_VM_Binaries` (@ `ff034e5`),
  `third_party/XC_VM_FFMPEG` (@ `43b01ca`).
- `install`: soporte Debian 12/13 (detección de distro vía `/etc/os-release`,
  paquetes t64 `libcurl4t64`/`libssh2-1t64`, sin PPA MaxMind ni repo MariaDB
  externo en Debian, `libcap2-bin` para setcap). Validado instalando el panel
  en producción con este instalador.
- Skill `.devin/skills/uypanel/` creado (SKILL.md + STATE.md).

### 2026-11-02 — Rebranding completo y separación total del original
- **Barrido de rebranding** en 176 archivos: `XC_VM` → `UyPanel`,
  `Vateron-Media` → `m4sterinfo` (README, docs en/ru, UI, langs, CLI, Makefile,
  instalador, workflows, test_installer). Copyrights del original conservados
  (obligatorio AGPL). Logo del original eliminado de los READMEs (pendiente
  logo propio).
- **Repos de soporte propios** (forks de upstream, renombrados):
  `m4sterinfo/UyPanel_Update` (changelog, GeoLite lists, api_server),
  `m4sterinfo/UyPanel_Binaries` (build PHP/nginx/redis/nginx_rtmp),
  `m4sterinfo/UyPanel_FFMPEG` (build FFmpeg).
- **Submódulos** renombrados y reapuntados: `third_party/UyPanel_Binaries`,
  `third_party/UyPanel_FFMPEG` (mismos commits pineados).
- **Git LFS**: el repo usa LFS para binarios grandes (20 archivos, ~665 MB:
  ffmpeg x6, redis-server, proxy.tar.gz 56 MB, GeoIP2-ISP, database.sql,
  yt-dlp, guess, cidr.db, login-bg.mp4). Objetos descargados del LFS del
  original; al pushear se suben al LFS propio. Añadidos GeoLite2-City/Country
  (extraídos del payload oficial 2.0.1, en LFS) — eran los únicos archivos del
  payload oficial no trackeados en git.
- **Telemetría desactivada**: `CertbotCronJob` ya no llama a
  `DiagnosticsService::submitPanelLogs()` (enviaba logs de errores al servidor
  central del original). La función sigue existiendo pero sin llamante.
- **Constantes de update** (`src/core/Config/AppConfig.php`):
  `GIT_OWNER=m4sterinfo`, `GIT_REPO_MAIN=UyPanel`, `GIT_REPO_UPDATE=UyPanel_Update`.
- **Release propio publicado**: https://github.com/m4sterinfo/UyPanel/releases/tag/2.0.1
  con `UyPanel.zip` (434 MB, payload íntegro verificado: 6,321 archivos,
  binarios reales), `loadbalancer.tar.gz` (291 MB) y `hashes.md5`. El Quick
  Install del README ya funciona apuntando a nuestro repo.
- **Validación**: php -l en todos los PHP modificados, py_compile del
  instalador, `make main` + `make lb` completos.

### 2026-11-02 — Renombrado técnico completo en producción (xc_vm → uypanel)
- **Logo propio**: `docs/_media/UyPanel_logo.jpg` (descargado de
  uy.v2apk.com/logos/UyPanel_logo.jpg), referenciado en los 3 READMEs.
- **Repo**: renombradas rutas con `xc_vm` en el nombre (ministra/template/uypanel,
  nginx realip_uypanel.conf) y barrido de identificadores técnicos en 143
  archivos: `/home/uypanel`, servicio systemd `uypanel`, BDs `uypanel` +
  `uypanel_migrate`, usuario/grupo `uypanel`, tarball `uypanel.tar.gz`.
  También quitada del instalador la directiva redis `lazyfree-lazy-server-delay`.
- **Migración del servidor en producción** (con backup previo
  `/home/debian/pre_rename_backup/dbs_pre_rename.sql.gz`, 147 MB):
  BDs renombradas por RENAME TABLE (65+66 tablas) + grants nuevos + drop de las
  viejas; usuario/grupo del sistema renombrados (uid 105); directorio movido a
  `/home/uypanel`; payload rebrandeado desplegado; config.ini actualizado
  (database=uypanel); redis.conf con rutas nuevas; crontabs root y uypanel
  actualizados; fstab tmpfs con rutas nuevas; unidad systemd `uypanel.service`
  (xc_vm.service eliminada); setcap nginx; message_of_day "Welcome to UyPanel".
- **IMPORTANTE — shim temporal**: los binarios PHP/nginx/nginx_rtmp tienen el
  prefix `/home/xc_vm` COMPILADO DENTRO (ini path, extension_dir, prefix de
  nginx). Existe symlink `/home/xc_vm -> /home/uypanel` para que funcionen.
  Tarea de roadmap: reconstruir binarios con prefix `/home/uypanel` usando
  `third_party/UyPanel_Binaries` (ajustar scripts de build) y entonces eliminar
  el symlink. NO borrar el symlink sin los binarios nuevos.
- Verificado: servicio active, DB conectada, sin migraciones pendientes,
  extensiones PHP cargando, panel HTTP 200/302 login, crons ejecutando,
  nginx_rtmp arriba, puertos 80/443/6379.

### Pendiente de hacer en el servidor (no requiere código)
- Nota: el PHP/nginx/redis en producción son los binarios Ubuntu del tarball
  2.0.1 con librerías adaptadas (`libcurl3t64-gnutls`, `libjpeg.so.8` ABI 8 en
  `/usr/local/lib`). Si se reconstruyen binarios Debian desde
  `third_party/XC_VM_Binaries`, esas librerías dejan de ser necesarias.

### 2026-11-02 — Nota sobre submódulos (desalineación de versiones)
- Los submódulos apuntan al estado actual de sus `main`, que ya construyen
  binarios más nuevos que los del 2.0.1 (PHP 8.3 + extensión privada
  `xcvm_core` en Rust, nginx más nuevo; ver `third_party/XC_VM_Binaries/CLAUDE.md`).
- Para reconstruir binarios compatibles con el panel 2.0.1 actual habría que
  retroceder los submódulos a commits de la época (~marzo 2026). Decidir
  cuando toque: o se actualiza el panel a la nueva generación de binarios, o
  se pinean submódulos antiguos.

## Trabajo pendiente / Roadmap

- [x] Crear repo propio en GitHub y pushear (hecho 2026-11-02: m4sterinfo/UyPanel)
- [x] Separación total del original: forks de soporte propios + rebranding + release 2.0.1
- [x] Renombrado técnico en producción: /home/uypanel, servicio uypanel, BDs uypanel*
- [ ] **Reconstruir binarios con prefix /home/uypanel** (third_party/UyPanel_Binaries,
      ajustar scripts de build) y eliminar el symlink /home/xc_vm — ver nota shim
- [ ] Personalizar READMEs/branding de los forks de soporte cuando se toque
- [ ] Re-importar EPG desde las 22 URLs migradas (los datos EPG no se migran)
- [ ] Revisar página de Settings del panel (quedó en defaults tras migración)

## Notas técnicas importantes

- El Makefile construye payloads: `make main` (panel), `make lb` (load
  balancer, excluye `modules/` y código admin), `make main_update`/`lb_update`.
- Arquitectura obligatoria: Controller → Service → Repository → Database;
  reglas en `.github/instructions/` y `ARCHITECTURE.md`.
- Los módulos (`src/modules/`) son solo del panel principal; el LB no los lleva.
- KeyDB 6.3.4 (= Redis 6.2): no soporta directivas de Redis 7+ como
  `lazyfree-lazy-server-delay` (ya eliminada del redis.conf en producción).
- MariaDB 11.8 en el servidor; el instalador escribe `/etc/mysql/my.cnf` propio.
