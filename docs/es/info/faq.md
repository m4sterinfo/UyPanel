<h1 align="center">❓ FAQ — Preguntas frecuentes</h1>

<p align="center">
  Aquí están las respuestas a las dudas y problemas más frecuentes al trabajar con <b>UyPanel</b>.
</p>

---

## ⚙️ Problemas con streams

<details>
<summary><b>❌ Mi stream no arranca en MAIN o LB</b></summary>

---

### 🔍 Diagnóstico

Conéctate a la consola del servidor y ejecuta:

```bash
sudo -u uypanel /home/uypanel/bin/php/bin/php /home/uypanel/console.php monitor 291
```

> 🧩 Donde `291` es el **ID de tu stream** (sustitúyelo por el tuyo).

---

### 📄 Qué hace el comando

El comando **monitor** intentará arrancar el stream manualmente y mostrará el error si no puede.

---

### ⚠️ Causas posibles

#### 1️⃣ Faltan librerías del sistema

Si la salida contiene un error tipo:

```
error while loading shared libraries: libxyz.so.1: cannot open shared object file
```

Instala la librería faltante:

```bash
sudo apt install <nombre_libreria>
```

Tras instalarla, repite el test.

> 💬 Infórmame si hace falta añadir alguna librería a la instalación.

---

#### 2️⃣ El error no es de librerías

Si el error es de otro tipo — mándame también su salida para ayudarte con el diagnóstico.

---

### 🧾 Resumen

1. Ejecuta el comando de diagnóstico.
2. Revisa si hay errores.
3. Instala las librerías faltantes.
4. Informa de cualquier otro error para analizarlo.

---

</details>

<details>
<summary><b>❌ El streaming se corta con error «IP_MISMATCH» o «TOKEN_EXPIRED»</b></summary>

---

Son funciones de seguridad, no bugs:

- **TOKEN_EXPIRED** — el token de sesión expiró. El usuario debe reconectarse.
- **IP_MISMATCH** — la IP del usuario cambió durante el stream (suele interpretarse como sharing de cuenta).

**Ajustes relacionados:**
- `restrict_same_ip` — rigor de la comprobación de IP
- `disallow_2nd_ip_con` — bloqueo de conexiones simultáneas desde IPs distintas

Si esto crea problemas a usuarios legítimos (p. ej., las redes móviles cambian de IP a menudo), ajusta el nivel de restricciones en la configuración del panel admin.

---

</details>

---

## 🔐 Problemas de acceso y login

<details>
<summary><b>❌ IP bloqueada — no puedo entrar</b></summary>

---

La protección brute-force bloquea la IP tras superar el límite de intentos fallidos de login. Se gestiona con los ajustes:

- `bruteforce_mac_attempts` — intentos por MAC por ventana
- `bruteforce_username_attempts` — intentos por usuario por ventana
- `flood_limit` — límite general de peticiones por ventana

**Cómo desbloquear:**

1. **Desde el panel admin:** Herramientas → Gestión de IPs → eliminar de la lista de bloqueo.
2. **Vía CLI:** `sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools flush` — resetea todos los bloqueos.
3. **Si estás totalmente bloqueado:** Usa `console.php tools rescue` para crear un código de recuperación de acceso (ver [Herramientas CLI](es-es/development/cli-tools.md)).

---

</details>

<details>
<summary><b>❌ Olvidé la contraseña de admin / no puedo entrar de ninguna forma</b></summary>

---

Crea un nuevo usuario administrador vía CLI:

```bash
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools user
```

El comando muestra un login y contraseña aleatorios con permisos completos. Entra, cambia la contraseña y elimina el usuario de rescate.

Si no conoces la URL del panel admin — crea un código de recuperación:

```bash
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools rescue
```

---

</details>

---

## 🗄 Base de datos y configuración

<details>
<summary><b>❌ «Couldn't connect to database» al arrancar</b></summary>

---

El problema más frecuente. Causas:

1. **Datos incorrectos en `config.ini`** — revisa `host`, `port`, `db_user`, `db_pass`, `db_name`
2. **MySQL/MariaDB no está arrancado** — `sudo systemctl status mariadb`
3. **Red inaccesible** — el servidor de BD está en otro host, el puerto bloqueado por firewall
4. **El usuario no tiene permisos** — re-otórgalos vía `console.php tools mysql`

**Corrección:** Edita `/home/uypanel/config/config.ini` y luego ejecuta:

```bash
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php status
```

---

</details>

<details>
<summary><b>❌ La migración de la base de datos falla al actualizar</b></summary>

---

Los archivos SQL de `migrations/` se ejecutan automáticamente al actualizar. Si uno falla:

- La migración queda registrada con estado `[WARN]` — no se repite automáticamente.
- Causas frecuentes: error de sintaxis, la tabla ya existe, conflicto de claves foráneas, permisos insuficientes para ALTER.

