<?php

/**
 * TmdbPopularCron
 *
 * Cron de recolección de películas/series populares de TMDB.
 *
 * Responsabilidad:
 *   - Recolección de películas y series populares desde la API de TMDB
 *   - Correspondencia con la BD local (streams, streams_series)
 *   - Recolección de «similares» (similar) de películas/series
 *   - Recolección de streams live populares por actividad
 *   - Escritura de resultados en archivos (tmdb_popular, live_popular)
 */
class TmdbPopularCron {
    /**
          * Recopilar películas y series populares de TMDB.
     *
          * Correlaciona los TMDB ID con los registros locales.
     *
          * @param TMDB  $tmdb    Instancia del cliente
          * @param array $tmdbIDs Mapeo tmdb_id => local_id
          * @param int   $pages   Número de páginas a consultar
     * @return array ['movies' => int[], 'series' => int[]]
     */
    private static function collectPopular(TMDB $tmdb, array $tmdbIDs, int $pages = 100): array {
        $rReturn = ['movies' => [], 'series' => []];

        // Popular movies
        foreach (range(1, $pages) as $rPage) {
            foreach ($tmdb->getPopularMovies($rPage) as $rItem) {
                $id = $rItem->getID();
                if (isset($tmdbIDs[$id])) {
                    $rReturn['movies'][] = $tmdbIDs[$id];
                }
            }
        }

        // Popular TV shows
        foreach (range(1, $pages) as $rPage) {
            foreach ($tmdb->getPopularTVShows($rPage) as $rItem) {
                $id = $rItem->getID();
                if (isset($tmdbIDs[$id])) {
                    $rReturn['series'][] = $tmdbIDs[$id];
                }
            }
        }

        return $rReturn;
    }

    /**
          * Rellenar el campo `similar` de las películas.
     *
          * @param TMDB $tmdb Instancia del cliente
     */
    private static function processSimilarMovies(TMDB $tmdb): void {
        global $db;

        $db->query(
            'SELECT COUNT(*) AS `count` FROM `streams`
             WHERE `type` = 2 AND `similar` IS NULL AND `tmdb_id` > 0;'
        );
        $rCount = (int)($db->get_row()['count'] ?? 0);
        if ($rCount <= 0) {
            return;
        }

        $rSteps = $rCount >= 1000 ? range(0, $rCount, 1000) : [0];
        foreach ($rSteps as $rStep) {
            $db->query(
                'SELECT `id`, `tmdb_id` FROM `streams`
                 WHERE `type` = 2 AND `similar` IS NULL AND `tmdb_id` > 0
                 LIMIT ' . $rStep . ', 1000;'
            );
            foreach ($db->get_rows() as $rRow) {
                $rSimilar = [];
                foreach (range(1, 3) as $rPage) {
                    $items = $tmdb->getSimilarMovies($rRow['tmdb_id'], $rPage);
                    foreach (json_decode(json_encode($items), true) as $rItem) {
                        $rSimilar[] = (int)($rItem['_data']['id'] ?? 0);
                    }
                }
                $db->query(
                    'UPDATE `streams` SET `similar` = ? WHERE `id` = ?;',
                    json_encode(array_values(array_unique($rSimilar))),
                    $rRow['id']
                );
            }
        }
    }

    /**
          * Rellenar el campo `similar` de las series.
     *
          * @param TMDB $tmdb Instancia del cliente
     */
    private static function processSimilarSeries(TMDB $tmdb): void {
        global $db;

        $db->query(
            'SELECT COUNT(*) AS `count` FROM `streams_series`
             WHERE `similar` IS NULL AND `tmdb_id` > 0;'
        );
        $rCount = (int)($db->get_row()['count'] ?? 0);
        if ($rCount <= 0) {
            return;
        }

        $rSteps = $rCount >= 1000 ? range(0, $rCount, 1000) : [0];
        foreach ($rSteps as $rStep) {
            $db->query(
                'SELECT `id`, `tmdb_id` FROM `streams_series`
                 WHERE `similar` IS NULL AND `tmdb_id` > 0
                 LIMIT ' . $rStep . ', 1000;'
            );
            foreach ($db->get_rows() as $rRow) {
                $rSimilar = [];
                foreach (range(1, 3) as $rPage) {
                    $items = $tmdb->getSimilarSeries($rRow['tmdb_id'], $rPage);
                    foreach (json_decode(json_encode($items), true) as $rItem) {
                        $rSimilar[] = (int)($rItem['id'] ?? 0);
                    }
                }
                $db->query(
                    'UPDATE `streams_series` SET `similar` = ? WHERE `id` = ?;',
                    json_encode(array_values(array_unique($rSimilar))),
                    $rRow['id']
                );
            }
        }
    }

    /**
          * Recopilar los streams live populares por actividad de 28 días.
     */
    private static function collectPopularLive(): void {
        global $db;

        $rPopularLive = [];
        $db->query(
            'SELECT `stream_id`, COUNT(`activity_id`) AS `count`
             FROM `lines_activity`
             LEFT JOIN `streams` ON `streams`.`id` = `lines_activity`.`stream_id`
             WHERE `type` = 1 AND `date_end` < UNIX_TIMESTAMP() - (86400*28)
             GROUP BY `stream_id`
             ORDER BY `count` DESC
             LIMIT 500;'
        );
        foreach ($db->get_rows() as $rRow) {
            $rPopularLive[] = $rRow['stream_id'];
        }

        file_put_contents(CONTENT_PATH . 'live_popular', igbinary_serialize($rPopularLive));
    }

    /**
          * Punto de entrada principal del cron.
     *
          * Ejecuta:
          *   1. Recolección de películas/series populares de TMDB
          *   2. Relleno de similar para películas
          *   3. Relleno de similar para series
          *   4. Recolección de streams live populares
     */
    public static function run(): void {
        global $db;

        require_once MAIN_HOME . 'includes/libs/tmdb.php';

        if (strlen(SettingsManager::getAll()['tmdb_api_key'] ?? '') > 0) {
            $lang = SettingsManager::getAll()['tmdb_language'] ?? '';
            $rTMDB = $lang !== ''
                ? new TMDB(SettingsManager::getAll()['tmdb_api_key'], $lang)
                : new TMDB(SettingsManager::getAll()['tmdb_api_key']);

            $rPages = 100;
            $rTMDBIDs = [];

            // --- Movies
            $db->query(
                'SELECT `id`, `movie_properties` FROM `streams`
                 WHERE `type` = 2 AND `movie_properties` IS NOT NULL AND LENGTH(`movie_properties`) > 0;'
            );
            foreach ($db->get_rows() as $rRow) {
                $rProperties = json_decode($rRow['movie_properties'], true);
                if (!empty($rProperties['tmdb_id'])) {
                    $rTMDBIDs[$rProperties['tmdb_id']] = $rRow['id'];
                }
            }

            // --- Series
            $db->query(
                'SELECT `id`, `tmdb_id` FROM `streams_series`
                 WHERE `tmdb_id` IS NOT NULL AND LENGTH(`tmdb_id`) > 0;'
            );
            foreach ($db->get_rows() as $rRow) {
                $rTMDBIDs[$rRow['tmdb_id']] = $rRow['id'];
            }

            // Collect & save popular
            $rReturn = self::collectPopular($rTMDB, $rTMDBIDs, $rPages);
            file_put_contents(CONTENT_PATH . 'tmdb_popular', igbinary_serialize($rReturn));

            // Similar movies & series
            self::processSimilarMovies($rTMDB);
            self::processSimilarSeries($rTMDB);
        }

        // Popular live streams (always, regardless of TMDB key)
        self::collectPopularLive();
    }
}
