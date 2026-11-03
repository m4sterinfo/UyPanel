<?php

/**
 * Contrato de módulo
 *
 * Cada módulo en modules/ DEBE implementar esta interfaz.
 * Un módulo es un directorio aislado con un contrato conocido.
 * Se puede eliminar y el sistema seguirá funcionando.
 *
 * ──────────────────────────────────────────────────────────────────
 * La fuente de verdad es la clase PHP:
 * ──────────────────────────────────────────────────────────────────
 *
 *   module.json — solo metadatos (name, description, version, requires_core).
 *   Toda la configuración runtime va AQUÍ, en los métodos PHP:
 *     - boot()               — registro de servicios
 *     - registerRoutes()     — rutas HTTP
 *     - registerCommands()   — comandos CLI y crons
 *     - getEventSubscribers() — suscripciones a eventos
 *
 * ──────────────────────────────────────────────────────────────────
 * Ciclo de vida:
 * ──────────────────────────────────────────────────────────────────
 *
 *   1. ModuleLoader escanea modules/star/module.json
 *   2. config/modules.php se comprueba en busca de overrides (enabled => false)
 *   3. boot(ServiceContainer) — registro de servicios
 *   4. registerRoutes(Router) — registro de rutas
 *   5. registerCommands(CommandRegistry) — comandos CLI y crons
 *   6. getEventSubscribers() — suscripciones a eventos
 *
 *   Instalación/desinstalación:
 *   7. install() — creación de tablas, datos iniciales
 *   8. uninstall() — eliminación de tablas, limpieza de datos
 *
 * @see Router::group()
 * @see ServiceContainer::set()
 * @see CommandRegistry::register()
 */

interface ModuleInterface {

    /**
          * Nombre único del módulo (coincide con el nombre del directorio)
     */
    public function getName(): string;

    /**
          * Versión del módulo (semver)
     */
    public function getVersion(): string;

    /**
          * Inicialización del módulo: registro de servicios en el contenedor DI
     *
          * Se invoca una vez al cargar el módulo.
     *
          * @param ServiceContainer $container Contenedor DI
     */
    public function boot(ServiceContainer $container): void;

    /**
          * Registro de las rutas HTTP del módulo
     *
          * Se invoca tras boot(). El módulo registra:
          * - rutas GET/POST para páginas
          * - rutas API para acciones AJAX
     *
          * @param Router $router Enrutador HTTP
     */
    public function registerRoutes(Router $router): void;

    /**
          * Registro de comandos CLI y tareas cron del módulo
     *
          * El módulo crea y registra explícitamente las instancias CommandInterface.
          * Nada de filesystem scanning — todo el registro en PHP.
     *
          * Ejemplo:
     *   $registry->register(new MyCronCommand());
     *   $registry->register(new MyToolCommand());
     *
          * @param CommandRegistry $registry Registro de comandos CLI
     */
    public function registerCommands(CommandRegistry $registry): void;

    /**
          * Obtener el array de suscripciones a eventos del core
     *
          * @return array Suscripciones: EventClass => handler
     */
    public function getEventSubscribers(): array;

    /**
          * Instalación del módulo (creación de tablas, datos iniciales)
     *
          * Se invoca una vez al activar el módulo.
     */
    public function install(): void;

    /**
          * Desinstalación del módulo (limpieza de tablas, registros en settings, cron)
     *
          * Se invoca al desactivar/eliminar el módulo.
     */
    public function uninstall(): void;
}
