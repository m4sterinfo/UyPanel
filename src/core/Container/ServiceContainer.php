<?php

/**
 * Contenedor DI mínimo (Service Container)
 *
 * Sustituye el patrón `global $db` / `SettingsManager::getAll()` / `API::$db`
 * por un registro único de servicios con inicialización perezosa.
 *
 * ──────────────────────────────────────────────────────────────────
 * Concepto:
 * ──────────────────────────────────────────────────────────────────
 *
 *   El contenedor almacena los servicios en dos formas:
 *
 *   1. Fábrica (callable) — función que crea el servicio.
 *      Se invoca UNA sola vez en el primer get(). El resultado se cachea.
 *
 *   2. Valor listo — objeto ya creado o valor escalar.
 *      Disponible de inmediato sin invocar la fábrica.
 *
 *   Todos los servicios son singletons por defecto (una instancia por petición).
 *   Para fábricas que devuelven una instancia nueva cada vez, use factory().
 *
 * ──────────────────────────────────────────────────────────────────
 * Uso:
 * ──────────────────────────────────────────────────────────────────
 *
 *   $c = ServiceContainer::getInstance();
 *
 *   // Registro de una fábrica (perezosa, se crea en el primer get)
 *   $c->set('db', function($c) {
 *       $cfg = $c->get('config');
 *       return DatabaseHandler::create($cfg);
 *   });
 *
 *   // Registro de un valor listo
 *   $c->set('config', $_INFO);
 *
 *   // Obtención de un servicio
 *   $db = $c->get('db');
 *
 *   // Fábrica (instancia nueva en cada invocación)
 *   $c->factory('request', function($c) {
 *       return new Request($_GET, $_POST);
 *   });
 *
 *   // Comprobación de existencia
 *   if ($c->has('redis')) { ... }
 *
 * ──────────────────────────────────────────────────────────────────
 * Compatibilidad hacia atrás:
 * ──────────────────────────────────────────────────────────────────
 *
 *   El código antiguo usa `global $db`. El contenedor no rompe esto —
 *   bootstrap.php registra $db tanto en el contenedor como en el scope global.
 *   El código nuevo usa $container->get('db').
 *   A medida que avance la migración, global $db quedará en desuso.
 *
 * ──────────────────────────────────────────────────────────────────
 * Para módulos:
 * ──────────────────────────────────────────────────────────────────
 *
 *   class PlexModule implements ModuleInterface {
 *       public function boot(ServiceContainer $container): void {
 *           $db    = $container->get('db');
 *           $cache = $container->get('cache');
 *           $container->set('plex.service', function($c) {
 *               return new PlexService($c->get('db'), $c->get('settings'));
 *           });
 *       }
 *   }
 */

class ServiceContainer {

    /**
          * La única instancia del contenedor (singleton)
     * @var ServiceContainer|null
     */
    private static $instance = null;

    /**
          * Fábricas registradas: id => callable
     * @var array<string, callable>
     */
    private $factories = [];

    /**
          * Servicios resueltos (listos): id => mixed
     * @var array<string, mixed>
     */
    private $resolved = [];

    /**
          * Servicios marcados como «de fábrica» (instancia nueva cada vez)
     * @var array<string, bool>
     */
    private $isFactory = [];

    /**
          * Flag: servicio actualmente en proceso de creación (para detectar ciclos)
     * @var array<string, bool>
     */
    private $creating = [];

    /**
          * Tags para agrupar servicios: tag => [id, id, ...]
     * @var array<string, string[]>
     */
    private $tags = [];

    // ─────────────────────────────────────────────────────────
    //  Singleton
    // ─────────────────────────────────────────────────────────

    /**
          * Obtener la única instancia.
     *
     * @return ServiceContainer
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
          * Reset del contenedor (para tests).
     */
    public static function resetInstance() {
        if (self::$instance !== null) {
            self::$instance->factories = [];
            self::$instance->resolved  = [];
            self::$instance->isFactory = [];
            self::$instance->creating  = [];
            self::$instance->tags      = [];
        }
        self::$instance = null;
    }

    /**
          * Constructor privado (singleton).
     */
    private function __construct() {
    }

    // ─────────────────────────────────────────────────────────
        //  Registro
    // ─────────────────────────────────────────────────────────

    /**
          * Registrar un servicio.
     *
          * Si $value es callable (closure o [class, method]),
          * se invocará UNA sola vez en el primer get(). El resultado se cachea.
     *
          * Si $value no es callable, se guarda como valor listo.
     *
          * @param string $id    Identificador único (p. ej., 'db', 'settings')
          * @param mixed  $value Fábrica (callable) o valor listo
     * @return $this
     */
    public function set($id, $value) {
                // Eliminamos el servicio previamente resuelto al re-registrar
        unset($this->resolved[$id]);
        unset($this->isFactory[$id]);

        if (is_callable($value) && !is_string($value) && !is_array($value)) {
                        // Closure → fábrica perezosa (singleton)
            $this->factories[$id] = $value;
        } else {
                        // Valor listo → directamente a resolved
            $this->resolved[$id] = $value;
        }

        return $this;
    }

    /**
          * Registrar un servicio de fábrica (instancia nueva en cada get).
     *
          * @param string   $id      Identificador
          * @param callable $factory Fábrica: function(ServiceContainer $c): mixed
     * @return $this
     */
    public function factory($id, $factory) {
        unset($this->resolved[$id]);
        $this->factories[$id] = $factory;
        $this->isFactory[$id] = true;

        return $this;
    }

