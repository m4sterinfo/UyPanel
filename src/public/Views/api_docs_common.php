<?php

/**
 * api_docs_common.php — Fragmentos compartidos entre las páginas de
 * documentación del API (admin y reseller).
 *
 * Se incluye con require_once desde las vistas usando MAIN_HOME.
 * Las funciones devuelven HTML (echo directo también vale, pero devolver
 * string permite componer).
 */

if (!function_exists('apiDocsStatusTable')) {
    function apiDocsStatusTable() {
        $rStatuses = [
            ['STATUS_SUCCESS', 'La operación se ejecutó correctamente.'],
            ['STATUS_FAILURE', 'Error genérico: datos insuficientes o sin permisos.'],
            ['STATUS_INVALID_INPUT', 'Faltan parámetros obligatorios o tienen formato inválido.'],
            ['STATUS_INVALID_PACKAGE', 'El paquete no existe, no es del tipo correcto o no está disponible para tu grupo.'],
            ['STATUS_INSUFFICIENT_CREDITS', 'No tienes créditos suficientes para esa operación.'],
            ['STATUS_NO_TRIALS', 'Ya no puedes generar más trials (límite del grupo alcanzado).'],
            ['STATUS_EXISTS_USERNAME', 'Ese username ya está en uso por otra línea.'],
            ['STATUS_INVALID_USERNAME', 'El username es demasiado corto (mínimo del grupo).'],
            ['STATUS_INVALID_PASSWORD', 'El password es demasiado corto (mínimo del grupo).'],
            ['STATUS_INVALID_DATE', 'La fecha de expiración no es válida.'],
            ['STATUS_INVALID_MAC', 'La MAC no es válida o ya existe (STATUS_EXISTS_MAC).'],
            ['STATUS_INVALID_SUBRESELLER', 'El grupo indicado no es un sub-reseller permitido para ti.'],
            ['STATUS_NOT_RESELLER', 'La api_key no pertenece a un reseller activo.'],
            ['STATUS_INVALID_EMAIL', 'El email no es válido.'],
            ['STATUS_DISABLED', 'La cuenta está deshabilitada.'],
        ];

        $rHTML = '<table class="table table-striped table-borderless docs-table"><thead><tr><th style="width:32%;">Status</th><th>Significado</th></tr></thead><tbody>';
        foreach ($rStatuses as $rStatus) {
            $rClass = ($rStatus[0] == 'STATUS_SUCCESS' ? 'text-success' : 'text-danger');
            $rHTML .= '<tr><td><code class="' . $rClass . '">' . $rStatus[0] . '</code></td><td>' . $rStatus[1] . '</td></tr>';
        }
        $rHTML .= '</tbody></table>';

        return $rHTML;
    }
}

if (!function_exists('apiDocsCopyBtn')) {
    function apiDocsCopyBtn($rText, $rLabel = 'Copiar') {
        return '<button type="button" class="btn btn-xs btn-light waves-effect waves-light copy-btn" data-copy="' . htmlspecialchars($rText) . '"><i class="mdi mdi-content-copy"></i> ' . $rLabel . '</button>';
    }
}

if (!function_exists('apiDocsCurl')) {
    function apiDocsCurl($rText) {
        return '<div class="docs-curl"><code>' . htmlspecialchars($rText) . '</code>' . apiDocsCopyBtn($rText) . '</div>';
    }
}
