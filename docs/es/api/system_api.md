<h1 align="center">📡 API del Sistema (métodos por verificar)</h1>

<p align="center">
 Esta API ofrece varias funcionalidades, incluyendo la consulta de logs, la gestión de VOD y de streams, la obtención de estadísticas, la ejecución de comandos en segundo plano y mucho más.
</p>

---

## Ubicación de los archivos

La System API la atiende el controlador:

```
src/public/Controllers/Api/AdminApiController.php
```

HTTP entry-point: `/api.php` → nginx enruta a `public/index.php` con el parámetro `XC_API=internal`.

## Resumen de la arquitectura de la API
**URI base**: `http://<host>:<puerto http>/api.php`  
**Autenticación**: parámetro `password` que debe coincidir con la configuración `live_streaming_pass`
**Ejemplo**: `http://<host>:<puerto http>/api.php?password=<live_streaming_pass>&action=<api endpoint>`  

---

## Endpoints principales de la API

### 1. Gestión de VOD
#### **GET** `/api.php?action=vod`
**Descripción:** Arranca o detiene streams de video bajo demanda (VOD).
**Parámetros:**
| Parámetro | Tipo | Requerido | Descripción |
|------------|----------|----------|-------------|
|stream_ids |array de enteros |sí |Lista de identificadores de streams. |
|function |string |sí |Acción a ejecutar (`start` o `stop`)|

---

### 2. Gestión de transmisiones
#### **GET** `/api.php?action=stream`
**Descripción:** Arranca o detiene transmisiones en directo.
**Parámetros:**
| Parámetro | Tipo | Requerido | Descripción |
|------------|----------|----------|-------------|
|stream_ids |array de enteros |sí |Lista de identificadores de streams. |
|function |string |sí |Acción a ejecutar (`start` o `stop`)|

---

### 3. Telemetría del sistema
#### **GET** `/api.php?action=stats`
**Descripción:** Obtiene estadísticas del sistema.
**Respuesta:**
```json
{
  "cpu": 8.32,
  "cpu_cores": 56,
  "cpu_avg": 8.86,
  "cpu_name": "Intel(R) Xeon(R) CPU E5-2680 v4 @ 2.40GHz",
  ...,
}
```

---

### 4. Comprobación del ciclo de vida de procesos
#### **GET** `/api.php?action=pidsAreRunning`
**Descripción:** Comprueba si los identificadores de proceso (PID) indicados están en ejecución.
| Parámetro | Tipo | Requerido | Descripción |
|------------|----------|----------|-------------|
|pids |array de enteros | sí | Lista de PIDs a comprobar. |
|program |string |sí |Nombre del programa. |

---

### 5. Obtención de un archivo
#### **GET** `/api.php?action=getFile`
**Descripción:** Descarga el archivo indicado.
**Parámetros:**
| Parámetro | Tipo | Requerido | Descripción |
|------------|----------|----------|-------------|
|filename |string |sí |Ruta del archivo.|
**Respuesta:**
- El contenido del archivo.

---

### 6. Listado del contenido de un directorio
#### **GET** `/api.php?action=viewDir`
**Descripción:** Obtiene el listado de directorios.
**Parámetros:**
| Parámetro | Tipo | Requerido | Descripción |
|------------|----------|----------|-------------|
|dir |string |sí |Ruta del directorio.|
**Respuesta:**
```html
<ul class="jqueryFileTree" style="display: none;">
  <li class="directory collapsed"><a href="#" rel="/path/to/directory/">directory_name</a></li>
  <li class="file ext_txt"><a href="#" rel="/path/to/file.txt">file.txt</a></li>
</ul>
```

---

### 7. Redirección de conexión
#### **GET** `/api.php?action=redirect_connection`
**Descripción:** Redirige una conexión a partir del identificador de actividad y del identificador de stream.
**Parámetros:**
| Parámetro | Tipo | Requerido | Descripción |
|------------|----------|----------|-------------|
|activity_id |entero |sí |Identificador de actividad. |
|stream_id |entero |sí |Identificador de stream. |

---

### 8. Enviar señal
#### **GET** `/api.php?action=signal_send`
**Descripción:** Envía un mensaje de señal para ejecutar una acción.
**Parámetros:**
| Parámetro | Tipo | Requerido | Descripción |
|------------|----------|----------|-------------|
|message |string |sí |Mensaje de la señal. |
|activity_id |entero |sí |Identificador de actividad.|


---

### 9. Limpiar la carpeta de archivos temporales
#### **GET** `/api.php?action=free_temp`
**Descripción:** Elimina los archivos temporales y lanza el script de caché.

---

### 10. Limpiar la carpeta de streams
#### **GET** `/api.php?action=free_streams`
**Descripción:** Limpia la carpeta de streams.

---

### 11. Obtener espacio libre
#### **GET** `/api.php?action=get_free_space`
**Descripción:** Devuelve información del espacio libre en disco.

---

### 12. Obtener PIDs
#### **GET** `/api.php?action=get_pids`
**Descripción:** Devuelve la lista de procesos en ejecución.

---
### 13. Terminar un proceso por PID
#### **GET** `/api.php?action=kill_pid`
**Descripción:** Mata un proceso por su PID.

---

## Códigos de error
| Código | Descripción |
|----------------------|-------------|
| INVALID_API_PASSWORD | La contraseña de la API es incorrecta |
| API_IP_NOT_ALLOWED | La dirección IP no está permitida |
| INVALID_REQUEST | Parámetros de la petición incorrectos |

---

## Notas
- Todas las peticiones deben autenticarse con la contraseña correcta de la API.
- Algunas acciones pueden requerir permisos o restricciones adicionales según la configuración del servidor.
