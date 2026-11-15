<h1 align="center">🔌 API REST externa (Admin y Reseller)</h1>

<p align="center">
 Dos APIs HTTP para integrar UyPanel con sistemas externos (facturación, bots, WHMCS, scripts).
 Ambas responden <code>JSON</code> y se autentican con una <strong>API Key</strong> por usuario.
</p>

---

## Conceptos previos

| Concepto | Qué es |
|---|---|
| **Access Code tipo "Admin API"** (type 3) | Define la **URL del endpoint** del Admin API: `http://<host>/<code>/` |
| **Access Code tipo "Reseller API"** (type 4) | Define la **URL del endpoint** del Reseller API |
| **API Key** | Credencial individual de 32 caracteres hexadecimales, guardada en `users.api_key` de cada usuario |

Los Access Codes se gestionan en el panel (**Access Codes**); la API Key se genera en **Edit Profile** de cada usuario (botón generar → Save Profile). Un endpoint sin API Key válida responde:

```json
{"status":"STATUS_FAILURE","error":"Invalid API key."}
```

### URL de los endpoints

- **Admin API**: `http://<host>/<code-admin-api>/?api_key=<KEY>&action=<accion>`
- **Reseller API**: `http://<host>/<code-reseller-api>/?api_key=<KEY>&action=<accion>`
- Variante equivalente: `.../<code>/api.php?action=...`
- GET o POST, indistinto. En las listas: `start` (offset), `limit` (50 por defecto), `show_columns`/`hide_columns` (columnas separadas por coma).

### Formato de respuesta

```json
{"status":"STATUS_SUCCESS","data":{...}}
```

En `create_line`/`edit_line`, `data` contiene la línea completa resultante. En errores, `data` devuelve lo recibido para depurar.

### Errores comunes

| Status | Significado |
|---|---|
| `STATUS_SUCCESS` | Operación correcta |
| `STATUS_FAILURE` | Error genérico / sin permisos |
| `STATUS_INVALID_INPUT` | Faltan parámetros obligatorios |
| `STATUS_INVALID_PACKAGE` | Paquete inexistente o no disponible para el grupo |
| `STATUS_INSUFFICIENT_CREDITS` | Créditos insuficientes |
| `STATUS_NO_TRIALS` | Límite de trials del grupo alcanzado |
| `STATUS_EXISTS_USERNAME` | Username duplicado |
| `STATUS_INVALID_USERNAME` / `STATUS_INVALID_PASSWORD` | Longitud mínima del grupo |
| `STATUS_INVALID_MAC` / `STATUS_EXISTS_MAC` | MAC inválida/duplicada (MAG/E2) |

> **Nota:** los dispositivos MAG y Enigma2 están **desactivados** en UyPanel (el portal Stalker es un vector clásico de fuerza bruta por MAC). Sus acciones de API no están disponibles.

---

## Reseller API (acciones)

Autenticación: `api_key` de un usuario con `is_reseller = 1` y `status = 1`.

### Cuenta y paquetes

| Acción | Parámetros | Descripción |
|---|---|---|
| `user_info` | — | Créditos, api_key y permisos del reseller |
| `packages` | — | Paquetes del grupo con su coste en créditos |

### Líneas

| Acción | Parámetros | Descripción |
|---|---|---|
| `get_lines` | `start`, `limit` | Líneas propias (y de sub-resellers) |
| `get_line` | `id` | Datos completos de una línea |
| `create_line` | `package` (obligatorio), `trial`, `username`, `password`, `member_id`, `contact`, `reseller_notes`, `bouquets_selected`, `allowed_ips`, `allowed_ua` | Crea la línea aplicando el paquete (bouquet, outputs, conexiones, vencimiento) y descuenta créditos |
| `edit_line` | `id` + campos | **Con `package` = renovar** (extiende vencimiento, descuenta créditos). Sin `package` = edición sin coste |
| `delete_line` | `id` | Elimina la línea |
| `disable_line` / `enable_line` | `id` | Deshabilita / habilita |

### Sub-resellers (solo super resellers)

| Acción | Parámetros | Descripción |
|---|---|---|
| `get_users` / `get_user` | `start, limit` / `id` | Lista / datos de sub-resellers |
| `create_user` | `username`, `password`, `email`, `member_group_id`, `owner_id`, `reseller_dns`, `notes` | Crea sub-reseller (coste: `create_sub_resellers_price`) |
| `edit_user` / `delete_user` | `id` + campos / `id` | Edita / elimina |
| `disable_user` / `enable_user` | `id` | Bloquea / desbloquea el login |
| `adjust_credits` | `id`, `credits`, `note` | Transfiere créditos (negativo = recuperar) |

