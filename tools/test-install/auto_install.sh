#!/bin/bash
# auto_install.sh — instalación automática de UyPanel en el contenedor
# Responde a las preguntas interactivas del instalador
set -e

INSTALL_DIR="/opt/xcvm-install"
cd "$INSTALL_DIR"

# Comprobamos que exista UyPanel.zip
if [[ ! -f UyPanel.zip ]]; then
    echo "ERROR: UyPanel.zip not found in $INSTALL_DIR"
    exit 1
fi

echo "==> Descompresión de UyPanel.zip..."
unzip -o UyPanel.zip

# Comprobamos que el instalador y el archivo están en su sitio
if [[ ! -f install ]]; then
    echo "ERROR: install script not found after unzip"
    exit 1
fi
if [[ ! -f uypanel.tar.gz ]]; then
    echo "ERROR: uypanel.tar.gz not found after unzip"
    exit 1
fi

echo "==> Lanzando el instalador..."
# Respuestas a las preguntas interactivas:
#   1) "Continue and overwrite? (Y / N)" — Y (por si /home/uypanel/ existe)
#   2) "HTTP port (default 80):" — línea vacía (default 80)
#   3) "HTTPS port (default 443):" — línea vacía (default 443)
#   4) "Overwrite sysctl configuration? (Y / N):" — N (sysctl no se puede cambiar en el contenedor)

printf 'Y\n\n\nN\n' | python3 install 2>&1 | tee /var/log/xcvm_install.log
EXIT_CODE=${PIPESTATUS[1]}

echo ""
echo "========================================="
if [[ $EXIT_CODE -eq 0 ]]; then
    echo "  INSTALL COMPLETED (exit code: 0)"
else
    echo "  INSTALL FAILED (exit code: $EXIT_CODE)"
fi
echo "========================================="
echo ""

# Comprobamos que los archivos clave están en su sitio
echo "==> Post-install verification:"
CHECKS=(
    "/home/uypanel/status"
    "/home/uypanel/console.php"
    "/home/uypanel/autoload.php"
    "/home/uypanel/bootstrap.php"
    "/home/uypanel/config/config.ini"
    "/home/uypanel/bin/nginx/sbin/nginx"
    "/home/uypanel/bin/php/bin/php"
    "/home/uypanel/bin/redis/redis-server"
)

PASS=0
FAIL=0
for f in "${CHECKS[@]}"; do
    if [[ -e "$f" ]]; then
        echo "  [OK]   $f"
        ((PASS++))
    else
        echo "  [FAIL] $f"
        ((FAIL++))
    fi
done

echo ""
echo "Results: $PASS passed, $FAIL failed out of ${#CHECKS[@]} checks"

# Comprobamos los servicios (si systemd funciona)
if command -v systemctl &>/dev/null && systemctl is-system-running &>/dev/null 2>&1; then
    echo ""
    echo "==> Service status:"
    systemctl status uypanel --no-pager 2>/dev/null || echo "  uypanel service not running (may need manual start)"
fi

exit $EXIT_CODE
