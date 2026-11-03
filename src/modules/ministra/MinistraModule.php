<?php

/**
 * Ministra (Stalker Portal) Module
 *
 * Módulo del portal MAG/Ministra para dispositivos STB.
 * Procesa las peticiones HTTP de los receptores MAG vía portal.php.
 *
 * ──────────────────────────────────────────────────────────────────
 * Qué incluye:
 * ──────────────────────────────────────────────────────────────────
 *
 *   Procesadores:
 *     - PortalHandler    — dispatcher switch/case para peticiones type+action
 *                          (pre-init, stb, itv, vod, series, epg, radio,
 *                           tv_archive, watchdog, audioclub, account_info,
 *                           handshake)
 *
 *   Helpers:
 *     - PortalHelpers    — funciones auxiliares del portal
 *                          (getDevice, updateCache, getEPG, getItems,
 *                           getMovies, getSeries, getStreams, getStations,
 *                           getSeriesItems, sort*, getHeaders, shutdown)
 *
 *   Punto de entrada:
 *     - ministra/portal.php — envoltorio fino: init/auth → PortalHandler
 *
 * @see PortalHandler
 * @see PortalHelpers
 */

class MinistraModule implements ModuleInterface {

    /**
     * {@inheritdoc}
     */
    public function getName(): string {
        return 'ministra';
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
          * Ministra es un portal autónomo (punto de entrada independiente portal.php),
          * no usa el contenedor DI común directamente.
     *
     * @param ServiceContainer $container
     */
    public function boot(ServiceContainer $container): void {
                // Ministra — endpoint standalone, no se registran servicios
    }

    /**
          * Registro de las rutas del módulo
     *
          * Ministra se atiende vía ministra/portal.php (HTTP directo),
          * no a través del Router del panel admin.
     *
     * @param Router $router
     */
    public function registerRoutes(Router $router): void {
                // Enrutado de Ministra — vía portal.php directamente
    }

    /**
          * Comandos CLI del módulo
     *
          * Ministra no tiene comandos CLI.
     *
     * @param CommandRegistry $registry
     */
    public function registerCommands(CommandRegistry $registry): void {
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
