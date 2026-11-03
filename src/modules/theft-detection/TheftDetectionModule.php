<?php

/**
 * Theft Detection Module
 *
 * Módulo de detección de robo de contenido VOD.
 * Monitoriza los patrones de visualización de VOD y detecta la posible
 * distribución/robo de contenido mediante el análisis del número de visualizaciones.
 *
 * ──────────────────────────────────────────────────────────────────
 * Qué incluye:
 * ──────────────────────────────────────────────────────────────────
 *
 *   Páginas:
 *     - theft_detection — tabla filtrada de visualizaciones VOD
 *                         por usuarios (filtro por rango)
 *
 *   Fuente de datos:
 *     - cache_engine.php generateTheftDetection() — tarea cron del core,
 *       genera un archivo de caché (igbinary) en CACHE_TMP_PATH/theft_detection
 *
 * @see admin/theft_detection.php
 */

class TheftDetectionModule implements ModuleInterface {

    /**
     * {@inheritdoc}
     */
    public function getName(): string {
        return 'theft-detection';
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
          * Theft Detection usa la infraestructura común del panel admin —
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
          * (theft_detection.php se carga directamente).
     *
     * @param Router $router
     */
    public function registerRoutes(Router $router): void {
                // Enrutado — vía navegación de páginas admin (theft_detection.php)
    }

    /**
          * Comandos CLI del módulo
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