### Logs

`activity_logs`, `live_connections`, `user_logs` — con `start`/`limit`.

---

## Admin API (acciones)

Autenticación: `api_key` de un usuario con `is_admin = 1`. Incluye todo lo anterior (sin restricción de ownership) más:

| Categoría | Acciones |
|---|---|
| Líneas | `get_lines`, `get_line`, `create_line`, `edit_line`, `delete_line`, `disable_line`, `enable_line`, `ban_line`, `unban_line` |
| Resellers | `get_users`, `get_user`, `create_user`, `edit_user`, `delete_user`, `disable_user`, `enable_user`, `adjust_credits` |
| Contenido | `get_streams`, `get_channels`, `get_stations`, `get_movies`, `get_series_list`, `get_episodes` + CRUD (`create_/edit_/delete_`) + `start_/stop_stream`, `start_/stop_movie` |
| Configuración | `get_bouquets` + CRUD, `get_packages` + CRUD, `get_groups` + CRUD, `get_categories` + CRUD, `get_access_codes` + CRUD, `get_settings`/`edit_settings`, `get_epgs` + CRUD + `reload_epg`, `get_providers` + CRUD + `reload_provider`, `get_transcode_profiles` + CRUD, `get_watch_folders` + CRUD + `reload_watch_folder`, blocked (`isp/ua/ip`) + `flush_blocked_ips`, `get_rtmp_ips` + CRUD, `get_hmacs` + CRUD |
| Servidores | `get_servers`, `get_server`, `install_server`, `install_proxy`, `edit_server`, `edit_proxy`, `delete_server`, `get_server_stats`, `get_free_space`, `get_fpm_status`, `get_rtmp_stats`, `get_pids`, `get_certificate_info`, `reload_nginx`, `reload_cache`, `clear_temp`, `clear_streams`, `get_directory`, `kill_pid`, `kill_connection` |
| Logs | `activity_logs`, `live_connections`, `credit_logs`, `client_logs`, `user_logs`, `login_logs`, `system_logs`, `stream_errors`, `restream_logs`, `watch_output` |
| Otros | `mysql_query` (⚠️ SQL arbitrario — solo lectura recomendado), `user_info` |

> **Diferencia clave:** el `create_line` del **Reseller API** aplica el paquete elegido. El `create_line` del **Admin API** crea con **campos crudos** (no aplica paquetes): `bouquets_selected` es obligatorio (JSON `"[1,2]"` o comas `"1,2"`) y `access_output` por defecto usa todos los outputs si se omite.

---

## Recetas (curl)

```bash
# Verificar cuenta y créditos (reseller)
curl "http://HOST/CODE/?api_key=KEY&action=user_info"

# Crear DEMO (trial del paquete)
curl "http://HOST/CODE/?api_key=KEY&action=create_line&package=3&trial=1"

# Crear línea de pago
curl "http://HOST/CODE/?api_key=KEY&action=create_line&package=2&username=cliente1&password=pass1234"

# Renovar línea (extiende vencimiento, descuenta créditos)
curl "http://HOST/CODE/?api_key=KEY&action=edit_line&id=8&package=2"

# Deshabilitar / habilitar / eliminar
curl "http://HOST/CODE/?api_key=KEY&action=disable_line&id=8"
curl "http://HOST/CODE/?api_key=KEY&action=enable_line&id=8"
curl "http://HOST/CODE/?api_key=KEY&action=delete_line&id=8"

# Línea para un sub-reseller (super reseller)
curl "http://HOST/CODE/?api_key=KEY&action=create_line&package=2&member_id=6"

# Ajustar créditos
curl "http://HOST/CODE/?api_key=KEY&action=adjust_credits&id=6&credits=10&note=recarga"

# Admin: línea cruda con bouquets y outputs
curl "http://HOST/CODE/?api_key=KEY&action=create_line&member_id=4&bouquets_selected=1&access_output=1,2&max_connections=2&exp_date=2027-01-01"
```

---

## Endpoints de reproducción (para clientes)

No usan `api_key` — usan el usuario/password de la línea:

| Qué | URL |
|---|---|
| Lista m3u_plus | `http://HOST/get.php?username=U&password=P&type=m3u_plus&output=ts` |
| Xtream API | `http://HOST/player_api.php?username=U&password=P` |
| Canal TS / HLS | `http://HOST/live/U/P/ID.ts` · `http://HOST/live/U/P/ID.m3u8` |
| VOD | `http://HOST/movie/U/P/ID.mp4` · `http://HOST/series/U/P/ID.mp4` |

---

📘 La misma referencia, con ejemplos interactivos y botones de copia, está **dentro del panel** en el menú **API Docs** (admin y reseller).
