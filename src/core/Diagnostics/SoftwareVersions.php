<?php

/**
 * SoftwareVersions — inventario de versiones instaladas y detección de
 * software desactualizado para la pestaña Info de Settings.
 *
 * El manifest de últimas versiones vive en el repo público UyPanel_Update
 * (versions.json — mismo mecanismo que changelog.json / api_server.json),
 * con caché local de 24h y fallback al manifest incluido en el payload
 * (config/software_versions.json) si GitHub no está accesible.
 *
 * Uso:
 *   $rReport = SoftwareVersions::getReport($rSettings, $db);
 *   // ['components' => [...], 'outdated' => [...], 'eol' => [...]]
 */
class SoftwareVersions {

	const MANIFEST_URL = 'https://raw.githubusercontent.com/m4sterinfo/UyPanel_Update/refs/heads/main/versions.json';
	const CACHE_TTL = 86400; // 24 horas
	const FETCH_TIMEOUT = 4;

	/** @var string|null Caché del manifest por petición */
	private static $manifest = null;

	// ───────────────────────────────────────────────────────────
	//  API pública
	// ───────────────────────────────────────────────────────────

	/**
	 * Informe completo de versiones: instalada vs última conocida.
	 *
	 * @param array $rSettings Settings del panel (getSettings())
	 * @param object|null $db Handler de base de datos (para MariaDB VERSION())
	 * @return array ['components' => [...], 'outdated' => string[], 'eol' => string[], 'manifest_source' => string]
	 */
	public static function getReport(array $rSettings, $db = null): array {
		$rInstalled = self::getInstalled($rSettings, $db);
		$rManifest = self::getManifest();

		$rComponents = [];
		foreach ($rInstalled as $rKey => $rItem) {
			$rEntry = $rManifest[$rKey] ?? null;
			$rLatest = is_array($rEntry) ? ($rEntry['latest'] ?? null) : null;
			$rStatus = 'unknown';

			if ($rItem['version'] === null) {
				$rStatus = 'unknown';
			} elseif ($rEntry !== null && !empty($rEntry['eol'])) {
				// El producto está EOL aunque esté en su última versión (ej. KeyDB)
				$rStatus = 'eol';
			} elseif ($rLatest === null) {
				$rStatus = 'unknown';
			} elseif (self::isNewer($rLatest, $rItem['version'])) {
				$rStatus = 'outdated';
			} else {
				$rStatus = 'ok';
			}

			$rComponents[] = [
				'key' => $rKey,
				'label' => $rItem['label'],
				'installed' => $rItem['version'],
				'latest' => $rLatest,
				'status' => $rStatus,
				'notes' => is_array($rEntry) ? ($rEntry['notes'] ?? null) : null,
				'hint' => is_array($rEntry) ? ($rEntry['hint'] ?? null) : null,
			];
		}

		return [
			'components' => $rComponents,
			'outdated' => array_values(array_map(fn($c) => $c['key'], array_filter($rComponents, fn($c) => $c['status'] === 'outdated'))),
			'eol' => array_values(array_map(fn($c) => $c['key'], array_filter($rComponents, fn($c) => $c['status'] === 'eol'))),
			'manifest_source' => self::$manifestSource,
		];
	}

	/**
	 * Versiones instaladas de cada componente del panel.
	 *
	 * @param array $rSettings Settings del panel
	 * @param object|null $db Handler de base de datos
	 * @return array key => ['label' => string, 'version' => string|null]
	 */
	public static function getInstalled(array $rSettings, $db = null): array {
		$rInstalled = [];

		// Nginx / Nginx RTMP (binarios compilados del panel)
		$rInstalled['nginx'] = [
			'label' => 'Nginx',
			'version' => self::parseVersion(shell_exec(BIN_PATH . 'nginx/sbin/nginx -v 2>&1')),
		];
		$rInstalled['nginx_rtmp'] = [
			'label' => 'Nginx RTMP',
			'version' => self::parseVersion(shell_exec(BIN_PATH . 'nginx_rtmp/sbin/nginx_rtmp -v 2>&1')),
		];

		// PHP y extensión phpredis
		$rInstalled['php'] = ['label' => 'PHP', 'version' => PHP_VERSION];
		$rExtRedis = phpversion('redis');
		$rInstalled['phpredis'] = ['label' => 'phpredis (extensión)', 'version' => $rExtRedis !== false ? $rExtRedis : null];

		// MariaDB
		$rMariaDB = null;
		try {
			if ($db) {
				$db->query('SELECT VERSION() AS ver;');
				$rRow = $db->get_row();
				if (!empty($rRow['ver'])) {
					$rMariaDB = $rRow['ver'];
				}
			}
		} catch (Throwable $e) {}
		$rInstalled['mariadb'] = ['label' => 'MariaDB', 'version' => self::parseVersion($rMariaDB)];

		// Servidor de caché (Valkey / KeyDB / Redis) vía INFO server
		$rInstalled['cache'] = ['label' => 'Cache Server', 'version' => self::getCacheVersion($rSettings)];

		// FFmpeg activo (configurable en Settings → Streaming)
		$rInstalled['ffmpeg'] = [
			'label' => 'FFmpeg (activo)',
			'version' => self::parseVersion($rSettings['ffmpeg_cpu'] ?? null),
		];

		return $rInstalled;
	}

