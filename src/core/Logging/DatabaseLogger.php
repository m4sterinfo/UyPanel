<?php

/**
 * Logger de peticiones de clientes (escritura en archivo para inserción posterior en BD)
 *
 * Registra los eventos de streaming de clientes en el archivo client_request.log.
 * Formato: JSON base64-encoded, una línea por registro.
 *
 * Los registros se procesan periódicamente con una tarea cron y se insertan
 * en la tabla client_logs de la base de datos.
 *
 * Eventos típicos: AUTH_FAILED, USER_EXPIRED, USER_BAN, IP_MISMATCH,
 *                   COUNTRY_DISALLOW, RESTREAM_DETECT, LINE_CREATE_FAIL, etc.
 *
 * @see LoggerInterface
 */

require_once __DIR__ . '/LoggerInterface.php';

class DatabaseLogger implements LoggerInterface {
    /**
          * Ruta al archivo de log de peticiones de clientes.
     *
     * @var string|null
     */
    private static ?string $logFile = null;

    /**
          * Ajuste: si está activado el guardado de logs de clientes.
          * Corresponde al ajuste client_logs_save de $rSettings.
     *
          * @var int|null null = sin definir (se comprueba vía StreamingUtilities), 0 = off, 1+ = on
     */
    private static ?int $enabled = null;

    /**
          * Establecer la ruta al archivo de log.
     *
          * @param string $path Ruta completa al archivo
     */
    public static function setLogFile(string $path): void {
        self::$logFile = $path;
    }

    /**
          * Establecer si está activado el logging de peticiones de clientes.
     *
          * @param int $value 0 — desactivado, 1+ — activado
     */
    public static function setEnabled(int $value): void {
        self::$enabled = $value;
    }

    /**
          * Obtener la ruta actual al archivo de log.
     *
     * @return string
     */
    public static function getLogFile(): string {
        if (self::$logFile !== null) {
            return self::$logFile;
        }

        if (defined('LOGS_TMP_PATH')) {
            return LOGS_TMP_PATH . 'client_request.log';
        }

        return '/tmp/uypanel_client.log';
    }

    /**
          * Registrar un evento de streaming de cliente.
     *
          * Corresponde al formato antiguo de StreamingUtilities::clientLog().
          * Firma vía LoggerInterface, pero internamente guarda datos extendidos.
     *
          * @param string     $type    Acción (AUTH_FAILED, USER_EXPIRED, etc.)
          * @param string     $message No se usa para logs de clientes (pasar '')
          * @param string|int $extra   Datos adicionales
          * @param int        $line    No se usa (compatibilidad con LoggerInterface)
     */
    public static function log(string $type, string $message, $extra = '', int $line = 0): void {
                // Por defecto el logging está activado (si no se define explícitamente)
        if (self::$enabled !== null && self::$enabled == 0) {
            return;
        }

        $rUserAgent = (!empty($_SERVER['HTTP_USER_AGENT'])
            ? htmlentities($_SERVER['HTTP_USER_AGENT'])
            : '');

        $rQueryString = (!empty($_SERVER['QUERY_STRING'])
            ? htmlentities($_SERVER['QUERY_STRING'])
            : '');

        $rData = [
            'user_id'      => 0,
            'stream_id'    => 0,
            'action'       => $type,
            'query_string' => $rQueryString,
            'user_agent'   => $rUserAgent,
            'user_ip'      => '',
            'time'         => time(),
            'extra_data'   => (string) $extra,
        ];

        file_put_contents(
            self::getLogFile(),
            base64_encode(json_encode($rData, JSON_UNESCAPED_UNICODE)) . "\n",
            FILE_APPEND
        );
    }

    /**
          * Registrar un evento de streaming de cliente (versión extendida).
     *
          * Análogo directo de StreamingUtilities::clientLog() con parámetros completos.
     *
          * @param int    $streamID ID del stream
          * @param int    $userID   ID del usuario
          * @param string $action   Acción (AUTH_FAILED, USER_EXPIRED, etc.)
          * @param string $ip       Dirección IP del cliente
          * @param string $data     Datos adicionales (JSON o string)
          * @param bool   $bypass   Registrar incluso si el logging está desactivado
     */
    public static function clientLog(
        int $streamID,
        int $userID,
        string $action,
        string $ip,
        string $data = '',
        bool $bypass = false
    ): void {
                // Comprobamos el ajuste: ¿está activado el logging?
        if (!$bypass) {
                        // Si se definió vía setEnabled
            if (self::$enabled !== null && self::$enabled == 0) {
                return;
            }

                        // Si no está definido — comprobamos vía ajustes globales (compatibilidad hacia atrás)
            if (self::$enabled === null && !empty($GLOBALS['rSettings'])) {
                if (
                    isset($GLOBALS['rSettings']['client_logs_save'])
                    && $GLOBALS['rSettings']['client_logs_save'] == 0
                ) {
                    return;
                }
            }
        }

        $rUserAgent = (!empty($_SERVER['HTTP_USER_AGENT'])
            ? htmlentities($_SERVER['HTTP_USER_AGENT'])
            : '');

        $rQueryString = (!empty($_SERVER['QUERY_STRING'])
            ? htmlentities($_SERVER['QUERY_STRING'])
            : '');

        $rData = [
            'user_id'      => $userID,
            'stream_id'    => $streamID,
            'action'       => $action,
            'query_string' => $rQueryString,
            'user_agent'   => $rUserAgent,
            'user_ip'      => $ip,
            'time'         => time(),
            'extra_data'   => $data,
        ];

        file_put_contents(
            self::getLogFile(),
            base64_encode(json_encode($rData, JSON_UNESCAPED_UNICODE)) . "\n",
            FILE_APPEND
        );
    }
}
