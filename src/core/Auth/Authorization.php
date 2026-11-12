<?php

class Authorization {
	/**
	 * Permisos 'adv' reservados al Super Admin: gestion del server (ajustes,
	 * servidores/LBs, stream providers, base de datos, herramientas, RTMP)
	 * y gestion de grupos/admins. Un admin normal no los ve ni en el menu.
	 */
	const SUPER_ADMIN_ONLY_KEYS = array(
		'settings',
		'servers',
		'add_server',
		'edit_server',
		'database',
		'quick_tools',
		'process_monitor',
		'rtmp',
		'add_rtmp',
		'stream_providers',
		'mng_groups',
		'add_group',
		'edit_group'
	);

	/** @var array Cache por request: group_id => 0|1 */
	private static $superAdminCache = array();

	/** @var array Cache por request: user id => array de IDs visibles */
	private static $visibleCache = array();

	public static function isSuperAdmin($rUserInfo = null) {
		if ($rUserInfo === null) {
			$rUserInfo = isset($GLOBALS['rUserInfo']) ? $GLOBALS['rUserInfo'] : null;
		}

		if (!$rUserInfo || empty($rUserInfo['member_group_id'])) {
			return false;
		}

		$rGroupID = intval($rUserInfo['member_group_id']);

		if (!isset(self::$superAdminCache[$rGroupID])) {
			global $db;
			self::$superAdminCache[$rGroupID] = 0;

			if ($db) {
				$db->query('SELECT `is_super_admin` FROM `users_groups` WHERE `group_id` = ?;', $rGroupID);

				if ($db->num_rows() == 1) {
					self::$superAdminCache[$rGroupID] = intval($db->get_row()['is_super_admin']);
				}
			}
		}

		return self::$superAdminCache[$rGroupID] == 1;
	}

	/**
	 * IDs de usuarios visibles para el usuario actual:
	 *   - Super Admin  => NULL (sin filtro, ve todo el panel)
	 *   - Admin/Reseller => array(self + todo el arbol de sub-usuarios)
	 *   - Sin sesion    => array() (no ve nada)
	 */
	public static function getVisibleUserIds($rUserInfo = null) {
		if ($rUserInfo === null) {
			$rUserInfo = isset($GLOBALS['rUserInfo']) ? $GLOBALS['rUserInfo'] : null;
		}

		if (!$rUserInfo || empty($rUserInfo['id'])) {
			return array();
		}

		if (self::isSuperAdmin($rUserInfo)) {
			return null;
		}

		$rCacheKey = 'u' . intval($rUserInfo['id']);

		if (isset(self::$visibleCache[$rCacheKey])) {
			return self::$visibleCache[$rCacheKey];
		}

		global $db;
		$rReturn = array(intval($rUserInfo['id']));

		if ($db) {
			$db->query("WITH RECURSIVE `subtree` AS (
				SELECT `id` FROM `users` WHERE `id` = ?
				UNION ALL
				SELECT `u`.`id` FROM `users` `u` INNER JOIN `subtree` `s` ON `u`.`owner_id` = `s`.`id`
			) SELECT `id` FROM `subtree` WHERE `id` != ?;", intval($rUserInfo['id']), intval($rUserInfo['id']));

			foreach ($db->get_rows() as $rRow) {
				$rReturn[] = intval($rRow['id']);
			}
		}

		self::$visibleCache[$rCacheKey] = $rReturn;
		return $rReturn;
	}

	/**
	 * Fragmento SQL "columna IN (visibles)" para filtrar listados por arbol.
	 * Devuelve NULL si el usuario es Super Admin (no filtrar) o '' si no hay sesion.
	 */
	public static function getVisibleSQL($rColumn) {
		$rVisible = self::getVisibleUserIds();

		if ($rVisible === null) {
			return null;
		}

		if (0 >= count($rVisible)) {
			return '1=0';
		}

		return $rColumn . ' IN (' . implode(',', array_map('intval', $rVisible)) . ')';
	}

	/**
	 * Permiso reseller por clave (p. ej. 'create_line', 'create_sub_resellers').
	 * Lo usa el topbar del panel reseller para mostrar/ocultar acciones.
	 */
	public static function hasResellerPermissions($rType) {
		global $rPermissions;

		if (!is_array($rPermissions)) {
			return false;
		}

		return !empty($rPermissions[$rType]);
	}

	public static function check($rType, $rID) {
		global $rUserInfo;
		global $db;
		global $rPermissions;
		if (!(isset($rUserInfo) && isset($rPermissions) && is_array($rPermissions) && $db)) {
			return false;
		}

		if ($rType == 'user') {
			$rVisible = self::getVisibleUserIds($rUserInfo);

			if ($rVisible === null) {
				return true;
			}

			if (0 < count($rVisible)) {
				$db->query('SELECT `id` FROM `users` WHERE `id` = ? AND `id` IN (' . implode(',', $rVisible) . ');', $rID);
				return 0 < $db->num_rows();
			}

			return false;
		}

		if ($rType == 'line') {
			$rVisible = self::getVisibleUserIds($rUserInfo);

			if ($rVisible === null) {
				return true;
			}

			if (0 < count($rVisible)) {
				$db->query('SELECT `id` FROM `lines` WHERE `id` = ? AND `member_id` IN (' . implode(',', $rVisible) . ');', $rID);
				return 0 < $db->num_rows();
			}

			return false;
		}

		if (!($rType == 'adv' && $rPermissions['is_admin'])) {
			return false;
		}

		if (in_array($rID, self::SUPER_ADMIN_ONLY_KEYS)) {
			return self::isSuperAdmin($rUserInfo);
		}

		if (self::isSuperAdmin($rUserInfo)) {
			return true;
		}

		// Multi-tenant: un grupo admin sin paginas asignadas NO hereda todo (denegar por defecto)
		if (!isset($rPermissions['advanced']) || !is_array($rPermissions['advanced']) || 0 >= count($rPermissions['advanced'])) {
			return false;
		}

		return in_array($rID, $rPermissions['advanced']);
	}
}