	// ───────────────────────────────────────────────────────────
	//  Manifest (remoto con caché + fallback incluido)
	// ───────────────────────────────────────────────────────────

	private static $manifestSource = 'none';

	private static function getManifest(): array {
		if (self::$manifest !== null) {
			return self::$manifest;
		}

		$rCacheFile = TMP_PATH . 'software_versions_cache.json';

		// 1. Caché válida
		if (file_exists($rCacheFile) && (time() - (int) filemtime($rCacheFile)) < self::CACHE_TTL) {
			$rData = self::readJSON($rCacheFile);
			if ($rData !== null) {
				self::$manifest = $rData;
				self::$manifestSource = 'cache';
				return self::$manifest;
			}
		}

		// 2. Manifest remoto (raw.githubusercontent, timeout corto)
		$rRemote = self::fetchRemote();
		if ($rRemote !== null) {
			self::$manifest = $rRemote;
			self::$manifestSource = 'remote';
			@file_put_contents($rCacheFile, json_encode($rRemote), LOCK_EX);
			return self::$manifest;
		}

		// 3. Caché vencida (stale) mejor que nada
		if (file_exists($rCacheFile)) {
			$rData = self::readJSON($rCacheFile);
			if ($rData !== null) {
				self::$manifest = $rData;
				self::$manifestSource = 'cache-stale';
				return self::$manifest;
			}
		}

		// 4. Manifest incluido en el payload
		$rBundled = self::readJSON(MAIN_HOME . 'config/software_versions.json');
		self::$manifest = is_array($rBundled) ? $rBundled : [];
		self::$manifestSource = 'bundled';
		return self::$manifest;
	}

	private static function fetchRemote(): ?array {
		try {
			$ch = curl_init(self::MANIFEST_URL);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, self::FETCH_TIMEOUT);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::FETCH_TIMEOUT);
			curl_setopt($ch, CURLOPT_USERAGENT, 'm4sterinfo/UyPanel');
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			$rResponse = curl_exec($ch);
			$iCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($rResponse === false || $iCode !== 200) {
				return null;
			}

			$rData = json_decode($rResponse, true);
			return is_array($rData) ? $rData : null;
		} catch (Throwable $e) {
			return null;
		}
	}

	private static function readJSON(string $rPath): ?array {
		$rContent = @file_get_contents($rPath);
		if ($rContent === false) {
			return null;
		}
		$rData = json_decode($rContent, true);
		return is_array($rData) ? $rData : null;
	}

	// ───────────────────────────────────────────────────────────
	//  Helpers de versión
	// ───────────────────────────────────────────────────────────

	/**
	 * Extrae el primer token de versión (X.Y.Z) de un string crudo.
	 * Ej: "nginx version: nginx/1.29.6" → "1.29.6"; "11.8.9-MariaDB" → "11.8.9".
	 */
	private static function parseVersion($rRaw): ?string {
		if ($rRaw === null || $rRaw === false || $rRaw === '') {
			return null;
		}
		if (preg_match('/[0-9]+(\.[0-9]+)+/', (string) $rRaw, $rMatch)) {
			return $rMatch[0];
		}
		return null;
	}

	/**
	 * Versión del servidor de caché desde INFO server.
	 * Soporta Valkey (valkey_version), KeyDB (keydb_version) y Redis (redis_version).
	 */
	private static function getCacheVersion(array $rSettings): ?string {
		try {
			$redis = new Redis();
			if (!$redis->connect('127.0.0.1', 6379, 1.0)) {
				return null;
			}
			$rPass = $rSettings['redis_password'] ?? '';
			if (!empty($rPass)) {
				$redis->auth($rPass);
			}
			$rInfo = $redis->info('server');
			foreach (['valkey_version', 'keydb_version', 'redis_version'] as $rField) {
				if (!empty($rInfo[$rField])) {
					return $rInfo[$rField];
				}
			}
			return null;
		} catch (Throwable $e) {
			return null;
		}
	}

	/**
	 * Comparación semántica: TRUE si $rCandidate es estrictamente mayor que $rCurrent.
	 */
	private static function isNewer(string $rCandidate, string $rCurrent): bool {
		// version_compare entiende X.Y.Z; normaliza sufijos tipo -MariaDB
		$rCandidate = preg_replace('/[^0-9.].*$/', '', trim($rCandidate));
		$rCurrent = preg_replace('/[^0-9.].*$/', '', trim($rCurrent));
		if ($rCandidate === '' || $rCurrent === '') {
			return false;
		}
		return version_compare($rCandidate, $rCurrent, '>');
	}
}
