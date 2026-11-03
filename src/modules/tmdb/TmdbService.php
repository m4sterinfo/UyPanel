<?php

/**
 * TmdbService
 *
 * Servicio para trabajar con la API de TMDB.
 *
 * Responsabilidad:
 *   - Creación de instancias TMDB con la localización correcta
 *   - Búsqueda de películas/series por título o TMDB ID
 *   - Obtención de información detallada (getMovie, getTVShow, getSeason)
 *   - Tráilers de series
 *
 * Dependencias:
 *   - includes/libs/tmdb.php (clase TMDB)
 *   - includes/libs/tmdb_release.php (parserelease)
 *   - includes/admin.php (getSeriesTrailer)
 */
class TmdbService {

    /**
          * Crear una instancia del cliente de la API TMDB
     *
     * @param string      $apiKey   TMDB API key
          * @param string|null $language Idioma de la consulta (prioridad: explícito → ajuste → default)
     * @return TMDB
     */
    public static function createClient(string $apiKey, ?string $language = null): TMDB {
        if ($language !== null && strlen($language) > 0) {
            return new TMDB($apiKey, $language);
        }

        $settingsLang = SettingsManager::getAll()['tmdb_language'] ?? '';
        if (strlen($settingsLang) > 0) {
            return new TMDB($apiKey, $settingsLang);
        }

        return new TMDB($apiKey);
    }

    /**
          * Búsqueda en TMDB (películas, series, episodios)
     *
          * Soporta la búsqueda:
          *   - Por ID numérico (getMovie/getTVShow/getSeason directo)
          *   - Por consulta de texto (searchMovie/searchTVShow)
     *
          * @param string      $term     Consulta de búsqueda o TMDB ID
          * @param string      $type     Tipo: movie|series|episode
          * @param string|null $language Idioma de la consulta
          * @param int|null    $season   Número de temporada (para episode)
     * @return array ['result' => bool, 'data' => array|null]
     */
    public static function search(string $term, string $type, ?string $language = null, ?int $season = null): array {
        $apiKey = SettingsManager::getAll()['tmdb_api_key'] ?? '';
        if (strlen($apiKey) === 0) {
            return ['result' => false];
        }

        self::requireLibrary();
        $rTMDB = self::createClient($apiKey, $language);

                // Búsqueda directa por TMDB ID numérico
        if (is_numeric($term) && in_array($type, ['movie', 'series', 'episode'])) {
            $rResult = self::fetchByID($rTMDB, $term, $type, $season);
            if (is_array($rResult)) {
                return ['result' => true, 'data' => $rResult];
            }
        }

                // Búsqueda por texto
        $rRelease = parserelease($term);
        $searchTerm = $rRelease['title'];
        $rJSON = [];

        if ($type === 'movie') {
            foreach ($rTMDB->searchMovie($searchTerm) as $rResult) {
                $rJSON[] = json_decode($rResult->getJSON(), true);
            }
        } elseif ($type === 'series') {
            foreach ($rTMDB->searchTVShow($searchTerm) as $rResult) {
                $rJSON[] = json_decode($rResult->getJSON(), true);
            }
        }

        if (count($rJSON) > 0) {
            return ['result' => true, 'data' => $rJSON];
        }

        return ['result' => false];
    }

    /**
          * Obtener información detallada de una película/serie por TMDB ID
     *
     * @param int         $id       TMDB ID
          * @param string      $type     Tipo: movie|series
          * @param string|null $language Idioma de la consulta
     * @return array ['result' => bool, 'data' => array|null]
     */
    public static function getDetails(int $id, string $type, ?string $language = null): array {
        $apiKey = SettingsManager::getAll()['tmdb_api_key'] ?? '';
        if (strlen($apiKey) === 0) {
            return ['result' => false];
        }

        self::requireLibrary();
        $rTMDB = self::createClient($apiKey, $language);
        $rResult = null;

        if ($type === 'movie') {
            $rMovie = $rTMDB->getMovie($id);
            $rResult = json_decode($rMovie->getJSON(), true);
            $rResult['trailer'] = $rMovie->getTrailer();
        } elseif ($type === 'series') {
            $rSeries = $rTMDB->getTVShow($id);
            $rResult = json_decode($rSeries->getJSON(), true);
            $settingsLang = SettingsManager::getAll()['tmdb_language'] ?? '';
            $rResult['trailer'] = getSeriesTrailer($id, ($language ?: $settingsLang));
        }

        if (!$rResult) {
            return ['result' => false];
        }

        return ['result' => true, 'data' => $rResult];
    }

    /**
          * Obtener datos por ID numérico
     *
          * @param TMDB     $tmdb   Instancia de TMDB
     * @param string   $id     TMDB ID
          * @param string   $type   Tipo: movie|series|episode
          * @param int|null $season Número de temporada
     * @return array|null
     */
    private static function fetchByID(TMDB $tmdb, string $id, string $type, ?int $season): ?array {
        if ($type === 'movie') {
            return [json_decode($tmdb->getMovie($id)->getJSON(), true)];
        }

        if ($type === 'series') {
            return [json_decode($tmdb->getTVShow($id)->getJSON(), true)];
        }

        if ($type === 'episode' && $season !== null) {
            $rResult = json_decode($tmdb->getSeason($id, $season)->getJSON(), true);
            if (isset($rResult['tvshow_id']) && $rResult['tvshow_id'] == 0) {
                return null;
            }
            return $rResult;
        }

        return null;
    }

    /**
          * Incluir la librería TMDB (una sola vez)
     */
    private static function requireLibrary(): void {
        static $loaded = false;
        if (!$loaded) {
            require_once INCLUDES_PATH . 'libs/tmdb.php';
            $loaded = true;
        }
    }
}
