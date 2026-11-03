<?php

/**
 * Plex Module
 *
 * Módulo Plex Sync Integration.
 * Registra servicios, rutas, acciones API y tareas cron.
 *
 * ──────────────────────────────────────────────────────────────────
 * Qué incluye:
 * ──────────────────────────────────────────────────────────────────
 *
 *   Servicios:
 *     - PlexService     — CRUD Plex Sync, ajustes, force
 *     - PlexRepository  — obtención de servidores y secciones Plex
 *     - PlexAuth        — autenticación Plex (getToken, checkToken)
 *     - PlexCron        — cron de sincronización
 *     - PlexItem        — procesamiento CLI de elementos
 *
 *   Controlador:
 *     - PlexController  — procesamiento de peticiones HTTP y API
 *
 *   Páginas:
 *     - plex            — lista de servidores Plex
 *     - plex/add        — añadir/editar biblioteca
 *     - plex/settings   — ajustes de Plex (settings_plex)
 *
 *   Acciones API:
 *     - enable_plex     — activar todos los servidores
 *     - disable_plex    — desactivar todos los servidores
 *     - kill_plex       — matar procesos
 *     - library         — eliminar/lanzar biblioteca
 *     - plex_sections   — obtener las secciones del servidor Plex
 *
 * @see PlexService
 * @see PlexRepository
 * @see PlexAuth
 * @see PlexController
 */

class PlexModule implements ModuleInterface {

    /**
     * {@inheritdoc}
     */
    public function getName(): string {
        return 'plex';
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
        $container->set('plex.service', 'PlexService');
        $container->set('plex.repository', 'PlexRepository');
        $container->set('plex.auth', 'PlexAuth');
        $container->set('plex.controller', function ($c) {
            return new PlexController();
        });
    }

    /**
          * Registro de las rutas del módulo
     *
          * Páginas:
     *   GET plex              → PlexController::index
     *   GET plex/add          → PlexController::add
     *   GET settings/plex     → PlexController::settings
     *
     * API:
     *   enable_plex           → PlexController::apiEnable
     *   disable_plex          → PlexController::apiDisable
     *   kill_plex             → PlexController::apiKill
     *   library               → PlexController::apiLibrary
     *   plex_sections         → PlexController::apiSections
     *
     * @param Router $router
     */
    public function registerRoutes(Router $router): void {
                // ── Páginas ──────────────────────────────────────────
        $router->group('plex', function (Router $r) {
            $r->get('', [PlexController::class, 'index'], [
                'permission' => ['adv', 'folder_watch'],
            ]);
            $r->get('add', [PlexController::class, 'add'], [
                'permission' => ['adv', 'folder_watch'],
            ]);
        });

                // settings_plex → settings/plex (vía normalizePage)
        $router->get('settings/plex', [PlexController::class, 'settings'], [
            'permission' => ['adv', 'folder_watch_settings'],
        ]);

                // ── Acciones API ──────────────────────────────────────
        $router->api('enable_plex', [PlexController::class, 'apiEnable'], [
            'permission' => ['adv', 'folder_watch_settings'],
        ]);
        $router->api('disable_plex', [PlexController::class, 'apiDisable'], [
            'permission' => ['adv', 'folder_watch_settings'],
        ]);
        $router->api('kill_plex', [PlexController::class, 'apiKill'], [
            'permission' => ['adv', 'folder_watch'],
        ]);
        $router->api('library', [PlexController::class, 'apiLibrary'], [
            'permission' => ['adv', 'folder_watch'],
        ]);
        $router->api('plex_sections', [PlexController::class, 'apiSections'], [
            'permission' => ['adv', 'folder_watch_settings'],
        ]);
    }

    /**
          * Comandos CLI del módulo
     *
     * @param CommandRegistry $registry
     */
    public function registerCommands(CommandRegistry $registry): void {
        $registry->register(new PlexCronJob());
        $registry->register(new PlexItemCommand());
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
