<?php

/**
 * TMDB Module
 *
 * Módulo de integración con TheMovieDB.
 * Registra servicios, acciones API y tareas cron.
 *
 * ──────────────────────────────────────────────────────────────────
 * Qué incluye:
 * ──────────────────────────────────────────────────────────────────
 *
 *   Servicios:
 *     - TmdbService       — búsqueda, obtención de detalles de películas/series
 *     - TmdbCron          — cron de procesamiento de la cola watch_refresh
 *     - TmdbPopularCron   — cron de recolección de películas/series populares
 *
 *   Acciones API:
 *     - tmdb_search       — búsqueda en TMDB (por texto o ID)
 *     - tmdb              — obtención de detalles de película/serie
 *
 *   Librería:
 *     - includes/libs/tmdb.php      — TMDB API v3 PHP wrapper
 *     - includes/libs/TMDb/         — modelos (Movie, TVShow, Season, ...)
 *     - includes/libs/tmdb_release.php — parser de nombres release
 *
 * @see TmdbService
 * @see TmdbCron
 * @see TmdbPopularCron
 */

class TmdbModule implements ModuleInterface {

    /**
     * {@inheritdoc}
     */
    public function getName(): string {
        return 'tmdb';
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion(): string {
        return '1.0.0';
    }

    /**
          * Registro de los servicios del módulo en el contenedor DI
     *
     * @param ServiceContainer $container
     */
    public function boot(ServiceContainer $container): void {
        $container->set('tmdb.service', 'TmdbService');
    }

    /**
          * Registro de las rutas del módulo
     *
          * TMDB no tiene páginas propias — solo acciones API.
          * Las acciones tmdb_search y tmdb siguen por ahora en admin/api.php
          * (delegan en TmdbService) y se trasladarán al refactorizar api.php.
     *
     * @param Router $router
     */
    public function registerRoutes(Router $router): void {
                // Las acciones API tmdb_search y tmdb siguen en api.php
                // hasta el refactor del god-object (Paso 5.2 de ARCHITECTURE.md)
    }

    /**
          * Comandos CLI del módulo
     *
     * @param CommandRegistry $registry
     */
    public function registerCommands(CommandRegistry $registry): void {
        $registry->register(new TmdbCronJob());
        $registry->register(new TmdbPopularCronJob());
    }

    /**
          * Suscripciones a eventos del core
     *
     * @return array
     */
    public function getEventSubscribers(): array {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function install(): void {
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(): void {
    }
}
