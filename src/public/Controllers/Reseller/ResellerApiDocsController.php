<?php

/**
 * ResellerApiDocsController — Documentación del API externo (panel reseller).
 *
 * Prepara el endpoint del API del grupo del reseller (access code type 4),
 * su api_key actual, sus paquetes y sus permisos, y renderiza
 * Views/reseller/api_docs.php.
 */
class ResellerApiDocsController extends BaseResellerController
{
    public function index()
    {
        global $db;

        $this->requirePermission();
        $this->setTitle('API Documentation');

        $rApiCode = null;
        foreach (getcodes(4) as $rCode) {
            if ($rCode['enabled'] && in_array($GLOBALS['rUserInfo']['member_group_id'], json_decode($rCode['groups'], true) ?: [])) {
                $rApiCode = $rCode;
                break;
            }
        }

        $rServers = ServerRepository::getAll();
        if ($rApiCode) {
            if (empty($GLOBALS['rUserInfo']['reseller_dns'])) {
                $rApiUrl = $rServers[SERVER_ID]['site_url'] . $rApiCode['code'] . '/';
            } else {
                $rApiUrl = 'http://' . $GLOBALS['rUserInfo']['reseller_dns'] . ':' . intval($rServers[SERVER_ID]['http_broadcast_port']) . '/' . $rApiCode['code'] . '/';
            }
        } else {
            $rApiUrl = null;
        }

        $rPackages = getPackages($GLOBALS['rUserInfo']['member_group_id'], 'line') ?: [];

        $this->render('api_docs', [
            'rApiUrl'          => $rApiUrl,
            'rPackages'        => $rPackages,
            'rCanSubResellers' => $GLOBALS['rPermissions']['create_sub_resellers'] ?? false,
            'rSubGroups'       => (is_array($GLOBALS['rPermissions']['subresellers'] ?? null) ? $GLOBALS['rPermissions']['subresellers'] : []),
            'rCanTrials'       => canGenerateTrials($GLOBALS['rUserInfo']['id']),
            'rCanBouquets'     => $GLOBALS['rPermissions']['allow_change_bouquets'] ?? false,
            'rCanRestrictions' => $GLOBALS['rPermissions']['allow_restrictions'] ?? false,
            'rActions'         => self::actions(),
        ]);
    }

    private static function actions(): array
    {
        return [
            'Cuenta y paquetes' => [
                ['user_info', '', 'Tu información: créditos, api_key y permisos de tu grupo.'],
                ['packages', '', 'Paquetes disponibles para tu grupo, con el coste en créditos de cada uno.'],
            ],
            'Líneas' => [
                ['get_lines', 'start, limit', 'Lista paginada de TUS líneas (y las de tus sub-resellers).'],
                ['get_line', 'id', 'Datos completos de una línea tuya.'],
                ['create_line', 'package (obligatorio), trial, username, password, member_id, contact, reseller_notes, bouquets_selected, allowed_ips, allowed_ua', 'Crea una línea con un paquete: aplica bouquet, outputs, conexiones y vencimiento del paquete, y descuenta los créditos.'],
                ['edit_line', 'id + campos a cambiar', 'Edita una línea. CON package = RENOVAR (extiende el vencimiento y descuenta créditos). Sin package = solo edición (no cuesta).'],
                ['delete_line', 'id', 'Elimina una línea tuya definitivamente.'],
                ['disable_line / enable_line', 'id', 'Deshabilita / habilita la línea del cliente.'],
            ],
            'Dispositivos MAG' => [
                ['get_mags', 'start, limit', 'Lista de dispositivos MAG.'],
                ['get_mag', 'id', 'Datos de un dispositivo MAG.'],
                ['create_mag', 'mac (obligatorio), package, trial, member_id', 'Crea un MAG con su línea interna (usuario/password generados de 32 caracteres).'],
                ['edit_mag', 'id + campos', 'Edita el dispositivo. Con package = renueva.'],
                ['delete_mag', 'id', 'Elimina el dispositivo MAG.'],
                ['disable_mag / enable_mag', 'id', 'Deshabilita / habilita.'],
                ['convert_mag', 'id', 'Convierte el MAG en línea normal (conserva usuario y paquete).'],
            ],
            'Dispositivos Enigma2' => [
                ['get_enigmas', 'start, limit', 'Lista de dispositivos Enigma2.'],
                ['get_enigma', 'id', 'Datos de un dispositivo Enigma2.'],
                ['create_enigma', 'mac (obligatorio), package, trial, member_id', 'Crea un Enigma2 con su línea interna.'],
                ['edit_enigma', 'id + campos', 'Edita el dispositivo. Con package = renueva.'],
                ['delete_enigma', 'id', 'Elimina el dispositivo Enigma2.'],
                ['disable_enigma / enable_enigma', 'id', 'Deshabilita / habilita.'],
                ['convert_enigma', 'id', 'Convierte el Enigma2 en línea normal.'],
            ],
            'Sub-resellers (solo super resellers)' => [
                ['get_users', 'start, limit', 'Lista de tus sub-resellers.'],
                ['get_user', 'id', 'Datos de un sub-reseller.'],
                ['create_user', 'username, password, email, member_group_id, owner_id, reseller_dns, notes', 'Crea un sub-reseller. Coste: create_sub_resellers_price del grupo. member_group_id debe estar en los grupos permitidos.'],
                ['edit_user', 'id + campos', 'Edita un sub-reseller.'],
                ['delete_user', 'id', 'Elimina un sub-reseller.'],
                ['disable_user / enable_user', 'id', 'Bloquea / desbloquea su acceso al panel.'],
                ['adjust_credits', 'id, credits, note', 'Transfiere créditos tuyos a un sub-reseller (negativo para recuperarlos).'],
            ],
            'Logs' => [
                ['activity_logs', 'start, limit', 'Actividad de tus líneas (conexiones pasadas).'],
                ['live_connections', 'start, limit', 'Conexiones activas ahora mismo.'],
                ['user_logs', 'start, limit', 'Historial de altas/renovaciones/bajas con coste en créditos.'],
            ],
        ];
    }
}
