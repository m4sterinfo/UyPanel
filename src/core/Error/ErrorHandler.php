<?php

/**
 * Manejo de errores
 *
 * Funciones generateError() y generate404() para mostrar errores.
 *
 * generateError() — muestra el error con código y descripción (en modo debug)
 *                    o la página 404 estándar (en producción).
 *
 * generate404()   — página estándar «404 Not Found» aparentando ser nginx.
 *
 * Dependencias:
 *   $rErrorCodes (array global de ErrorCodes.php)
 *   $rSettings   (array global de ajustes, se carga más tarde)
 */

/**
 * Generación de la página de error.
 *
 * En modo debug ($rSettings['debug_show_errors'] === true) muestra
 * una página estilizada con el código de error y la descripción.
 * En producción — invoca generate404().
 *
 * @param string    $rError Código de error (clave de $rErrorCodes)
 * @param bool      $rKill  Terminar la ejecución tras la salida (default: true)
 * @param int|null  $rCode  Código HTTP de respuesta (null = 404)
 */
function generateError($rError, $rKill = true, $rCode = null) {
    global $rErrorCodes;
    global $rSettings;

    if (isset($rSettings['debug_show_errors']) && $rSettings['debug_show_errors']) {
        $rErrorDescription = (isset($rErrorCodes[$rError]) ? $rErrorCodes[$rError] : '');
        $rStyle = '*{-webkit-box-sizing:border-box;box-sizing:border-box}body{padding:0;margin:0}#notfound{position:relative;height:100vh}#notfound .notfound{position:absolute;left:50%;top:50%;-webkit-transform:translate(-50%,-50%);-ms-transform:translate(-50%,-50%);transform:translate(-50%,-50%)}.notfound{max-width:520px;width:100%;line-height:1.4;text-align:center}.notfound .notfound-404{position:relative;height:200px;margin:0 auto 20px;z-index:-1}.notfound .notfound-404 h1{font-family:Montserrat,sans-serif;font-size:236px;font-weight:200;margin:0;color:#211b19;text-transform:uppercase;position:absolute;left:50%;top:50%;-webkit-transform:translate(-50%,-50%);-ms-transform:translate(-50%,-50%);transform:translate(-50%,-50%)}.notfound .notfound-404 h2{font-family:Montserrat,sans-serif;font-size:28px;font-weight:400;text-transform:uppercase;color:#211b19;background:#fff;padding:10px 5px;margin:auto;display:inline-block;position:absolute;bottom:0;left:0;right:0}.notfound p{font-family:Montserrat,sans-serif;font-size:14px;font-weight:300;text-transform:uppercase}@media only screen and (max-width:767px){.notfound .notfound-404 h1{font-size:148px}}@media only screen and (max-width:480px){.notfound .notfound-404{height:148px;margin:0 auto 10px}.notfound .notfound-404 h1{font-size:86px}.notfound .notfound-404 h2{font-size:16px}}';
        echo '<html><head><title>UyPanel - Debug Mode</title><link href="https://fonts.googleapis.com/css?family=Montserrat:200,400,700" rel="stylesheet"><style>' . $rStyle . '</style></head><body><div id="notfound"><div class="notfound"><div class="notfound-404"><h1>UyPanel</h1><h2>' . $rError . '</h2><br/></div><p>' . $rErrorDescription . '</p></div></div></body></html>';

        if ($rKill) {
            exit();
        }
    } else {
        if ($rKill) {
            if (!$rCode) {
                generate404();
            } else {
                http_response_code($rCode);
                exit();
            }
        }
    }
}

/**
 * Generación de la página 404 estándar (imitación de nginx).
 *
 * @param bool $rKill Terminar la ejecución tras la salida (default: true)
 */
function generate404($rKill = true) {
    echo '<html>' . "\r\n" . '<head><title>404 Not Found</title></head>' . "\r\n" . '<body>' . "\r\n" . '<center><h1>404 Not Found</h1></center>' . "\r\n" . '<hr><center>nginx</center>' . "\r\n" . '</body>' . "\r\n" . '</html>' . "\r\n" . '<!-- a padding to disable MSIE and Chrome friendly error page -->' . "\r\n" . '<!-- a padding to disable MSIE and Chrome friendly error page -->' . "\r\n" . '<!-- a padding to disable MSIE and Chrome friendly error page -->' . "\r\n" . '<!-- a padding to disable MSIE and Chrome friendly error page -->' . "\r\n" . '<!-- a padding to disable MSIE and Chrome friendly error page -->' . "\r\n" . '<!-- a padding to disable MSIE and Chrome friendly error page -->';
    http_response_code(404);

    if ($rKill) {
        exit();
    }
}
