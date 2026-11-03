<h1 align="center">📡 XtreamCodes API</h1>

<p align="center">
  La API da acceso a streams IPTV (Live TV, Radio), VOD (películas), series y EPG (guía de programación) en aplicaciones compatibles con XtreamCodes. Es el componente clave para la integración con UyPanel.
</p>

---

## 📚 Navegación

- [📂 Ubicación de archivos](#ubicación-de-archivos)
- [📑 Contenido](#contenido)
- [🔑 Autenticación](#autenticación)
- [📺 Live TV (TV y Radio)](#live-tv-tv-y-radio)
- [🎬 VOD (Películas)](#vod-películas)
- [📽 Series](#series)
- [🎞 Obtención de contenido multimedia](#obtención-de-contenido-multimedia)
- [🧾 Notas](#notas)

---

## 📂 Ubicación de archivos

La API principal la atiende `PlayerApiController`:

```
src/public/Controllers/Api/PlayerApiController.php
```

---

## 📑 Contenido

La API está dividida en secciones clave para facilitar la integración. Cada endpoint está descrito con ejemplos de peticiones y respuestas.

---

## 🔑 Autenticación

La autenticación es el primer paso para acceder al contenido. Valida las credenciales del usuario y devuelve la información del servidor.

### Petición

```http
GET /player_api?username={username}&password={password}
```

### Ejemplo de respuesta

```json
{
  "user_info": {
    "username": "testxc",
    "password": "testxc",
    "message": "Welcome to UyPanel",
    "auth": 1,
    "status": "Active",
    "exp_date": null,
    "is_trial": 0,
    "created_at": 1757353729,
    "max_connections": 1,
    "allowed_output_formats": [
      "m3u8",
      "ts",
      "rtmp"
    ]
  },
  "server_info": {
    "xui": true,
    "version": "1.1.0",
    "url": "176.124.192.118",
    "port": "80",
    "https_port": "443",
    "server_protocol": "http",
    "rtmp_port": "8880",
    "timestamp_now": 1757442189,
    "time_now": "2025-09-09 19:23:09",
    "timezone": "Europe/London"
  }
}
```

> 💡 **Consejo:** Usa los datos obtenidos en las peticiones siguientes para evitar re-autenticarte.

---

## 📺 Live TV (TV y Radio)

Sección para trabajar con transmisiones en directo, incluyendo categorías, streams y EPG.

### Obtener todas las categorías Live

```http
GET /player_api?username={username}&password={password}&action=get_live_categories
```

**Ejemplo de respuesta**

```json
[
  {
    "category_id": "1",
    "category_name": "News",
    "parent_id": 0
  },
  {
    "category_id": "2",
    "category_name": "Sports",
    "parent_id": 0
  }
]
```

### Obtener todos los streams Live

```http
GET /player_api?username={username}&password={password}&action=get_live_streams
```

**Ejemplo de respuesta**

```json
[
  {
    "num": 1,
    "name": "BBC News",
    "stream_type": "live",
    "stream_id": 101,
    "stream_icon": "http://176.124.192.118/images/bbc.png",
    "epg_channel_id": "bbc.news.uk",
    "added": "1660568200",
    "category_id": "1",
    "custom_sid": "",
    "tv_archive": 0,
    "direct_source": "",
    "tv_archive_duration": 0
  }
]
```

### Obtener los streams de una categoría

```http
GET /player_api?username={username}&password={password}&action=get_live_streams&category_id={id}
```

### Obtener el EPG de un canal (programa breve)

```http
GET /player_api?username={username}&password={password}&action=get_short_epg&stream_id={id}&limit={N}
```

**Ejemplo de respuesta**

```json
{
  "epg_listings": [
    {
      "id": 1,
      "title": "Morning News",
      "start": "2022-08-15 07:00:00",
      "end": "2022-08-15 08:00:00",
      "description": "Daily morning news update."
    }
  ]
}
```

### Obtener la programación completa de un canal

```http
GET /player_api?username={username}&password={password}&action=get_simple_data_table&stream_id={id}
```

### EPG para todos los canales (XMLTV)

```http
GET /xmltv.php?username={username}&password={password}
```

**Ejemplo de respuesta (XMLTV)**

```xml
<tv>
  <channel id="bbc.news.uk">
    <display-name>BBC News</display-name>
  </channel>
  <programme start="20220815070000 +0000" stop="20220815080000 +0000" channel="bbc.news.uk">
    <title>Morning News</title>
    <desc>Daily morning news update.</desc>
  </programme>
</tv>
```

---

## 🎬 VOD (Películas)

Sección para video bajo demanda, incluyendo categorías e información de películas.

### Categorías de películas

```http
GET /player_api?username={username}&password={password}&action=get_vod_categories
```

**Ejemplo de respuesta**

```json
[
  {
    "category_id": "10",
    "category_name": "Action",
    "parent_id": 0
  },
  {
    "category_id": "11",
    "category_name": "Drama",
    "parent_id": 0
  }
]
```

> ⚠️ **Nota:** La lista completa de categorías puede ser extensa; en los ejemplos solo se muestran fragmentos.

### Obtener todos los streams VOD

```http
GET /player_api?username={username}&password={password}&action=get_vod_streams
```

**Ejemplo de respuesta**

```json
[
  {
    "num": 1,
    "name": "The Dark Knight (2008)",
    "title": "The Dark Knight",
    "year": 2008,
    "stream_type": "movie",
    "stream_id": 1,
    "stream_icon": "http://176.124.192.118:80/images/7pQr8EhEi05VXRmZc5QfCLaoCzC2XshRjzbEF-0-ISX3OBAKZPN21ASjMFFF_OljuWhs_Jbsg3Nu1tBcV0ErgPL_v9ei3c1fI0mNI7C5eos.jpg",
    "rating": 8.5,
    "rating_5based": 4.3,
    "added": 1757343129,
    "plot": "Batman raises the stakes in his war on crime. With the help of Lt. Jim Gordon and District Attorney Harvey Dent, Batman sets out to dismantle the remaining criminal organizations that plague the streets. The partnership proves to be effective, but they soon find themselves prey to a reign of chaos unleashed by a rising criminal mastermind known to the terrified citizens of Gotham as the Joker.",
    "cast": "Christian Bale, Heath Ledger, Aaron Eckhart, Michael Caine, Maggie Gyllenhaal",
    "director": "Christopher Nolan, Christopher Nolan, Steve Gehrke",
    "genre": "Drama, Action, Crime",
    "release_date": "2008-07-16",
    "youtube_trailer": "kmJLuwP3MbY",
    "episode_run_time": "152",
    "category_id": "1",
    "category_ids": [1, 2],
    "container_extension": "mp4",
    "custom_sid": "",
    "direct_source": ""
  }
]
```

---

### Películas por categoría

```http
GET /player_api?username={username}&password={password}&action=get_vod_streams&category_id={id}
```

### Información de una película

```http
GET /player_api?username={username}&password={password}&action=get_vod_info&vod_id={id}
```

**Ejemplo de respuesta**

```json
{
  "info": {
    "kinopoisk_url": "https://www.themoviedb.org/movie/155",
    "tmdb_id": 155,
    "name": "The Dark Knight",
    "o_name": "The Dark Knight",
    "cover_big": "http://176.124.192.118:80/images/7pQr8EhEi05VXRmZc5QfCLaoCzC2XshRjzbEF-0-ISX3OBAKZPN21ASjMFFF_OljuWhs_Jbsg3Nu1tBcV0ErgPL_v9ei3c1fI0mNI7C5eos.jpg",
    "movie_image": "http://176.124.192.118:80/images/7pQr8EhEi05VXRmZc5QfCLaoCzC2XshRjzbEF-0-ISX3OBAKZPN21ASjMFFF_OljuWhs_Jbsg3Nu1tBcV0ErgPL_v9ei3c1fI0mNI7C5eos.jpg",
    "release_date": "2008-07-16",
    "episode_run_time": 152,
    "youtube_trailer": "kmJLuwP3MbY",
    "director": "Christopher Nolan, Christopher Nolan, Steve Gehrke",
    "actors": "Christian Bale, Heath Ledger, Aaron Eckhart, Michael Caine, Maggie Gyllenhaal",
    "cast": "Christian Bale, Heath Ledger, Aaron Eckhart, Michael Caine, Maggie Gyllenhaal",
    "description": "Batman raises the stakes in his war on crime. With the help of Lt. Jim Gordon and District Attorney Harvey Dent, Batman sets out to dismantle the remaining criminal organizations that plague the streets. The partnership proves to be effective, but they soon find themselves prey to a reign of chaos unleashed by a rising criminal mastermind known to the terrified citizens of Gotham as the Joker.",
    "plot": "Batman raises the stakes in his war on crime. With the help of Lt. Jim Gordon and District Attorney Harvey Dent, Batman sets out to dismantle the remaining criminal organizations that plague the streets. The partnership proves to be effective, but they soon find themselves prey to a reign of chaos unleashed by a rising criminal mastermind known to the terrified citizens of Gotham as the Joker.",
    "age": "",
    "mpaa_rating": "",
    "rating_count_kinopoisk": 0,
    "country": "United Kingdom",
    "genre": "Drama, Action, Crime",
    "backdrop_path": [
      "http://176.124.192.118:80/images/7pQr8EhEi05VXRmZc5QfCADBzwKB171qpiTTqrZdeATVLqPvNOO1tw6QZZproFAJRjrtA4EzIxoMJZlI2R3OlQ.jpg"
    ],
    "duration_secs": 9120,
    "duration": "02:32:00",
    "bitrate": 0,
    "rating": 8.52,
    "releasedate": "2008-07-16",
    "subtitles": []
  },
  "movie_data": {
    "stream_id": 1,
    "name": "The Dark Knight (2008)",
    "title": "The Dark Knight",
    "year": 2008,
    "added": 1757343129,
    "category_id": "1",
    "category_ids": [1, 2],
    "container_extension": "mp4",
    "custom_sid": "",
    "direct_source": ""
  }
}
```

---

## 📽 Series

Sección para trabajar con series, incluyendo categorías, temporadas y episodios.

### Categorías de series

```http
GET /player_api?username={username}&password={password}&action=get_series_categories
```

**Ejemplo de respuesta**

```json
[
  {
    "category_id": "20",
    "category_name": "Drama",
    "parent_id": 0
  }
]
```

---

### Todas las series

```http
GET /player_api?username={username}&password={password}&action=get_series
```

**Ejemplo de respuesta**

```json
[
  {
    "num": 1,
    "name": "Braceface (2001)",
    "title": "Braceface",
    "year": 2001,
    "stream_type": "series",
    "series_id": 1,
    "cover": "http://176.124.192.118:80/images/7pQr8EhEi05VXRmZc5QfCLaoCzC2XshRjzbEF-0-ISX3OBAKZPN21ASjMFFF_OljpT6mCZeHa4zLXMQZ2eaTpGZUSOucptPKyuP5tgpEZm0.jpg",
    "plot": "The show, set in Elkford, British Columbia, is based around Sharon Spitz, who is a junior high school student with braces that get in her way of leading a normal teenage life. In the first season, she is enrolled at Mary Pickford Junior High.",
    "cast": "Stacey DePass",
    "director": "Charles E. Bastien",
    "genre": "Drama, Animation, Comedy",
    "release_date": "2001-06-02",
    "releaseDate": "2001-06-02",
    "last_modified": "1757348651",
    "rating": "7",
    "rating_5based": 3.5,
    "backdrop_path": [
      "http://176.124.192.118:80/images/7pQr8EhEi05VXRmZc5QfCADBzwKB171qpiTTqrZdeATBaSzxZEgi9EPIvh5kU_50ecbH2L-yDt1PIfqijKTIMg.jpg"
    ],
    "youtube_trailer": null,
    "episode_run_time": 25,
    "category_id": "4",
    "category_ids": [4]
  }
]
```

---

### Series por categoría

```http
GET /player_api?username={username}&password={password}&action=get_series&category_id={id}
```

### Información de una serie

```http
GET /player_api?username={username}&password={password}&action=get_series_info&series_id={id}
```

**Ejemplo de respuesta**

```json
{
  "seasons": [
    {
      "air_date": "2001-06-02",
      "episode_count": 26,
      "id": 4937,
      "name": "Season 1",
      "overview": "",
      "season_number": 1,
      "vote_average": 4.5,
      "cover": "http://176.124.192.118:80/images/7pQr8EhEi05VXRmZc5QfCLaoCzC2XshRjzbEF-0-ISX3OBAKZPN21ASjMFFF_OljJBrZ7sNEJHE6hlqofFOJSRKiMRvSj08-T6iTQVWvj2I.jpg",
      "cover_big": "http://176.124.192.118:80/images/7pQr8EhEi05VXRmZc5QfCLaoCzC2XshRjzbEF-0-ISX3OBAKZPN21ASjMFFF_OljJBrZ7sNEJHE6hlqofFOJSRKiMRvSj08-T6iTQVWvj2I.jpg"
    }
  ],
  "info": {
    "name": "Braceface (2001)",
    "title": "Braceface",
    "year": 2001,
    "cover": "http://176.124.192.118:80/images/7pQr8EhEi05VXRmZc5QfCLaoCzC2XshRjzbEF-0-ISX3OBAKZPN21ASjMFFF_OljpT6mCZeHa4zLXMQZ2eaTpGZUSOucptPKyuP5tgpEZm0.jpg",
    "plot": "The show, set in Elkford, British Columbia, is based around Sharon Spitz, who is a junior high school student with braces that get in her way of leading a normal teenage life. In the first season, she is enrolled at Mary Pickford Junior High.",
    "cast": "Stacey DePass",
    "director": "Charles E. Bastien",
    "genre": "Drama, Animation, Comedy",
    "release_date": "2001-06-02",
    "releaseDate": "2001-06-02",
    "last_modified": "1757348651",
    "rating": "7",
    "rating_5based": 3.5,
    "backdrop_path": [
      "http://176.124.192.118:80/images/7pQr8EhEi05VXRmZc5QfCADBzwKB171qpiTTqrZdeATBaSzxZEgi9EPIvh5kU_50ecbH2L-yDt1PIfqijKTIMg.jpg"
    ],
    "youtube_trailer": null,
    "episode_run_time": 25,
    "category_id": "4",
    "category_ids": [4]
  },
  "episodes": {
    "1": [
      {
        "id": "2",
        "episode_num": "1",
        "title": "Braceface - S01E01 - Brace Yourself",
        "container_extension": "mp4",
        "info": {
          "release_date": "2001-06-02",
          "plot": "Sharon Spitz, a 13-year-old 8th grader get's braces the day before picture day. When getting braces at the ortadontist, there is a thunder storm and the power go's out. Something happens with Sharon's braces and now its making her do all sorts of weird things!",
          "duration_secs": 649,
          "duration": "00:10:49",
          "movie_image": "http://176.124.192.118:80/images/7pQr8EhEi05VXRmZc5QfCADBzwKB171qpiTTqrZdeARCK8Ch0a6wlNxuIHhbJ3c1Dg_AqkwSsH-qdpnCvcMsiy6U85vvi1bs1xDOddBeOEQ.jpg",
          "bitrate": 2791,
          "rating": "5",
          "season": "1",
          "tmdb_id": "105800",
          "cover_big": "http://176.124.192.118:80/images/7pQr8EhEi05VXRmZc5QfCADBzwKB171qpiTTqrZdeARCK8Ch0a6wlNxuIHhbJ3c1Dg_AqkwSsH-qdpnCvcMsiy6U85vvi1bs1xDOddBeOEQ.jpg"
        },
        "subtitles": [],
        "custom_sid": "",
        "added": 1757348651,
        "season": 1,
        "direct_source": ""
      }
    ]
  }
}
```

---

## 🎞 Obtención de contenido multimedia

Obtén los enlaces directos a los archivos multimedia tras la autenticación.

* **Live TV (canal):**

```http
http://176.124.192.118/live/{username}/{password}/{stream_id}.ts
```

* **Película (VOD):**

```http
http://176.124.192.118/movie/{username}/{password}/{vod_id}.mp4
```

* **Episodio:**

```http
http://176.124.192.118/series/{username}/{password}/{episode_id}.mp4
```

> ⚠️ **Importante:** En la primera petición se produce un redirect a `/auth/...` para autenticar, después se entrega el contenido.

---

## 🧾 Notas

- **Formatos de salida:** Se soportan m3u8, ts, rtmp — elige según el dispositivo.
- **Seguridad:** Todas las peticiones requieren autenticación; monitorea los logs por si hay errores.
- **Integración:** Para testear usa herramientas como Postman. En la aplicación implementa caché de respuestas para optimizar.


> 💬 Si tienes dudas de integración, abre un issue en el [repositorio](https://github.com/m4sterinfo/UyPanel/issues).

---
