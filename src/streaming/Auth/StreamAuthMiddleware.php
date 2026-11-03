<?php

/**
 * Middleware común de streaming auth para live/vod/timeshift.
 *
 * Extrae los bloques duplicados:
 *  - Cabeceras de respuesta (CORS, Server, Protection, Alt-Svc, unique cookie)
 *  - Descifrado y validación básica del token
 *
 * Limitación: static-only, sin contenedor DI. Globals vía parámetros.
 */
class StreamAuthMiddleware {

    /**
          * Envío de las cabeceras comunes de respuesta de streaming.
     *
          * Estaba duplicado en live.php, vod.php, timeshift.php (~25 líneas cada uno).
     */
    public static function sendStreamHeaders($rSettings, $rServers) {
        header('Access-Control-Allow-Origin: *');

        if (!empty($rSettings['send_server_header'])) {
            header('Server: ' . $rSettings['send_server_header']);
        }

        if ($rSettings['send_protection_headers']) {
            header('X-XSS-Protection: 0');
            header('X-Content-Type-Options: nosniff');
        }

        if ($rSettings['send_altsvc_header']) {
            header('Alt-Svc: h3-29=":' . $rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-T051=":' . $rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-Q050=":' . $rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-Q046=":' . $rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-Q043=":' . $rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,quic=":' . $rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000; v="46,43"');
        }

        if (empty($rSettings['send_unique_header_domain']) && !filter_var(HOST, FILTER_VALIDATE_IP)) {
            $rSettings['send_unique_header_domain'] = '.' . HOST;
        }

        if (!empty($rSettings['send_unique_header'])) {
            $rExpires = new DateTime('+6 months', new DateTimeZone('GMT'));
            header('Set-Cookie: ' . $rSettings['send_unique_header'] . '=' . Encryption::randomString(11) . '; Domain=' . $rSettings['send_unique_header_domain'] . '; Expires=' . $rExpires->format(DATE_RFC2822) . '; Path=/; Secure; HttpOnly; SameSite=none');
        }
    }

    /**
          * Descifrado del token y validación básica (formato + caducidad).
     *
          * @param string $rToken       Token cifrado de la petición
          * @param array  $rSettings    Ajustes (necesita live_streaming_pass)
          * @param array  $rServers     Servidores (necesita time_offset)
          * @param string $rIP          IP del cliente para el log
          * @return array               Datos descifrados del token
     */
    public static function decryptToken($rToken, $rSettings, $rServers, $rIP) {
        $rTokenData = json_decode(
            Encryption::decrypt($rToken, $rSettings['live_streaming_pass'], OPENSSL_EXTRA),
            true
        );

        if (!is_array($rTokenData)) {
            DatabaseLogger::clientLog(0, 0, 'LB_TOKEN_INVALID', $rIP);
            generateError('LB_TOKEN_INVALID');
        }

        if (isset($rTokenData['expires']) && $rTokenData['expires'] < time() - intval($rServers[SERVER_ID]['time_offset'])) {
            generateError('TOKEN_EXPIRED');
        }

        return $rTokenData;
    }
}
