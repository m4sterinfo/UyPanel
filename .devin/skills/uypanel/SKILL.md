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

1. **ANTES de trabajar**: lee `STATE.md` (junto a este archivo) — contiene el
   estado actual, decisiones tomadas y trabajo pendiente.
2. **DESPUÉS de cada cambio significativo**: actualiza `STATE.md`:
   - Añade entrada al "Registro de cambios" (fecha, qué, por qué, archivos).
   - Actualiza secciones afectadas (estado, pendientes, decisiones).
   - Esto es lo que mantiene el skill vivo y actualizado con el proyecto.
3. **Convenciones de código**: el repo hereda reglas estrictas en
   `.github/instructions/*.instructions.md` (arquitectura, PHP, bash, nginx,
   migraciones, build). Léelas antes de tocar código de `domain/`, `core/`,
   `public/` o `streaming/`. La especificación completa está en
   `ARCHITECTURE.md` (~83 KB) y `specs/MODULE_SYSTEM_SPEC.md`.
4. **Commits**: mensajes en inglés, estilo conventional
   (`feat:`, `fix:`, `docs:`, `refactor:`, `chore:`), enfocados al "por qué".
   El usuario hace el push a GitHub; nunca hacer push sin pedido explícito.

## Estructura del repo

```
UyPanel/
├─ src/                  # Código fuente del panel (lo que se despliega en /home/xc_vm)
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
├─ docs/                 # Documentación (mkdocs, en/ y ru/)
├─ third_party/          # SUBMÓDULOS git (sistemas de build de binarios)
│  ├─ XC_VM_Binaries/    # Build de PHP/nginx/redis/nginx_rtmp por distro (docker)
│  └─ XC_VM_FFMPEG/      # Build de FFmpeg 8.0/7.1/4.0 (docker)
├─ install               # Instalador Python (con soporte Debian 12/13 — parche nuestro)
├─ Makefile              # Sistema de build de payloads
├─ ARCHITECTURE.md       # Especificación técnica completa
└─ .devin/skills/uypanel/  # Este skill (SKILL.md + STATE.md)
```

## Comandos clave

```bash
# Build de payloads (generan dist/)
make main        # → dist/xc_vm.tar.gz + dist/XC_VM.zip (panel principal)
make lb          # → dist/loadbalancer.tar.gz (load balancer; excluye modules/, admin, etc.)
make main_update # → dist/update.tar.gz (actualización de panel instalado)
make lb_update   # → dist/loadbalancer_update.tar.gz

# Binarios (PHP 8.1, nginx, redis/KeyDB, nginx_rtmp) y FFmpeg:
cd third_party/XC_VM_Binaries && ./build_all.sh   # usa docker/
cd third_party/XC_VM_FFMPEG && make               # ver su README/Makefile

# Sintaxis PHP (validar antes de commitear cambios PHP):
tools/php_syntax_check.sh   # o: find src -name "*.php" | xargs -n1 php -l

# Servidor en producción (Debian 13, panel instalado en /home/xc_vm):
sudo systemctl start|stop|restart xc_vm
sudo /home/xc_vm/status 1                          # estado: DB + migraciones
sudo /home/xc_vm/bin/php/bin/php /home/xc_vm/console.php <comando>   # CLI del panel
journalctl -u xc_vm -f
```

## Entorno de producción (servidor actual)

- Debian 13 (trixie), 8 cores, 32 GB RAM, IP `198.27.81.133`
- Panel XC_VM 2.0.1 instalado en `/home/xc_vm` (nginx 80/443, nginx_rtmp,
  4 pools php-fpm 8.1.33, KeyDB 6.3.4 en 6379 con auth, MariaDB 11.8)
- Base de datos migrada desde XUI (27,672 líneas, 240,557 streams)
- Acceso admin: `http://198.27.81.133/x9access9x`
- Credenciales MariaDB: `/home/debian/credentials.txt` (NO commitear nunca)
- Particularidades Debian 13 del PHP incluido (build Ubuntu):
  `libcurl3t64-gnutls` + `libjpeg.so.8` (ABI 8) en `/usr/local/lib` — ver
  `/home/debian/AGENTS.md` para detalles. NO sustituir libjpeg por symlink a la
  ABI 62 (layouts de struct incompatibles).

## Relación con upstream

- `origin` apunta a Vateron-Media/XC_VM (solo lectura para nosotros).
- Rama `main` local = nuestro código, basada en el tag 2.0.1.
- Ver qué hemos cambiado respecto al original: `git diff 2.0.1..HEAD`
- Traer fixes upstream si algún día se quiere:
  `git fetch origin && git log 2.0.1..origin/main --oneline` y cherry-pick
  selectivo (NO merge ciego: rompería nuestras modificaciones).

## Al crear el repo propio en GitHub

- Cambiar `origin` a la URL del repo nuevo (ej. `git remote set-url origin ...`
  y añadir upstream como remote aparte si se quiere seguir al original).
- El `Makefile` consulta el API de releases de Vateron-Media (`LAST_TAG`) —
  actualizarlo al repo propio cuando se empiecen a publicar releases.
- Renombrar branding "XC_VM" → "UyPanel" progresivamente (README, docs,
  nombres de servicio si se decide; con cuidado: el nombre de servicio systemd
  `xc_vm` y rutas `/home/xc_vm` están arraigados en scripts y configs).

## Tono de trabajo

- Cambios pequeños y enfocados; un tema por commit.
- Validar sintaxis PHP y probar en el servidor antes de commitear.
- Ante duda de arquitectura: ARCHITECTURE.md manda; luego las instructions.
- Registrar TODO cambio en STATE.md — el skill es la memoria del proyecto.
