# Test Install Container

Contenedor Docker de prueba (Ubuntu 22.04) para verificar la instalación de UyPanel desde `dist/UyPanel.zip`.

## Requisitos

- Docker + Docker Compose
- `dist/UyPanel.zip` compilado (vía `make main`)

## Uso

```bash
# Compilar, arrancar e instalar (todo de una vez)
./tools/test-install/test_release.sh

# O paso a paso:
./tools/test-install/test_release.sh build     # compilar la imagen
./tools/test-install/test_release.sh run       # arrancar el contenedor
./tools/test-install/test_release.sh install   # ejecutar la instalación

# Entrar al contenedor manualmente
docker exec -it xcvm-test-install bash

# Ver el log de la instalación
./tools/test-install/test_release.sh logs

# Limpiar
./tools/test-install/test_release.sh clean
```

## Qué se comprueba

El script automático (`auto_install.sh`):

1. Descomprime `UyPanel.zip`
2. Ejecuta `python3 install` con respuestas automáticas a las preguntas interactivas
3. Comprueba la presencia de los archivos clave tras la instalación:
   - `/home/uypanel/status`, `console.php`, `autoload.php`, `bootstrap.php`
   - `/home/uypanel/config/config.ini`
   - `/home/uypanel/bin/nginx/sbin/nginx`
   - `/home/uypanel/bin/php/bin/php`
   - `/home/uypanel/bin/redis/redis-server`

## Puertos

| Host  | Container | Propósito |
|-------|-----------|-----------|
| 8880  | 80        | HTTP      |
| 8443  | 443       | HTTPS     |

## Notas

- El contenedor se arranca con `--privileged` y systemd (PID 1) — necesario para `systemctl`, `mount`, tmpfs
- sysctl NO se sobrescribe (respuesta N) — en el contenedor esto no funciona
- MariaDB se instala y configura dentro del contenedor