**Depuración:**

1. Mira en la salida de consola qué migración falló.
2. Abre el archivo en `migrations/` y estudia el SQL.
3. Corrige el problema a mano en MySQL — la siguiente actualización continuará desde donde se detuvo.

Detalles: [Migraciones de BD](es-es/development/cli-tools.md#migraciones-de-la-base-de-datos).

---

</details>

---

## 🌐 SSL y Nginx

<details>
<summary><b>❌ La generación del certificado SSL falla</b></summary>

---

`console.php certbot` puede terminar con distintos códigos de error:

| Error | Causa | Solución |
|---|---|---|
| Error 3 | El dominio es una IP pelada | Certbot requiere un nombre de dominio, no una IP |
| Error 4 | El dry run falló — puerto 80/443 ocupado | Detén el servicio en conflicto: `sudo lsof -i :80` |
| Error 0 | Archivos no encontrados tras la generación | Revisa `/home/uypanel/bin/certbot/logs/uypanel.log` |
| Error 2 | Error inesperado de certbot | Revisa los logs, asegúrate de que el DNS apunta a tu servidor |

**También:** Elimina los lock-files obsoletos si certbot fue interrumpido:

```bash
sudo rm -f /home/uypanel/bin/certbot/*/.certbot.lock
```

---

</details>

<details>
<summary><b>❌ Nginx no se reinicia — conflicto de puertos</b></summary>

---

UyPanel arranca **dos** instancias de nginx:

1. **nginx** (`bin/nginx/`) — tráfico HTTP(S)
2. **nginx_rtmp** (`bin/nginx_rtmp/`) — streaming RTMP

Cada una puede caer si su puerto ya está ocupado.

**Diagnóstico:**

```bash
sudo netstat -tlnp | grep -E ':80|:443|:1935'
```

**Solución:** Cambia el puerto de emisión en la configuración del panel admin y regenera las configs:

```bash
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools ports
```

---

</details>

---

## 🔄 Actualizaciones y servicio

<details>
<summary><b>❌ La actualización no descarga / la suma de comprobación no coincide</b></summary>

---

El sistema de actualizaciones descarga archivos de GitHub Releases. Si no funciona:

- **Red/firewall** bloquea el acceso a GitHub
- **Descarga parcial** — la conexión se cortó a mitad
- **MD5 no coincide** — el archivo está dañado (la actualización se cancela de forma segura)

La actualización nunca se aplica si la suma de comprobación no coincide. Reintenta tras resolver los problemas de red:

```bash
sudo -u uypanel /home/uypanel/bin/php/bin/php /home/uypanel/console.php update update
```

---

</details>

<details>
<summary><b>❌ El servicio se detiene de repente / no termina limpiamente</b></summary>

---

El comando service usa una escalera de señales de terminación. Si hay procesos colgados:

```bash
# Ver los procesos colgados
ps -u uypanel

# Terminación forzada si hace falta
sudo killall -9 -u uypanel

# Reinicio limpio
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php service start
```

Causas frecuentes: deadlock en una transacción PHP, bucle infinito en el procesado de un stream, o un socket de red esperando respuesta.

---

</details>

---

## 🛠 Permisos y sistema

<details>
<summary><b>❌ Los errores «Permission denied» vuelven una y otra vez</b></summary>

---

Ejecuta el comando status — corrige automáticamente todos los problemas de permisos conocidos:

```bash
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php status
```

Qué corrige:
- Permisos de los sockets PHP-FPM (`bin/php/sockets/*`)
- Propietario de los directorios de contenido (`content/streams/`)
- Propietario de las configs (`config/`)
- Bit de ejecución de `daemons.sh`
- Permisos de las interfaces de red (`/sys/class/net`)

Si los permisos se rompen tras cada reinicio, comprueba que el usuario del sistema `uypanel` existe y es propietario de `/home/uypanel`.

---

</details>

<details>
<summary><b>❌ El Load Balancer aparece offline / no se sincroniza con MAIN</b></summary>

---

Los servidores LB sondean a MAIN por HTTP y procesan señales. Cuando la sincronización se rompe:

1. **Red:** el LB no puede alcanzar el puerto HTTP de MAIN — revisa las reglas del firewall
2. **Base de datos:** el LB no puede conectarse al MySQL de MAIN — re-otorga los privilegios:
   ```bash
   sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools mysql
   ```
3. **Timeout:** si `last_check_ago` supera los 180 segundos, el servidor se marca como offline

**Depuración:** Lanza en MAIN para comprobar la comunicación:

```bash
sudo -u uypanel /home/uypanel/bin/php/bin/php /home/uypanel/console.php watchdog
```

---

</details>

---

📘 *Esta página se actualiza con el tiempo. Si encontraste un problema típico nuevo — propónlo en los [Issues](https://github.com/m4sterinfo/UyPanel/issues).*

---
