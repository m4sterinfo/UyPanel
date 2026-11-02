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
# Установка зависимостей (Ubuntu/Debian)
# ====================
echo "=== Обновление списка пакетов и установка зависимостей ==="

# Обновляем индекс пакетов
sudo apt update -qq

# Основной набор для сборки PHP-расширений + git
sudo apt install -y --no-install-recommends \
    build-essential \
    autoconf \
    automake \
    libtool \
    git \
    pkg-config
# ====================

echo "=== Проверка окружения ==="
for bin in "$PHP_BIN" "$PHPIZE" "$PHP_CONFIG"; do
    if [ ! -x "$bin" ]; then
        echo "Ошибка: $bin не найден"
        exit 1
    fi
done

if [ ! -f "$PHP_INI" ]; then
    echo "Ошибка: Основной php.ini не найден по пути $PHP_INI"
    exit 1
fi

echo "PHP binary     : $PHP_BIN"
echo "php.ini        : $PHP_INI"
echo "Extension dir  : $EXT_DIR"

echo
echo "=== Подготовка сборки Xdebug ==="
rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"
cd "$TMP_DIR"

git clone "$XDEBUG_REPO" .
$PHPIZE
./configure --with-php-config="$PHP_CONFIG"

echo
echo "=== Сборка ==="
make -j$(nproc)

echo
echo "=== Установка расширения ==="
cp modules/xdebug.so "$EXT_DIR"/

echo
echo "=== Добавление настроек в $PHP_INI ==="

# Проверяем, не добавляли ли уже секцию Xdebug
if grep -q "^;.*Xdebug" "$PHP_INI" || grep -q "^zend_extension=.*xdebug.so" "$PHP_INI"; then
    echo "Внимание: похоже, Xdebug уже присутствует в php.ini"
    echo "Новые настройки добавляться НЕ будут, чтобы избежать дублирования."
else
    cat >> "$PHP_INI" <<EOF


; ====================
; Xdebug (добавлено $(date '+%Y-%m-%d %H:%M:%S'))
; ====================
zend_extension=xdebug.so

; Включаем отладку и профайлинг
xdebug.mode=debug,profile
xdebug.start_with_request=trigger
xdebug.output_dir=/home/uypanel/xdebug
xdebug.client_host=127.0.0.1
xdebug.client_port=9003
xdebug.discover_client_host=0

; Прочие настройки (по желанию)
; xdebug.log=/tmp/xdebug.log
; xdebug.profiler_enable=1
; xdebug.profiler_output_name=cachegrind.out.%p
; ====================
EOF

    echo "Настройки Xdebug успешно добавлены в конец файла $PHP_INI"
fi

sudo chown uypanel:uypanel -R /home/uypanel >/dev/null 2>&1
sudo chmod 777 "$EXT_DIR/xdebug.so" >/dev/null 2>&1

echo
echo "=== Перезапуск php-fpm ==="
if systemctl is-active --quiet php-fpm; then
    sudo systemctl restart php-fpm
else
    echo "php-fpm не обнаружен в systemd, перезапустите вручную"
fi
sleep 2

echo
echo "=== Проверка загрузки модуля ==="
if $PHP_BIN -r 'exit(extension_loaded("xdebug") ? 0 : 1);'; then
    echo "Xdebug успешно загружен (проверено через extension_loaded)"
else
    echo "Ошибка: Xdebug не загрузился"
    echo "Проверьте php -r 'var_dump(extension_loaded(\"xdebug\"));'"
    exit 1
fi

echo
echo "=============================="
echo "Xdebug установлен успешно"
echo
echo "📂 Папка профилей: /tmp/xdebug"
echo "Для php-fpm не забудь перезапуск:"
echo "  systemctl restart php-fpm      # если systemd"
echo "  или"
echo "  $PHP_PREFIX/sbin/php-fpm --reload"
echo "  или"
echo "  pkill -USR2 php-fpm"
echo
echo "=============================="
