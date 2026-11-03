<?php

/**
 * MAGSCAN Module
 *
 * Módulo de ajustes MAGSCAN.
 * Gestiona las listas blancas/negras para el escaneo de dispositivos MAG
 * (direcciones MAC e IP).
 *
 * ──────────────────────────────────────────────────────────────────
 * Qué incluye:
 * ──────────────────────────────────────────────────────────────────
 *
 *   Páginas:
 *     - magscan_settings — gestión de la lista blanca/negra de MAC
 *                          + lista blanca de IP (tres pestañas)
 *
 *   Procesador de formulario:
 *     - submit_magscan   — procesamiento POST del guardado de ajustes
 *
 * @see admin/magscan_settings.php
 */

class MagscanModule implements ModuleInterface {

    /**
     * {@inheritdoc}
     */
    public function getName(): string {
        return 'magscan';
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
          * MAGSCAN usa la infraestructura común del panel admin —
          * no se registran servicios propios.
     *
     * @param ServiceContainer $container
     */
    public function boot(ServiceContainer $container): void {
                // No hay servicios propios — se usa la infraestructura admin común
    }

    /**
          * Registro de las rutas del módulo
     *
          * El enrutado se realiza a través de la navegación del panel admin
          * (magscan_settings.php se carga directamente).
     *
     * @param Router $router
     */
    public function registerRoutes(Router $router): void {
                // Enrutado — vía navegación de páginas admin (magscan_settings.php)
    }

    /**
          * Comandos CLI del módulo
     *
          * MAGSCAN no tiene comandos CLI.
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
