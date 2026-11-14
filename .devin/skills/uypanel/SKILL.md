---
name: uypanel
description: Contexto vivo del proyecto UyPanel (fork de XC_VM 2.0.1) — arquitectura, comandos, convenciones y registro de cambios propios. Invocar antes de trabajar en el proyecto y actualizar tras cada mejora.
argument-hint: "[tema opcional: build|deploy|arquitectura|migracion]"
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - glob
---

# UyPanel — Panel IPTV (fork independiente de XC_VM)

Eres el agente de desarrollo de **UyPanel**, un fork independiente de
[Vateron-Media/XC_VM](https://github.com/Vateron-Media/XC_VM) basado en el tag
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
│  ├─ UyPanel_Binaries/  # Build de PHP/nginx/redis/nginx_rtmp por distro (docker)
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

# Binarios (PHP 8.3, nginx, redis/KeyDB, nginx_rtmp) y FFmpeg:
cd third_party/UyPanel_Binaries && ./build_all.sh  # usa docker/
cd third_party/UyPanel_FFMPEG && make             # ver su README/Makefile

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
- UyPanel 2.0.3 (base XC_VM 2.0.1) instalado en `/home/uypanel` (nginx 80/443, nginx_rtmp,
  4 pools php-fpm 8.3.36, KeyDB 6.3.4 en 6379 con auth, MariaDB 11.8)
- Base de datos migrada desde XUI (27,672 líneas, 240,557 streams)
- Acceso admin: `http://198.27.81.133/7JDEeDMG` (código tras la reinstalación
  limpia del 2026-11-04; el `x9access9x` anterior murió con la BD vieja)
- Credenciales MariaDB: `/home/debian/credentials.txt` (BD: `uypanel`) (NO commitear nunca)
- Binarios nativos Debian 13 (PHP 8.3.36 hardened, nginx 1.29.6). El instalador
  (desde 2.0.3) instala sus deps runtime: `libcurl3t64-gnutls` + `libsodium23`
  (verificado en containers debian:13/debian:12 limpios). El hack de
  `libjpeg.so.8` en `/usr/local/lib` solo aplica al rollback de binarios 8.1
  viejos — ver `/home/debian/AGENTS.md`.

## Relación con upstream

- `origin` = https://github.com/m4sterinfo/UyPanel (NUESTRO repo; push normal).
- El proyecto original (Vateron-Media/XC_VM) solo se consulta si se quiere
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
- **Checklist obligatorio** (lección de la 2.0.3, que hubo que publicar 2 veces):
  1. Bump de `UyPanel_VERSION` en `src/core/Config/AppConfig.php` — si se olvida,
     el payload se autoreporta con la versión vieja y los paneles instalados
     quedan en loop de "update disponible" (el delta no lleva AppConfig.php).
  2. Commit + push a main.
  3. `gh release create <tag> --title "UyPanel <tag>" --notes ...` — el tag se
     crea en HEAD de main; el CI arma y sube los assets.
  4. Verificar: assets publicados y `UyPanel_VERSION` correcto DENTRO del zip.
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
  servers.php auto-provisión).

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
