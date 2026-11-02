# UyPanel — Estado del proyecto (documento vivo)

> Este archivo lo mantiene el agente (vía skill `uypanel`). Se actualiza tras
> cada cambio significativo. Última actualización: 2026-11-02.

## Identidad

- **Nombre**: UyPanel
- **Base**: fork de XC_VM tag `2.0.1` (commit `e21f9063`), historial git completo conservado
- **Rama de trabajo**: `main` (nuestra)
- **Upstream**: `origin` = https://github.com/Vateron-Media/XC_VM (solo lectura)
- **Repo propio**: pendiente de crear; el usuario hará el push
- **Licencia**: AGPL-3.0 (heredada, mantener)

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

- [ ] Crear repo propio en GitHub y cambiar `origin` (usuario hace el push)
- [ ] Actualizar `LAST_TAG` del Makefile al repo propio cuando haya releases
- [ ] Rebranding progresivo XC_VM → UyPanel (README, docs, UI) — decidir alcance
      (cuidado: `xc_vm` está arraigado en service systemd, rutas, configs)
- [ ] Re-importar EPG desde las 22 URLs migradas (los datos EPG no se migran)
- [ ] Revisar página de Settings del panel (quedó en defaults tras migración)
- [ ] Decidir política de versionado propio (ej. `2.0.1-uy.1`)

## Notas técnicas importantes

- El Makefile construye payloads: `make main` (panel), `make lb` (load
  balancer, excluye `modules/` y código admin), `make main_update`/`lb_update`.
- Arquitectura obligatoria: Controller → Service → Repository → Database;
  reglas en `.github/instructions/` y `ARCHITECTURE.md`.
- Los módulos (`src/modules/`) son solo del panel principal; el LB no los lleva.
- KeyDB 6.3.4 (= Redis 6.2): no soporta directivas de Redis 7+ como
  `lazyfree-lazy-server-delay` (ya eliminada del redis.conf en producción).
- MariaDB 11.8 en el servidor; el instalador escribe `/etc/mysql/my.cnf` propio.
