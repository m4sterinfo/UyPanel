<?php

/**
 * Cargador de módulos
 *
 * Detecta automáticamente los módulos desde modules/star/module.json.
 * config/modules.php solo guarda overrides (enabled => false).
 *
 * La fuente de verdad es la clase PHP del módulo (ModuleInterface), no el JSON.
 * module.json contiene solo metadatos: name, description, version, requires_core.
 *
 * ──────────────────────────────────────────────────────────────────
 * Uso (web):
 * ──────────────────────────────────────────────────────────────────
 *
 *   $loader = new ModuleLoader();
 *   $loader->loadAll();
 *   $loader->bootAll($container, $router);
 *
 * ──────────────────────────────────────────────────────────────────
 * Uso (CLI):
 * ──────────────────────────────────────────────────────────────────
 *
 *   $loader = new ModuleLoader();
 *   $loader->loadAll();
 *   $loader->registerAllCommands($registry);
 *
 * @see ModuleInterface
 */

class ModuleLoader {

    /** @var ModuleInterface[] Módulos cargados: name => instance */
    protected $modules = [];

    /** @var array Overrides de config/modules.php */
    protected $overrides = [];

    /**
          * Detectar y cargar todos los módulos desde modules/star
     *
          * Escanea modules/star/module.json y comprueba los overrides en config/modules.php.
          * Crea las instancias ModuleInterface, pero NO invoca boot/registerRoutes.
     *
          * @param string|null $modulesDir Ruta al directorio modules/
     * @return $this
     */
    public function loadAll($modulesDir = null) {
        if ($modulesDir === null) {
            $modulesDir = defined('MAIN_HOME') ? MAIN_HOME . 'modules' : dirname(__DIR__, 2) . '/modules';
        }

                // Overrides — solo desactivación de módulos
        $overridesPath = defined('CONFIG_PATH') ? CONFIG_PATH . 'modules.php' : dirname(__DIR__, 2) . '/config/modules.php';
        if (file_exists($overridesPath)) {
            $this->overrides = require $overridesPath;
            if (!is_array($this->overrides)) {
                $this->overrides = [];
            }
        }

                // Auto-discover de módulos
        $jsonFiles = glob($modulesDir . '/*/module.json');
        if (!$jsonFiles) {
            return $this;
        }

        foreach ($jsonFiles as $jsonFile) {
            $name = basename(dirname($jsonFile));

                        // Comprobamos el override: disabled
            if (isset($this->overrides[$name]['enabled']) && !$this->overrides[$name]['enabled']) {
                continue;
            }

            $this->load($name, dirname($jsonFile));
        }

        return $this;
    }

    /**
          * Cargar un módulo
     *
          * @param string $name Nombre del módulo (nombre del directorio)
          * @param string|null $modulePath Ruta absoluta al directorio del módulo
     * @return bool
     */
    public function load($name, $modulePath = null) {
        if (isset($this->modules[$name])) {
            return true;
        }

        if ($modulePath === null) {
            $modulePath = $this->getModulePath($name);
        }

                // Determinamos el nombre de la clase por convención: kebab-case → PascalCase + Module
        $className = $this->resolveClassName($name);

                // Intentamos cargar la clase
        if (!class_exists($className)) {
            $classFile = $modulePath . '/' . $className . '.php';
            if (file_exists($classFile)) {
                require_once $classFile;
            }

            if (!class_exists($className)) {
                error_log("ModuleLoader: class '{$className}' not found for module '{$name}'");
                return false;
            }
        }

        $module = new $className();
        if (!($module instanceof ModuleInterface)) {
            error_log("ModuleLoader: class '{$className}' does not implement ModuleInterface");
            return false;
        }

        $this->modules[$name] = $module;
        return true;
    }

    /**
          * Ejecutar boot() y registerRoutes() para todos los módulos cargados
     *
          * Se usa en el contexto web (bootstrap.php).
     *
          * @param ServiceContainer $container Contenedor DI
          * @param Router|null $router Enrutador HTTP (null para CLI)
     */
    public function bootAll(ServiceContainer $container, Router $router = null) {
        foreach ($this->modules as $name => $module) {
            $module->boot($container);

            if ($router !== null) {
                $module->registerRoutes($router);
            }

            $subscribers = $module->getEventSubscribers();
            if (!empty($subscribers) && $container->has('events')) {
                $dispatcher = $container->get('events');
                foreach ($subscribers as $event => $handler) {
                    $dispatcher->listen($event, $handler);
                }
            }
        }
    }

    /**
          * Registrar los comandos CLI de todos los módulos cargados
     *
          * Se usa en console.php. Cada módulo define por sí mismo
          * sus comandos en registerCommands() — sin filesystem scanning.
     *
          * @param CommandRegistry $registry Registro de comandos CLI
     */
    public function registerAllCommands(CommandRegistry $registry) {
        foreach ($this->modules as $module) {
            $module->registerCommands($registry);
        }
    }

    /**
          * Comprobar si un módulo está cargado
     */
    public function isLoaded($name) {
        return isset($this->modules[$name]);
    }

    /**
          * Obtener la instancia de un módulo cargado
     *
     * @return ModuleInterface|null
     */
    public function getModule($name) {
        return $this->modules[$name] ?? null;
    }

    /**
          * Obtener todos los módulos cargados
     *
     * @return ModuleInterface[]
     */
    public function getModules() {
        return $this->modules;
    }

    /**
          * Obtener la ruta al directorio del módulo
     */
    public function getModulePath($name) {
        $base = defined('MAIN_HOME') ? MAIN_HOME : dirname(__DIR__, 2) . '/';
        return $base . 'modules/' . $name;
    }

    /**
          * Convertir el nombre del módulo en nombre de clase
     *
     * kebab-case → PascalCase + 'Module'
          * Ejemplos: 'plex' → 'PlexModule', 'theft-detection' → 'TheftDetectionModule'
     *
     * @param string $name
     * @return string
     */
    protected function resolveClassName($name) {
                // Override de config: 'class' => 'CustomModule'
        if (isset($this->overrides[$name]['class'])) {
            return $this->overrides[$name]['class'];
        }

                // Convención: kebab-case → PascalCase + Module
        $parts = explode('-', $name);
        return implode('', array_map('ucfirst', $parts)) . 'Module';
    }
}
