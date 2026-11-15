<?php if (!isset($__viewMode)): ?>
<?php include 'session.php'; ?>
<?php include 'functions.php'; ?>

<?php if (!checkPermissions()): ?>
	<?php goHome(); ?>
<?php endif; ?>

<?php $_TITLE = 'API Documentation'; ?>
<?php
require_once __DIR__ . '/../layouts/admin.php';
renderUnifiedLayoutHeader('admin');
?>
<?php endif; ?>

<?php
require_once MAIN_HOME . 'public/Views/api_docs_common.php';
$rMyKey = (isset($rUserInfo) ? ($rUserInfo['api_key'] ?? '') : '');
$rKeyPlaceholder = (0 < strlen($rMyKey) ? $rMyKey : 'TU_API_KEY');
$rApiUrl = ($rApiCodes[0] ?? null);
$rFirstPackage = null;
foreach ($rPackages as $rPackage) {
	if ($rPackage['is_line']) {
		$rFirstPackage = $rPackage;
		break;
	}
}
$rPkgID = ($rFirstPackage ? $rFirstPackage['id'] : 1);
$rOutputList = implode(',', array_map(function ($rOutput) { return $rOutput['access_output_id']; }, $rOutputs));
?>

<div class="wrapper docs-page" <?php if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'): ?> style="display: none;" <?php endif; ?>>
	<div class="container-fluid">
		<div class="row">
			<div class="col-12">
				<div class="page-title-box">
					<div class="page-title-right">
						<?php include 'topbar.php'; ?>
					</div>
					<h4 class="page-title">Documentación del API</h4>
				</div>
			</div>
		</div>

		<div class="row">
			<div class="col-12">
				<div class="alert alert-info" role="alert">
					El API externo permite integrar UyPanel con sistemas externos (facturación, bots, WHMCS, scripts) usando peticiones HTTP GET/POST que devuelven <code>JSON</code>.
					Existen dos APIs: el <strong>Admin API</strong> (control total del panel) y el <strong>Reseller API</strong> (solo las líneas y sub-resellers propios).
				</div>

				<?php if (0 >= count($rApiCodes)): ?>
					<div class="alert alert-warning" role="alert">
						No hay ningún <strong>Access Code tipo "Admin API"</strong> habilitado. Créalo en <a href="codes">Access Codes</a> (tipo Admin API, grupo Administrators) para obtener la URL del endpoint.
					</div>
				<?php endif; ?>

				<div class="card">
					<div class="card-body">
						<h5 class="header-title mb-3"><i class="fas fa-key text-info"></i> Autenticación y tu API Key</h5>
						<p>El API <strong>no</strong> usa tu usuario ni contraseña del panel: usa una <strong>API Key</strong> individual de 32 caracteres asociada a tu cuenta de usuario.</p>
						<ol>
							<li>Ve a <a href="edit_profile">Edit Profile</a> (menú de tu usuario, arriba a la derecha).</li>
							<li>En el campo <strong>API Key</strong> pulsa el botón <span class="badge badge-info"><i class="mdi mdi-refresh"></i></span> (generar). La key se genera al azar (32 caracteres hexadecimales).</li>
							<li>Pulsa <strong>Save Profile</strong>. A partir de ese momento esa key autentica tus llamadas al API.</li>
							<li>Para invalidar una key filtrada, pulsa el botón rojo (limpiar), guarda, y genera una nueva.</li>
						</ol>

						<?php if (0 < count($rApiCodes)): ?>
							<p class="mb-1"><strong>Endpoints del Admin API:</strong></p>
							<?php foreach ($rApiCodes as $rEndpoint): ?>
								<div class="docs-curl"><code><?= htmlspecialchars($rEndpoint); ?></code><?= apiDocsCopyBtn($rEndpoint); ?></div>
							<?php endforeach; ?>
							<?php if ($rResellerApiUrl): ?>
								<p class="mb-1 mt-3"><strong>Endpoint del Reseller API</strong> (para tus resellers):</p>
								<div class="docs-curl"><code><?= htmlspecialchars($rResellerApiUrl); ?></code><?= apiDocsCopyBtn($rResellerApiUrl); ?></div>
							<?php endif; ?>
							<p class="mt-3 mb-1"><strong>Tu API Key actual:</strong>
								<?php if (0 < strlen($rMyKey)): ?>
									<div class="docs-curl"><code><?= htmlspecialchars($rMyKey); ?></code><?= apiDocsCopyBtn($rMyKey); ?></div>
								<?php else: ?>
									<span class="text-warning">Todavía no tienes una API Key generada — créala en <a href="edit_profile">Edit Profile</a>.</span>
								<?php endif; ?>
							</p>
							<p class="mt-2 mb-0"><strong>URL completa lista para usar:</strong></p>
							<?php foreach ($rApiCodes as $rEndpoint): ?>
								<div class="docs-curl"><code><?= htmlspecialchars($rEndpoint); ?>?api_key=<?= htmlspecialchars($rKeyPlaceholder); ?>&action=user_info</code><?= apiDocsCopyBtn($rEndpoint . '?api_key=' . $rKeyPlaceholder . '&action=user_info'); ?></div>
							<?php endforeach; ?>
							<p class="mt-2 mb-0 text-muted">La key viaja en el parámetro <code>api_key</code> (GET o POST). El endpoint también funciona como <code>.../api.php?action=...</code> (p. ej. <code><?= htmlspecialchars($rApiCodes[0] ?? ''); ?>api.php?action=user_info</code>).</p>
						<?php endif; ?>
					</div>
				</div>

				<div class="card">
					<div class="card-body">
						<h5 class="header-title mb-3"><i class="fas fa-code text-info"></i> Formato de respuesta y errores</h5>
						<p>Todas las respuestas son JSON con un campo <code>status</code> y, en operaciones de escritura, <code>data</code>:</p>
						<div class="docs-curl"><code>{"status":"STATUS_SUCCESS","data":{"insert_id":123}}</code></div>
						<p class="mt-3">En <code>get_line</code>, <code>create_line</code> y <code>edit_line</code>, <code>data</code> contiene la línea completa ya creada/actualizada. En errores, <code>data</code> devuelve los datos recibidos para depurar.</p>
						<?= apiDocsStatusTable(); ?>
					</div>
				</div>

				<div class="card">
					<div class="card-body">
						<h5 class="header-title mb-3"><i class="fas fa-list text-info"></i> Referencia de acciones (Admin API)</h5>
						<p class="text-muted">Parámetros comunes de las listas: <code>start</code> (offset, 0 por defecto), <code>limit</code> (50 por defecto), <code>show_columns</code> / <code>hide_columns</code> (nombres de columna separados por coma).</p>
						<?php foreach ($rActions as $rCategory => $rCategoryActions): ?>
							<h6 class="mt-4 mb-2 text-primary"><?= htmlspecialchars($rCategory); ?></h6>
							<table class="table table-striped table-borderless docs-table">
								<thead><tr><th style="width:30%;">Acción</th><th style="width:30%;">Parámetros</th><th>Descripción</th></tr></thead>
								<tbody>
									<?php foreach ($rCategoryActions as $rAction): ?>
										<tr>
											<td><code><?= htmlspecialchars($rAction[0]); ?></code></td>
											<td><code class="text-muted"><?= htmlspecialchars($rAction[1]); ?></code></td>
											<td><?= $rAction[2]; ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="card">
					<div class="card-body">
						<h5 class="header-title mb-3"><i class="fas fa-terminal text-info"></i> Ejemplos prácticos (curl)</h5>
						<?php if (!$rApiUrl): ?>
							<div class="alert alert-warning mb-0" role="alert">Crea un Access Code tipo "Admin API" para ver los ejemplos con tu endpoint real.</div>
						<?php else: ?>

						<h6 class="mt-3">1. Verificar tu cuenta</h6>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=user_info"'); ?>

						<h6 class="mt-4">2. Crear una línea para un reseller (campos crudos)</h6>
						<p class="text-muted mb-1">El Admin API crea líneas con campos directos (no aplica paquetes). <code>bouquets_selected</code> es obligatorio; <code>access_output</code> si se omite usa todos los outputs (<?= htmlspecialchars($rOutputList); ?>).</p>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=create_line&member_id=4&bouquets_selected=1&access_output=' . $rOutputList . '&max_connections=2&exp_date=2027-01-01&username=cliente1&password=pass1234"'); ?>

						<h6 class="mt-4">3. Renovar una línea (extender expiración)</h6>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=edit_line&id=8&exp_date=2027-06-01"'); ?>

						<h6 class="mt-4">4. Ban / unban / deshabilitar / eliminar</h6>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=ban_line&id=8"'); ?>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=unban_line&id=8"'); ?>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=disable_line&id=8"'); ?>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=delete_line&id=8"'); ?>

						<h6 class="mt-4">5. Ajustar créditos de un reseller</h6>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=adjust_credits&id=4&credits=50&reason=recarga"'); ?>

						<h6 class="mt-4">6. Listar líneas y filtrar columnas</h6>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=get_lines&start=0&limit=20&show_columns=id,username,exp_date,max_connections"'); ?>

						<h6 class="mt-4">7. Estado del servidor</h6>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=get_server_stats"'); ?>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=get_free_space"'); ?>

						<h6 class="mt-4">8. Consulta SQL directa (¡cuidado!)</h6>
						<p class="text-muted mb-1">Solo lectura recomendado. Un UPDATE/DELETE mal escrito modifica la base de datos real.</p>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=mysql_query&query=SELECT+id,username+FROM+%60lines%60+LIMIT+5"'); ?>
						<?php endif; ?>
					</div>
				</div>

				<div class="card">
					<div class="card-body">
						<h5 class="header-title mb-3"><i class="fas fa-tv text-info"></i> Endpoints de reproducción (para los clientes)</h5>
						<p>Estos endpoints NO usan api_key: usan el usuario y password de la línea.</p>
						<table class="table table-striped table-borderless docs-table">
							<thead><tr><th style="width:35%;">Qué</th><th>URL</th></tr></thead>
							<tbody>
								<tr><td>Lista completa (m3u_plus)</td><td><code><?= htmlspecialchars($rServers[SERVER_ID]['site_url'] ?? 'http://HOST/'); ?>get.php?username=USUARIO&password=PASSWORD&type=m3u_plus&output=ts</code></td></tr>
								<tr><td>Datos del usuario (Xtream API)</td><td><code><?= htmlspecialchars($rServers[SERVER_ID]['site_url'] ?? 'http://HOST/'); ?>player_api.php?username=USUARIO&password=PASSWORD</code></td></tr>
								<tr><td>Canal directo TS</td><td><code>.../live/USUARIO/PASSWORD/ID_CANAL.ts</code></td></tr>
								<tr><td>Canal HLS</td><td><code>.../live/USUARIO/PASSWORD/ID_CANAL.m3u8</code></td></tr>
								<tr><td>Película / serie</td><td><code>.../movie/USUARIO/PASSWORD/ID.mp4</code> · <code>.../series/USUARIO/PASSWORD/ID.mp4</code></td></tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="card">
					<div class="card-body">
						<h5 class="header-title mb-3"><i class="fas fa-shield-alt text-warning"></i> Notas de seguridad</h5>
						<ul class="mb-0">
							<li>La <code>api_key</code> equivale a tu cuenta: <strong>no la publiques</strong> ni la incrustes en código cliente. Regenerala si se filtra.</li>
							<li>Los Access Codes tipo "Admin API"/"Reseller API" definen la <em>URL</em> del endpoint (y su whitelist de IPs opcional); la <code>api_key</code> define <em>quién</em> puede usarlo.</li>
							<li>El endpoint rechaza peticiones sin key válida con <code>{"status":"STATUS_FAILURE","error":"Invalid API key."}</code>.</li>
							<li>Para dar acceso al API a un reseller nuevo: asegúrate de que exista un Access Code tipo "Reseller API" y que el reseller genere su key en su Edit Profile.</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
renderUnifiedLayoutFooter('admin');
?>
<script id="scripts">
	$(document).ready(function() {
		setTimeout(pingSession, 30000);
		refreshTooltips();
		$(".copy-btn").click(function() {
			var rText = $(this).data("copy");
			var rBtn = $(this);
			if (navigator.clipboard) {
				navigator.clipboard.writeText(rText).then(function() {
					rBtn.addClass("btn-success");
					setTimeout(function() { rBtn.removeClass("btn-success"); }, 800);
				});
			} else {
				var rInput = $("<textarea>").val(rText).appendTo("body").select();
				document.execCommand("copy");
				rInput.remove();
				rBtn.addClass("btn-success");
				setTimeout(function() { rBtn.removeClass("btn-success"); }, 800);
			}
		});
	});
</script>
</body>

</html>
