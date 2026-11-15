<?php

/**
 * ApiDocsController — Documentación del API externo (panel admin).
 *
 * Prepara los datos dinámicos (endpoint, api_key del usuario, paquetes,
 * outputs) y renderiza Views/admin/api_docs.php. La referencia de acciones
 * vive como arrays de datos para que la vista sea genérica.
 */
class ApiDocsController extends BaseAdminController
{
    public function index()
    {
        global $db;

        $this->requirePermission();
        $this->setTitle('API Documentation');

        $rServers = ServerRepository::getAll();
        $rApiCodes = array();
        foreach (getcodes(3) as $rCode) {
            if ($rCode['enabled']) {
                $rApiCodes[] = $rServers[SERVER_ID]['site_url'] . $rCode['code'] . '/';
            }
        }

        $rResellerApiUrl = null;
        foreach (getcodes(4) as $rCode) {
            if ($rCode['enabled']) {
                $rResellerApiUrl = $rServers[SERVER_ID]['site_url'] . $rCode['code'] . '/';
                break;
            }
        }

        $rPackages = array();
        $db->query('SELECT `id`, `package_name`, `is_line`, `is_mag`, `official_credits`, `trial_credits`, `official_duration`, `official_duration_in`, `trial_duration`, `trial_duration_in` FROM `users_packages` ORDER BY `id` ASC;');
        foreach ($db->get_rows() as $rRow) {
            $rPackages[] = $rRow;
        }

        $rOutputs = array();
        $db->query('SELECT `access_output_id`, `output_name`, `output_key` FROM `output_formats` ORDER BY `access_output_id` ASC;');
        foreach ($db->get_rows() as $rRow) {
            $rOutputs[] = $rRow;
        }

        $this->render('api_docs', [
            'rApiCodes'       => $rApiCodes,
            'rResellerApiUrl' => $rResellerApiUrl,
            'rPackages'       => $rPackages,
            'rOutputs'        => $rOutputs,
            'rActions'        => self::actions(),
        ]);
    }

