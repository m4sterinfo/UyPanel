<h1 align="center">🔌 External REST API (Admin & Reseller)</h1>

<p align="center">
 Two HTTP APIs to integrate UyPanel with external systems (billing, bots, WHMCS, scripts).
 Both return <code>JSON</code> and authenticate with a per-user <strong>API Key</strong>.
</p>

---

## Background concepts

| Concept | What it is |
|---|---|
| **Access Code type "Admin API"** (type 3) | Defines the **endpoint URL** of the Admin API: `http://<host>/<code>/` |
| **Access Code type "Reseller API"** (type 4) | Defines the **endpoint URL** of the Reseller API |
| **API Key** | Per-user 32-char hex credential stored in `users.api_key` |

Access Codes are managed in the panel (**Access Codes**); the API Key is generated in each user's **Edit Profile** (generate button → Save Profile). An endpoint without a valid API Key answers:

```json
{"status":"STATUS_FAILURE","error":"Invalid API key."}
```

### Endpoint URLs

- **Admin API**: `http://<host>/<admin-api-code>/?api_key=<KEY>&action=<action>`
- **Reseller API**: `http://<host>/<reseller-api-code>/?api_key=<KEY>&action=<action>`
- Equivalent variant: `.../<code>/api.php?action=...`
- GET or POST. For lists: `start` (offset), `limit` (default 50), `show_columns`/`hide_columns` (comma-separated).

### Response format

```json
{"status":"STATUS_SUCCESS","data":{...}}
```

On `create_line`/`edit_line`, `data` holds the resulting line. On errors, `data` echoes the received input for debugging.

### Common errors

| Status | Meaning |
|---|---|
| `STATUS_SUCCESS` | Success |
| `STATUS_FAILURE` | Generic error / no permission |
| `STATUS_INVALID_INPUT` | Missing required parameters |
| `STATUS_INVALID_PACKAGE` | Package missing or not available to the group |
| `STATUS_INSUFFICIENT_CREDITS` | Not enough credits |
| `STATUS_NO_TRIALS` | Group trial limit reached |
| `STATUS_EXISTS_USERNAME` | Username already taken |
| `STATUS_INVALID_USERNAME` / `STATUS_INVALID_PASSWORD` | Group minimum length |
| `STATUS_INVALID_MAC` / `STATUS_EXISTS_MAC` | Invalid/duplicate MAC (MAG/E2) |

> **Note:** MAG and Enigma2 devices are **disabled** in UyPanel (the Stalker portal is a classic MAC brute-force vector). Their API actions are not available.

---

## Reseller API (actions)

Auth: `api_key` of a user with `is_reseller = 1` and `status = 1`.

### Account & packages

| Action | Parameters | Description |
|---|---|---|
| `user_info` | — | Credits, api_key and group permissions |
| `packages` | — | Group packages with credit cost |

### Lines

