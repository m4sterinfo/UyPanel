<?php

/**
 * TMDB Library Loader
 *
 * Archivo proxy para cargar la librería TMDB desde includes/libs/.
 *
 * La librería TMDb (includes/libs/tmdb.php + includes/libs/TMDb/)
 * permanece en su sitio hasta el paso completo al autoload.
 * Este archivo proporciona un punto de entrada único desde el módulo.
 *
 * @see includes/libs/tmdb.php
 * @see includes/libs/TMDb/
 * @see includes/libs/tmdb_release.php
 */

require_once INCLUDES_PATH . 'libs/tmdb.php';
