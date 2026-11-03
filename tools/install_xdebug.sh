#!/bin/bash
set -e

PHP_PREFIX="/home/uypanel/bin/php"
PHP_BIN="$PHP_PREFIX/bin/php"
PHPIZE="$PHP_PREFIX/bin/phpize"
PHP_CONFIG="$PHP_PREFIX/bin/php-config"
PHP_INI="$PHP_PREFIX/lib/php.ini"

EXT_DIR=$($PHP_BIN -i | awk -F'=> ' '/extension_dir/ {print $2}' | tr -d ' ')

XDEBUG_REPO="https://github.com/xdebug/xdebug.git"
TMP_DIR="/tmp/php-xdebug-build"

# ====================
# Instalación de dependencias (Ubuntu/Debian)
# ====================
echo "=== Actualización de la lista de paquetes e instalación de dependencias ==="

# Actualizamos el índice de paquetes
sudo apt update -qq

# Conjunto básico para compilar extensiones PHP + git
sudo apt install -y --no-install-recommends \
    build-essential \
    autoconf \
    automake \
    libtool \
    git \
    pkg-config
# ====================

echo "=== Comprobación del entorno ==="
for bin in "$PHP_BIN" "$PHPIZE" "$PHP_CONFIG"; do
    if [ ! -x "$bin" ]; then
                echo "Error: $bin no encontrado"
        exit 1
    fi
done

if [ ! -f "$PHP_INI" ]; then
        echo "Error: el php.ini principal no se encontró en la ruta $PHP_INI"
    exit 1
fi

echo "PHP binary     : $PHP_BIN"
echo "php.ini        : $PHP_INI"
echo "Extension dir  : $EXT_DIR"

echo
echo "=== Preparación de la compilación de Xdebug ==="
rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"
cd "$TMP_DIR"

git clone "$XDEBUG_REPO" .
$PHPIZE
./configure --with-php-config="$PHP_CONFIG"

echo
echo "=== Compilación ==="
make -j$(nproc)

echo
echo "=== Instalación de la extensión ==="
cp modules/xdebug.so "$EXT_DIR"/

echo
echo "=== Añadiendo ajustes a $PHP_INI ==="

# Comprobamos si ya se añadió antes la sección Xdebug
if grep -q "^;.*Xdebug" "$PHP_INI" || grep -q "^zend_extension=.*xdebug.so" "$PHP_INI"; then
        echo "Atención: parece que Xdebug ya está presente en php.ini"
        echo "Los ajustes nuevos NO se añadirán, para evitar duplicados."
else
    cat >> "$PHP_INI" <<EOF


; ====================
; Xdebug (añadido $(date '+%Y-%m-%d %H:%M:%S'))
; ====================
zend_extension=xdebug.so

; Activamos la depuración y el profiling
xdebug.mode=debug,profile
xdebug.start_with_request=trigger
xdebug.output_dir=/home/uypanel/xdebug
xdebug.client_host=127.0.0.1
xdebug.client_port=9003
xdebug.discover_client_host=0

; Otros ajustes (opcional)
; xdebug.log=/tmp/xdebug.log
; xdebug.profiler_enable=1
; xdebug.profiler_output_name=cachegrind.out.%p
; ====================
EOF

        echo "Ajustes de Xdebug añadidos con éxito al final del archivo $PHP_INI"
fi

sudo chown uypanel:uypanel -R /home/uypanel >/dev/null 2>&1
sudo chmod 777 "$EXT_DIR/xdebug.so" >/dev/null 2>&1

echo
echo "=== Reinicio de php-fpm ==="
if systemctl is-active --quiet php-fpm; then
    sudo systemctl restart php-fpm
else
        echo "php-fpm no detectado en systemd, reinícialo manualmente"
fi
sleep 2

echo
echo "=== Comprobación de la carga del módulo ==="
if $PHP_BIN -r 'exit(extension_loaded("xdebug") ? 0 : 1);'; then
        echo "Xdebug cargado con éxito (verificado vía extension_loaded)"
else
        echo "Error: Xdebug no se cargó"
        echo "Comprueba php -r 'var_dump(extension_loaded(\"xdebug\"));'"
    exit 1
fi

echo
echo "=============================="
echo "Xdebug instalado con éxito"
echo
echo "📂 Carpeta de perfiles: /tmp/xdebug"
echo "Para php-fpm no olvides reiniciar:"
echo "  systemctl restart php-fpm      # si systemd"
echo "  o"
echo "  $PHP_PREFIX/sbin/php-fpm --reload"
echo "  o"
echo "  pkill -USR2 php-fpm"
echo
echo "=============================="
