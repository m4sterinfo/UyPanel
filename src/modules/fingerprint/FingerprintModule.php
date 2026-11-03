<?php

/**
 * Fingerprint Module
 *
 * Módulo «Huella del stream» (Fingerprint Stream).
 * Permite al administrador superponer texto (ID de actividad, nombre de usuario
 * o un mensaje arbitrario) sobre los streams en vivo para identificar al espectador.
 *
 * ──────────────────────────────────────────────────────────────────
 * Qué incluye:
 * ──────────────────────────────────────────────────────────────────
 *
 *   Páginas:
 *     - fingerprint     — selección de stream (búsqueda + categoría + DataTable)
 *                         + activación de la huella (tipo/tamaño/color/posición)
 *                         + tabla de actividad
 *
 *   Acciones API:
 *     - fingerprint     — envío de la señal a los servidores activos
 *     - line_activity   — cierre forzado de la conexión
 *
 * @see admin/fingerprint.php
 */

class FingerprintModule implements ModuleInterface {

    /**
     * {@inheritdoc}
     */
    public function getName(): string {
        return 'fingerprint';
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
          * Fingerprint usa la infraestructura común del panel admin
          * (session, functions.php, api.php) — no se necesitan servicios propios.
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
          * (fingerprint.php se carga directamente).
     *
     * @param Router $router
     */
    public function registerRoutes(Router $router): void {
                // Enrutado — vía navegación de páginas admin (fingerprint.php)
    }

    /**
          * Comandos CLI del módulo
     *
          * Fingerprint no tiene comandos CLI.
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