| Action | Parameters | Description |
|---|---|---|
| `get_lines` | `start`, `limit` | Own lines (and sub-resellers') |
| `get_line` | `id` | Full line data |
| `create_line` | `package` (required), `trial`, `username`, `password`, `member_id`, `contact`, `reseller_notes`, `bouquets_selected`, `allowed_ips`, `allowed_ua` | Creates the line applying the package (bouquet, outputs, connections, expiry) and deducts credits |
| `edit_line` | `id` + fields | **With `package` = renew** (extends expiry, deducts credits). Without `package` = free edit |
| `delete_line` | `id` | Deletes the line |
| `disable_line` / `enable_line` | `id` | Disable / enable |

### Sub-resellers (super resellers only)

| Action | Parameters | Description |
|---|---|---|
| `get_users` / `get_user` | `start, limit` / `id` | List / data of sub-resellers |
| `create_user` | `username`, `password`, `email`, `member_group_id`, `owner_id`, `reseller_dns`, `notes` | Creates sub-reseller (cost: `create_sub_resellers_price`) |
| `edit_user` / `delete_user` | `id` + fields / `id` | Edit / delete |
| `disable_user` / `enable_user` | `id` | Block / unblock panel login |
| `adjust_credits` | `id`, `credits`, `note` | Transfer credits (negative = take back) |

### Logs

`activity_logs`, `live_connections`, `user_logs` — with `start`/`limit`.

---

## Admin API (actions)

Auth: `api_key` of a user with `is_admin = 1`. Includes everything above (no ownership restriction) plus:

| Category | Actions |
|---|---|
| Lines | `get_lines`, `get_line`, `create_line`, `edit_line`, `delete_line`, `disable_line`, `enable_line`, `ban_line`, `unban_line` |
| Resellers | `get_users`, `get_user`, `create_user`, `edit_user`, `delete_user`, `disable_user`, `enable_user`, `adjust_credits` |
| Content | `get_streams`, `get_channels`, `get_stations`, `get_movies`, `get_series_list`, `get_episodes` + CRUD (`create_/edit_/delete_`) + `start_/stop_stream`, `start_/stop_movie` |
| Settings | `get_bouquets` + CRUD, `get_packages` + CRUD, `get_groups` + CRUD, `get_categories` + CRUD, `get_access_codes` + CRUD, `get_settings`/`edit_settings`, `get_epgs` + CRUD + `reload_epg`, `get_providers` + CRUD + `reload_provider`, `get_transcode_profiles` + CRUD, `get_watch_folders` + CRUD + `reload_watch_folder`, blocked (`isp/ua/ip`) + `flush_blocked_ips`, `get_rtmp_ips` + CRUD, `get_hmacs` + CRUD |
| Servers | `get_servers`, `get_server`, `install_server`, `install_proxy`, `edit_server`, `edit_proxy`, `delete_server`, `get_server_stats`, `get_free_space`, `get_fpm_status`, `get_rtmp_stats`, `get_pids`, `get_certificate_info`, `reload_nginx`, `reload_cache`, `clear_temp`, `clear_streams`, `get_directory`, `kill_pid`, `kill_connection` |
| Logs | `activity_logs`, `live_connections`, `credit_logs`, `client_logs`, `user_logs`, `login_logs`, `system_logs`, `stream_errors`, `restream_logs`, `watch_output` |
| Other | `mysql_query` (⚠️ arbitrary SQL — read-only recommended), `user_info` |

> **Key difference:** the **Reseller API** `create_line` applies the chosen package. The **Admin API** `create_line` builds from **raw fields** (no package logic): `bouquets_selected` is required (JSON `"[1,2]"` or comma list `"1,2"`) and `access_output` defaults to all outputs when omitted.

---

## Recipes (curl)

```bash
# Verify account & credits (reseller)
curl "http://HOST/CODE/?api_key=KEY&action=user_info"

# Create DEMO (package trial)
curl "http://HOST/CODE/?api_key=KEY&action=create_line&package=3&trial=1"

# Create paid line
curl "http://HOST/CODE/?api_key=KEY&action=create_line&package=2&username=client1&password=pass1234"

# Renew line (extends expiry, deducts credits)
curl "http://HOST/CODE/?api_key=KEY&action=edit_line&id=8&package=2"

# Disable / enable / delete
curl "http://HOST/CODE/?api_key=KEY&action=disable_line&id=8"
curl "http://HOST/CODE/?api_key=KEY&action=enable_line&id=8"
curl "http://HOST/CODE/?api_key=KEY&action=delete_line&id=8"

# Line for a sub-reseller (super reseller)
curl "http://HOST/CODE/?api_key=KEY&action=create_line&package=2&member_id=6"

# Adjust credits
curl "http://HOST/CODE/?api_key=KEY&action=adjust_credits&id=6&credits=10&note=topup"

# Admin: raw line with bouquets and outputs
curl "http://HOST/CODE/?api_key=KEY&action=create_line&member_id=4&bouquets_selected=1&access_output=1,2&max_connections=2&exp_date=2027-01-01"
```

---

## Playback endpoints (for clients)

No `api_key` — they use the line's username/password:

| What | URL |
|---|---|
| m3u_plus list | `http://HOST/get.php?username=U&password=P&type=m3u_plus&output=ts` |
| Xtream API | `http://HOST/player_api.php?username=U&password=P` |
| TS / HLS channel | `http://HOST/live/U/P/ID.ts` · `http://HOST/live/U/P/ID.m3u8` |
| VOD | `http://HOST/movie/U/P/ID.mp4` · `http://HOST/series/U/P/ID.mp4` |

---

📘 The same reference, with interactive examples and copy buttons, lives **inside the panel** under the **API Docs** menu (admin and reseller).
