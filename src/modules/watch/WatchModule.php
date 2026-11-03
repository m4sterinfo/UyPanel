<?php

/**
 * Watch Module
 *
 * Módulo Watch Folder / Recording.
 * Registra servicios, rutas, acciones API y tareas cron.
 *
 * ──────────────────────────────────────────────────────────────────
 * Qué incluye:
 * ──────────────────────────────────────────────────────────────────
 *
 *   Servicios:
 *     - WatchService    — CRUD de Watch Folders, ajustes, enable/disable/kill
 *     - RecordingService — planificación de grabaciones (DVR)
 *
 *   Controlador:
 *     - WatchController — procesamiento de peticiones HTTP y API
 *
 *   Páginas:
 *     - watch          — lista de folders
 *     - watch/add      — añadir/editar
 *     - watch/settings — ajustes de watch (settings_watch)
 *     - watch/output   — logs (watch_output)
 *     - watch/record   — planificación de grabación (record)
 *
 *   Acciones API:
 *     - enable_watch   — activar todos los folders
 *     - disable_watch  — desactivar todos los folders
 *     - kill_watch     — matar procesos
 *     - folder         — eliminar/lanzar folder
 *
 * @see WatchService
 * @see RecordingService
 * @see WatchController
 */

class WatchModule implements ModuleInterface {

    /**
     * {@inheritdoc}
     */
    public function getName(): string {
        return 'watch';
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
                // WatchService y RecordingService son clases estáticas,
                // registramos sus nombres para el descubrimiento
        $container->set('watch.service', 'WatchService');
        $container->set('watch.recording', 'RecordingService');
        $container->set('watch.controller', function ($c) {
            return new WatchController();
        });
    }

    /**
          * Registro de las rutas del módulo
     *
          * Páginas:
     *   GET watch              → WatchController::index
     *   GET watch/add          → WatchController::add
     *   GET settings/watch     → WatchController::settings
     *   GET watch/output       → WatchController::output
          *   GET watch/record       → WatchController::record (vía record)
     *
     * API:
     *   enable_watch           → WatchController::apiEnable
     *   disable_watch          → WatchController::apiDisable
     *   kill_watch             → WatchController::apiKill
     *   folder                 → WatchController::apiFolder
     *
     * @param Router $router
     */
    public function registerRoutes(Router $router): void {
                // ── Páginas ──────────────────────────────────────────
        $router->group('watch', function (Router $r) {
            $r->get('', [WatchController::class, 'index'], [
                'permission' => ['adv', 'folder_watch'],
            ]);
            $r->get('add', [WatchController::class, 'add'], [
                'permission' => ['adv', 'folder_watch'],
            ]);
            $r->get('output', [WatchController::class, 'output'], [
                'permission' => ['adv', 'folder_watch'],
            ]);
        });

                // settings_watch → settings/watch (vía normalizePage)
        $router->get('settings/watch', [WatchController::class, 'settings'], [
            'permission' => ['adv', 'folder_watch_settings'],
        ]);

                // record → página independiente
        $router->get('record', [WatchController::class, 'record'], [
            'permission' => ['adv', 'folder_watch'],
        ]);

                // ── Acciones API ──────────────────────────────────────
        $router->api('enable_watch', [WatchController::class, 'apiEnable'], [
            'permission' => ['adv', 'folder_watch_settings'],
        ]);
        $router->api('disable_watch', [WatchController::class, 'apiDisable'], [
            'permission' => ['adv', 'folder_watch_settings'],
        ]);
        $router->api('kill_watch', [WatchController::class, 'apiKill'], [
            'permission' => ['adv', 'folder_watch'],
        ]);
        $router->api('folder', [WatchController::class, 'apiFolder'], [
            'permission' => ['adv', 'folder_watch'],
        ]);
    }

    /**
          * Comandos CLI del módulo
     *
     * @param CommandRegistry $registry
     */
    public function registerCommands(CommandRegistry $registry): void {
        $registry->register(new WatchCronJob());
        $registry->register(new WatchItemCommand());
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
