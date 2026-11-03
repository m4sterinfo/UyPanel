<?php

/**
 * Contrato de logging
 *
 * Interfaz única para todas las implementaciones de loggers (archivo, BD, etc.).
 * Cada implementación decide por sí misma dónde y en qué formato escribir.
 *
 * @see FileLogger      Logging en archivo (errores PDO, EPG, etc.)
 * @see DatabaseLogger  Logging de peticiones de streaming de clientes
 */
interface LoggerInterface {
    /**
          * Escribir un mensaje de log.
     *
          * @param string     $type    Tipo de evento (por ejemplo: 'pdo', 'epg', 'AUTH_FAILED')
          * @param string     $message Texto del mensaje
          * @param string|int $extra   Datos adicionales (trace, query, etc.)
          * @param int        $line    Número de línea (opcional)
     *
     * @return void
     */
    public static function log(string $type, string $message, $extra = '', int $line = 0): void;
}
