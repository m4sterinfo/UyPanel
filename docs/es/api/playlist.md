<h1 align="center">📺 Obtener playlist</h1>

<p align="center">
  Esta API provee las funciones de autenticación y generación de playlists para UyPanel. Los clientes pueden autenticarse con usuario/contraseña o con token, y se genera una playlist personalizada.
</p>

---

## 📚 Navegación

- [📂 Ubicación del archivo](#ubicación-del-archivo)
- [🔑 Autenticación](#autenticación)
- [🚀 Endpoints](#endpoints)
- [❌ Códigos de error](#códigos-de-error)
- [🧾 Notas](#notas)

---

## 📂 Ubicación del archivo

La Playlist API la atiende `PlaylistApiController`:

```
src/public/Controllers/Api/PlaylistApiController.php
```

---

## 🔑 Autenticación

La API requiere autenticación ya sea con usuario y contraseña o con token. Esto garantiza el acceso seguro al contenido.

### URL base

```http
http://<tu-dominio>:80/playlist
```

---

## 🚀 Endpoints

### 1. Autenticación y generación de playlist

**Método y endpoint:**

```http
GET /
```

**Parámetros de la petición:**

| Parámetro   | Tipo      | Requerido | Descripción |
|------------|----------|-----------|----------|
| username  | string   | Sí*     | Usuario para la autenticación (obligatorio si no se usa `token`). |
| password  | string   | Sí*     | Contraseña para la autenticación (obligatoria si no se usa `token`). |
| token     | string   | Sí*     | Token de autenticación (obligatorio si no se usan usuario y contraseña). |
| type      | string   | No       | Tipo de dispositivo (por defecto: `m3u_plus`). |
| key       | string   | No       | Tipo de contenido (live — en directo, movies — películas, radio_streams — radio, series — series). |
| output    | string   | No       | Formato de salida (hls o m3u). |
| nocache   | boolean  | No       | Si es true, desactiva la caché. |

> 💡 *Sí* — es obligatorio uno de los dos: o username/password, o token.

**Ejemplos de petición:**

```bash
curl -X GET "http://<tu-dominio>:80/playlist/username/password/type&output=hls&key=live"
```

```bash
curl -X GET "http://<tu-dominio>:80/playlist/token/type&output=hls&key=live"
```

**Respuesta:**

- El archivo de playlist en el formato indicado (M3U o HLS).

---

## ❌ Códigos de error

En caso de error, la API devuelve los códigos correspondientes para diagnosticar el problema.

| Código                  | Descripción |
|-------------------------|----------|
| NO_CREDENTIALS         | Faltan los datos de autenticación. |
| INVALID_CREDENTIALS    | Usuario, contraseña o token incorrectos. |
| BLOCKED_USER_AGENT     | El user agent está bloqueado. |
| EXPIRED                | La cuenta ha expirado. |
| DEVICE_NOT_ALLOWED     | El tipo de dispositivo no está permitido. |
| BANNED                 | El usuario está baneado. |
| DISABLED               | La cuenta está deshabilitada. |
| EMPTY_USER_AGENT       | Se requiere user agent pero no viene. |
| NOT_IN_ALLOWED_IPS     | La IP no está permitida. |
| NOT_IN_ALLOWED_COUNTRY | El país no está permitido. |
| NOT_IN_ALLOWED_UAS     | El user agent no está permitido. |
| ISP_BLOCKED            | El proveedor (ISP) está bloqueado. |
| ASN_BLOCKED            | Se aplicó una restricción ASN. |
| DOWNLOAD_LIMIT_REACHED | Demasiadas peticiones. |
| GENERATE_PLAYLIST_FAILED | No se pudo generar la playlist. |

> ⚠️ **Consejo:** Revisa los logs del servidor para más detalles si surgen errores.

---

## 🧾 Notas

- **Parámetros de la petición:** Asegúrate de indicar todos los parámetros correctamente para evitar errores de autenticación.
- **Restricciones:** La API aplica filtros por user agent, IP y país para reforzar la seguridad.
- **Protección anti-abuso:** Hay límites de peticiones para evitar sobrecargar el servidor.
- **Recomendaciones:** Para testear usa herramientas como cURL o Postman. En producción, integra la generación de playlists en tu aplicación.

> 💬 Si tienes dudas de integración, abre un issue en el [repositorio](https://github.com/m4sterinfo/UyPanel/issues).

---