    private static function actions(): array
    {
        return [
            'Cuenta' => [
                ['user_info', '', 'Información de tu cuenta (api_key, créditos, permisos).'],
                ['adjust_credits', 'id, credits, reason', 'Ajusta los créditos de un reseller (positivo o negativo).'],
            ],
            'Líneas' => [
                ['get_lines', 'start, limit', 'Lista paginada de líneas (usa show_columns / hide_columns para filtrar columnas).'],
                ['get_line', 'id', 'Datos completos de una línea.'],
                ['create_line', 'member_id, bouquets_selected, access_output, exp_date, max_connections, username, password, ...', 'Crea una línea con campos crudos. bouquets_selected es obligatorio (JSON "[1,2]" o comas "1,2"). access_output por defecto: todos los outputs.'],
                ['edit_line', 'id + campos a cambiar', 'Edita una línea (mismos campos que create_line). Para renovar, pasa exp_date.'],
                ['delete_line', 'id', 'Elimina una línea definitivamente.'],
                ['disable_line / enable_line', 'id', 'Deshabilita / habilita la línea (enabled).'],
                ['ban_line / unban_line', 'id', 'Banea / desbanea la línea (admin_enabled).'],
            ],
            'Resellers (usuarios)' => [
                ['get_users', 'start, limit', 'Lista paginada de resellers.'],
                ['get_user', 'id', 'Datos de un reseller.'],
                ['create_user', 'username, password, email, member_group_id, credits, ...', 'Crea un reseller.'],
                ['edit_user', 'id + campos a cambiar', 'Edita un reseller.'],
                ['delete_user', 'id', 'Elimina un reseller (y sus líneas).'],
                ['disable_user / enable_user', 'id', 'Deshabilita / habilita el login de un reseller.'],
            ],
            'MAG / Enigma2' => [
                ['get_mags / get_enigmas', 'start, limit', 'Lista paginada de dispositivos.'],
                ['get_mag / get_enigma', 'id', 'Datos de un dispositivo.'],
                ['create_mag / create_enigma', 'mac, package, member_id, ...', 'Crea un dispositivo MAG / Enigma2.'],
                ['edit_mag / edit_enigma', 'id + campos a cambiar', 'Edita un dispositivo.'],
                ['delete_mag / delete_enigma', 'id', 'Elimina un dispositivo.'],
                ['disable_mag / enable_mag', 'id', 'Deshabilita / habilita el dispositivo.'],
                ['ban_mag / unban_mag', 'id', 'Banea / desbanea (solo MAG/Enigma).'],
                ['convert_mag / convert_enigma', 'id', 'Convierte el dispositivo en línea normal.'],
            ],
            'Contenido' => [
                ['get_streams / get_channels / get_stations', 'start, limit', 'Listas de streams, canales creados y radios.'],
                ['get_movies / get_series_list / get_episodes', 'start, limit', 'Listas de VOD.'],
                ['get_stream / get_channel / get_movie / get_episode / get_series', 'id', 'Datos de un elemento.'],
                ['create_stream / create_channel / create_movie / create_episode / create_series', 'campos del formulario', 'Crea el elemento.'],
                ['edit_stream / edit_channel / ...', 'id + campos', 'Edita el elemento.'],
                ['delete_stream / delete_movie / ...', 'id, server_id (opcional)', 'Elimina el elemento.'],
                ['start_stream / stop_stream', 'id, server_id', 'Arranca / detiene el stream en un server.'],
                ['start_movie / stop_movie', 'id, server_id', 'Arranca / detiene la transcodificación VOD.'],
            ],
            'Configuración' => [
                ['get_bouquets / create_bouquet / edit_bouquet / delete_bouquet', 'id, campos', 'Gestión de bouquets.'],
                ['get_packages / create_package / edit_package / delete_package', 'id, campos', 'Gestión de paquetes (users_packages).'],
                ['get_groups / create_group / edit_group / delete_group', 'id, campos', 'Gestión de grupos.'],
                ['get_categories / create_category / edit_category / delete_category', 'id, campos', 'Gestión de categorías.'],
                ['get_access_codes / create_access_code / edit_access_code / delete_access_code', 'id, campos', 'Gestión de access codes (endpoints del panel y del API).'],
                ['get_settings / edit_settings', 'campos', 'Ver / editar la configuración global.'],
                ['get_epgs / reload_epg', 'id (opcional)', 'EPGs y recarga.'],
                ['get_providers / reload_provider', 'id (opcional)', 'Proveedores y recarga.'],
                ['get_transcode_profiles / ...', 'id, campos', 'Perfiles de transcodificación.'],
                ['get_watch_folders / reload_watch_folder', 'id, server_id', 'Watch folders y re-escaneo.'],
                ['get_blocked_isps / add_blocked_isp / delete_blocked_isp', 'id', 'ISPs bloqueadas.'],
                ['get_blocked_uas / add_blocked_ua / delete_blocked_ua', 'id', 'User-Agents bloqueados.'],
                ['get_blocked_ips / add_blocked_ip / delete_blocked_ip / flush_blocked_ips', 'id', 'IPs bloqueadas.'],
                ['get_rtmp_ips / create_rtmp_ip / edit_rtmp_ip / delete_rtmp_ip', 'id, campos', 'IPs RTMP.'],
                ['get_hmacs / create_hmac / edit_hmac / delete_hmac', 'id, campos', 'Claves HMAC.'],
            ],
            'Servidores y sistema' => [
                ['get_servers / get_server', 'id (opcional)', 'Lista / datos de servers.'],
                ['install_server / install_proxy', 'campos SSH del server', 'Instala un LB (type 0) o proxy (type 1).'],
                ['edit_server / edit_proxy / delete_server', 'id, campos', 'Edita / elimina un server.'],
                ['get_server_stats', 'server_id (opcional)', 'Estadísticas del server (CPU, RAM, red).'],
                ['get_free_space', 'server_id (opcional)', 'Espacio en disco.'],
                ['get_fpm_status / get_rtmp_stats / get_pids', 'server_id (opcional)', 'Estado de PHP-FPM / RTMP / procesos.'],
                ['get_certificate_info', 'server_id (opcional)', 'Info del certificado SSL.'],
                ['reload_nginx / reload_cache', 'server_id (opcional)', 'Recarga nginx / reconstruye la caché.'],
                ['clear_temp / clear_streams', 'server_id (opcional)', 'Limpieza de temporales.'],
                ['get_directory', 'server_id, dir', 'Lista un directorio del server.'],
                ['kill_pid / kill_connection', 'server_id, pid / activity_id', 'Mata un proceso / una conexión activa.'],
                ['mysql_query', 'query', 'Ejecuta un SQL arbitrario. ÚSALO CON EXTREMO CUIDADO.'],
            ],
            'Logs' => [
                ['activity_logs / live_connections / client_logs', 'start, limit', 'Actividad de líneas y conexiones.'],
                ['credit_logs / user_logs', 'start, limit', 'Gastos de créditos y logs de resellers.'],
                ['login_logs / system_logs / stream_errors / restream_logs / mag_events / watch_output', 'start, limit', 'Otros logs del sistema.'],
            ],
        ];
    }
}
