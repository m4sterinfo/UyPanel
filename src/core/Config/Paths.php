<?php

/**
 * Constantes de rutas
 *
 * Todos los define() para la estructura de archivos del proyecto.
 * Dependencia: MAIN_HOME debe estar definido antes de incluir este archivo.
 *
 * Grupos:
 *   1. Rutas base (content/, tmp/)
 *   2. Directorios de sistema (config/, bin/, includes/, www/, signals/)
 *   3. Directorios de contenido (streams/, epg/, vod/, archive/, ...)
 *   4. Directorios temporales (cache/, flood/, logs/, ...)
 *   5. Recursos web (images/, enigma2/)
 */

// ─────────────────────────────────────────────────────────────────
//  1. Rutas base (con protección contra redefinición)
// ─────────────────────────────────────────────────────────────────

if (!defined('CONTENT_PATH')) {
    define('CONTENT_PATH', MAIN_HOME . 'content/');
}

if (!defined('TMP_PATH')) {
    define('TMP_PATH', MAIN_HOME . 'tmp/');
}

// ─────────────────────────────────────────────────────────────────
//  2. Directorios de sistema
// ─────────────────────────────────────────────────────────────────

define('CONFIG_PATH',   MAIN_HOME . 'config/');
define('BIN_PATH',      MAIN_HOME . 'bin/');
define('INCLUDES_PATH', MAIN_HOME . 'includes/');
define('WWW_PATH',      MAIN_HOME . 'www/');
define('SIGNALS_PATH',  MAIN_HOME . 'signals/');

// ─────────────────────────────────────────────────────────────────
//  3. Directorios de contenido
// ─────────────────────────────────────────────────────────────────

define('STREAMS_PATH',  CONTENT_PATH . 'streams/');
define('EPG_PATH',      CONTENT_PATH . 'epg/');
define('VOD_PATH',      CONTENT_PATH . 'vod/');
define('ARCHIVE_PATH',  CONTENT_PATH . 'archive/');
define('CREATED_PATH',  CONTENT_PATH . 'created/');
define('DELAY_PATH',    CONTENT_PATH . 'delayed/');
define('VIDEO_PATH',    CONTENT_PATH . 'video/');
define('PLAYLIST_PATH', CONTENT_PATH . 'playlists/');

// ─────────────────────────────────────────────────────────────────
//  4. Directorios temporales
// ─────────────────────────────────────────────────────────────────

define('CONS_TMP_PATH',       TMP_PATH . 'opened_cons/');
define('CRONS_TMP_PATH',      TMP_PATH . 'crons/');
define('CIDR_TMP_PATH',       TMP_PATH . 'cidr/');
define('CACHE_TMP_PATH',      TMP_PATH . 'cache/');
define('STREAMS_TMP_PATH',    TMP_PATH . 'cache/streams/');
define('SERIES_TMP_PATH',     TMP_PATH . 'cache/series/');
define('LINES_TMP_PATH',      TMP_PATH . 'cache/lines/');
define('DIVERGENCE_TMP_PATH', TMP_PATH . 'divergence/');
define('FLOOD_TMP_PATH',      TMP_PATH . 'flood/');
define('PLAYER_TMP_PATH',     TMP_PATH . 'player/');
define('MINISTRA_TMP_PATH',   TMP_PATH . 'ministra/');
define('SIGNALS_TMP_PATH',    TMP_PATH . 'signals/');
define('LOGS_TMP_PATH',       TMP_PATH . 'logs/');
define('WATCH_TMP_PATH',      TMP_PATH . 'watch/');

// ─────────────────────────────────────────────────────────────────
//  5. Recursos web
// ─────────────────────────────────────────────────────────────────

define('IMAGES_PATH',    WWW_PATH . 'images/');
define('E2_IMAGES_PATH', IMAGES_PATH . 'enigma2/');
