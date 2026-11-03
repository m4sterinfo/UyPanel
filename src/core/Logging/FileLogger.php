<?php

/**
 * Logger de archivos
 *
 * Registra eventos de negocio en un archivo en formato JSON base64-encoded.
 * Cada línea es un registro independiente. El archivo se bloquea al escribir (LOCK_EX).
 *
 * Formato del registro:
 *   base64({"type":"pdo","message":"...","extra":"...","line":0,"time":1708300000,"env":"cli"})
 *
 * Filtrado:
 *   - Se ignoran los registros que contienen 'panel_logs' en extra (logs recursivos)
 *   - Se ignoran 'timeout exceeded', 'lock wait timeout', 'duplicate entry' (errores ruidosos de MySQL)
 *
 * @see LoggerInterface
 */

require_once __DIR__ . '/LoggerInterface.php';

class FileLogger implements LoggerInterface {
    /**
          * Ruta al archivo de log.
          * Por defecto se usa LOGS_TMP_PATH . 'error_log.log'
     *
     * @var string|null
     */
    private static ?string $logFile = null;

    /**
          * Establecer la ruta al archivo de log.
     *
          * @param string $path Ruta completa al archivo
     */
    public static function setLogFile(string $path): void {
        self::$logFile = $path;
    }

    /**
          * Obtener la ruta actual al archivo de log.
          * Si no se estableció explícitamente, se usa LOGS_TMP_PATH . 'error_log.log'
     *
     * @return string
     */
    public static function getLogFile(): string {
        if (self::$logFile !== null) {
            return self::$logFile;
        }

        if (defined('LOGS_TMP_PATH')) {
            return LOGS_TMP_PATH . 'error_log.log';
        }

        return '/tmp/uypanel_error.log';
    }

    /**
          * Escribir un mensaje de log en el archivo.
     *
          * Antes de escribir se aplica el filtro: los errores ruidosos de MySQL y las
          * referencias recursivas a panel_logs se ignoran.
     *
          * @param string     $type    Tipo de evento ('pdo', 'epg', 'error', etc.)
          * @param string     $message Texto del mensaje
          * @param string|int $extra   Datos adicionales (consulta SQL, trace, etc.)
          * @param int        $line    Número de línea (opcional)
     */
    public static function log(string $type, string $message, $extra = '', int $line = 0): void {
        $extra = (string) $extra;

        $rTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $rCaller = $rTrace[1] ?? [];
        $rFile = (string) ($rCaller['file'] ?? '');
        if ($line <= 0 && isset($rCaller['line'])) {
            $line = (int) $rCaller['line'];
        }

                // Filtrado de registros ruidosos / recursivos
        if (self::shouldSkip($message, $extra)) {
            return;
        }

        $rData = [
            'type'    => $type,
            'message' => $message,
            'extra'   => $extra,
            'file'    => $rFile,
            'line'    => $line,
            'time'    => time(),
            'env'     => php_sapi_name(),
        ];

        $logLine = base64_encode(json_encode($rData, JSON_UNESCAPED_UNICODE)) . "\n";

        file_put_contents(
            self::getLogFile(),
            $logLine,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
          * Comprobar si hay que saltar el registro (filtrado de ruido).
     *
          * @param string $message Texto del mensaje
          * @param string $extra   Datos adicionales
     *
          * @return bool true — saltar, false — escribir
     */
    private static function shouldSkip(string $message, string $extra): bool {
                // Log recursivo: registro sobre la tabla panel_logs
        if (stripos($extra, 'panel_logs') !== false) {
            return true;
        }

                // Errores ruidosos de MySQL — los saltamos
        $noisy = ['timeout exceeded', 'lock wait timeout', 'duplicate entry'];
        $messageLower = strtolower($message);
        foreach ($noisy as $pattern) {
            if (strpos($messageLower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