    /**
          * Añadir un tag a un servicio.
     *
          * Los tags permiten agrupar servicios relacionados y obtenerlos por lotes.
          * Los usa el sistema modular para recolectar event subscribers,
          * tareas cron, rutas, etc.
     *
          * @param string $id  Identificador del servicio
          * @param string $tag Nombre del tag (p. ej., 'event.subscriber', 'cron')
     * @return $this
     */
    public function tag($id, $tag) {
        if (!isset($this->tags[$tag])) {
            $this->tags[$tag] = [];
        }
        if (!in_array($id, $this->tags[$tag], true)) {
            $this->tags[$tag][] = $id;
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────
        //  Obtención
    // ─────────────────────────────────────────────────────────

    /**
          * Obtener un servicio por identificador.
     *
          * @param string $id Identificador
     * @return mixed
          * @throws RuntimeException Si el servicio no se encuentra o se detecta una dependencia circular
     */
    public function get($id) {
                // 1. Ya resuelto (singleton) — retorno inmediato
        if (array_key_exists($id, $this->resolved) && empty($this->isFactory[$id])) {
            return $this->resolved[$id];
        }

                // 2. Hay fábrica — la invocamos
        if (isset($this->factories[$id])) {
                        // Detección de dependencias circulares
            if (isset($this->creating[$id])) {
                throw new RuntimeException(
                    "ServiceContainer: dependencia circular al crear el servicio '{$id}'. "
                        . "Cadena: " . implode(' → ', array_keys($this->creating)) . " → {$id}"
                );
            }

            $this->creating[$id] = true;

            try {
                $service = call_user_func($this->factories[$id], $this);
            } catch (Exception $e) {
                unset($this->creating[$id]);
                throw new RuntimeException(
                    "ServiceContainer: error al crear el servicio '{$id}': " . $e->getMessage(),
                    0,
                    $e
                );
            }

            unset($this->creating[$id]);

                        // Los servicios de fábrica no se cachean
            if (empty($this->isFactory[$id])) {
                $this->resolved[$id] = $service;
            }

            return $service;
        }

        throw new RuntimeException(
            "ServiceContainer: el servicio '{$id}' no está registrado. "
                . "Servicios disponibles: " . implode(', ', $this->keys())
        );
    }

    /**
          * Obtener un servicio o devolver un valor por defecto.
     *
          * @param string $id      Identificador
          * @param mixed  $default Valor por defecto (si el servicio no se encuentra)
     * @return mixed
     */
    public function getOrDefault($id, $default = null) {
        if ($this->has($id)) {
            return $this->get($id);
        }
        return $default;
    }

    /**
          * Obtener todos los servicios con el tag indicado.
     *
          * @param string $tag Nombre del tag
          * @return array Array de servicios [id => service]
     */
    public function getTagged($tag) {
        $services = [];
        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $id) {
                if ($this->has($id)) {
                    $services[$id] = $this->get($id);
                }
            }
        }
        return $services;
    }

    /**
          * Comprobar si un servicio está registrado.
     *
          * @param string $id Identificador
     * @return bool
     */
    public function has($id) {
        return array_key_exists($id, $this->resolved) || isset($this->factories[$id]);
    }

    /**
          * Lista de todos los identificadores registrados.
     *
     * @return string[]
     */
    public function keys() {
        return array_unique(
            array_merge(
                array_keys($this->resolved),
                array_keys($this->factories)
            )
        );
    }

    /**
          * Eliminar un servicio del contenedor.
     *
          * @param string $id Identificador
     * @return $this
     */
    public function remove($id) {
        unset(
            $this->factories[$id],
            $this->resolved[$id],
            $this->isFactory[$id]
        );

                // Eliminar de los tags
        foreach ($this->tags as $tag => &$ids) {
            $ids = array_values(array_filter($ids, function ($v) use ($id) {
                return $v !== $id;
            }));
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────
        //  Registro masivo
    // ─────────────────────────────────────────────────────────

    /**
          * Registrar varios servicios desde un array.
     *
          * @param array $services Array [id => value/callable, ...]
     * @return $this
     */
    public function register(array $services) {
        foreach ($services as $id => $value) {
            $this->set($id, $value);
        }
        return $this;
    }

    // ─────────────────────────────────────────────────────────
        //  Sintaxis tipo ArrayAccess (sin implements — no hacen falta interfaces)
    // ─────────────────────────────────────────────────────────

    /**
          * Acceso mágico: $container->db en vez de $container->get('db')
     *
     * @param string $id
     * @return mixed
     */
    public function __get($id) {
        return $this->get($id);
    }

    /**
          * Comprobación mágica: isset($container->db)
     *
     * @param string $id
     * @return bool
     */
    public function __isset($id) {
        return $this->has($id);
    }

    // ─────────────────────────────────────────────────────────
        //  Depuración
    // ─────────────────────────────────────────────────────────

    /**
          * Volcado del contenido del contenedor (para depurar).
     *
     * @return array
     */
    public function dump() {
        $result = [];
        foreach ($this->keys() as $id) {
            $status = 'pending';
            $type   = 'unknown';

            if (array_key_exists($id, $this->resolved)) {
                $status = 'resolved';
                $type   = is_object($this->resolved[$id])
                    ? get_class($this->resolved[$id])
                    : gettype($this->resolved[$id]);
            } elseif (isset($this->factories[$id])) {
                $status = !empty($this->isFactory[$id]) ? 'factory' : 'lazy';
                $type   = 'callable';
            }

            $result[$id] = [
                'status' => $status,
                'type'   => $type,
                'tags'   => $this->getTagsFor($id),
            ];
        }

        ksort($result);
        return $result;
    }

    /**
          * Obtener todos los tags de un servicio.
     *
     * @param string $id
     * @return string[]
     */
    private function getTagsFor($id) {
        $result = [];
        foreach ($this->tags as $tag => $ids) {
            if (in_array($id, $ids, true)) {
                $result[] = $tag;
            }
        }
        return $result;
    }
}
