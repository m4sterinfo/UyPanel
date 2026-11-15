<?php
require_once MAIN_HOME . 'public/Views/api_docs_common.php';
$rMyKey = ($rUserInfo['api_key'] ?? '');
$rKeyPlaceholder = (0 < strlen($rMyKey) ? $rMyKey : 'TU_API_KEY');
$rFirstPackage = null;
foreach ($rPackages as $rPackage) {
	if ($rPackage['is_line']) {
		$rFirstPackage = $rPackage;
		break;
	}
}
$rPkgID = ($rFirstPackage ? $rFirstPackage['id'] : 1);
$rPkgName = ($rFirstPackage ? $rFirstPackage['package_name'] : '1 Mes');
?>

<div class="wrapper docs-page">
	<div class="container-fluid">
		<div class="row">
			<div class="col-12">
				<div class="page-title-box">
					<h4 class="page-title">Documentación del API</h4>
				</div>
			</div>
		</div>

		<div class="row">
			<div class="col-12">
				<div class="alert alert-info" role="alert">
					Con el API puedes crear y gestionar tus líneas, demos, dispositivos MAG/Enigma2 y sub-resellers desde sistemas externos (bots, web, WHMCS, scripts) con simples peticiones HTTP que devuelven <code>JSON</code>.
				</div>

				<?php if (!$rApiUrl): ?>
					<div class="alert alert-warning" role="alert">
						Tu grupo todavía no tiene un endpoint del API asignado. Contacta con tu proveedor para que habilite un Access Code tipo "Reseller API" para tu grupo.
					</div>
				<?php endif; ?>

				<div class="card">
					<div class="card-body">
						<h5 class="header-title mb-3"><i class="fas fa-key text-info"></i> 1. Genera tu propia API Key</h5>
						<p>El API no usa tu usuario y contraseña del panel: usa una <strong>API Key</strong> personal de 32 caracteres vinculada a tu cuenta.</p>
						<ol>
							<li>Abre el menú de tu usuario (arriba a la derecha) → <a href="edit_profile"><strong>User Profile</strong></a>.</li>
							<li>Busca el campo <strong>API Key</strong>. Si está vacío, pulsa el botón azul <span class="badge badge-info"><i class="mdi mdi-refresh"></i></span> para generar una al azar.</li>
							<li>Pulsa <strong>Save Profile</strong>. Esa key ya autentica tus llamadas.</li>
							<li>Si alguna vez se filtra, pulsa el botón rojo (borrar), guarda, y genera una nueva.</li>
						</ol>

						<?php if ($rApiUrl): ?>
							<p class="mb-1"><strong>Tu endpoint del API:</strong></p>
							<div class="docs-curl"><code><?= htmlspecialchars($rApiUrl); ?></code><?= apiDocsCopyBtn($rApiUrl); ?></div>

							<p class="mt-3 mb-1"><strong>Tu API Key actual:</strong>
							<?php if (0 < strlen($rMyKey)): ?>
								<div class="docs-curl"><code><?= htmlspecialchars($rMyKey); ?></code><?= apiDocsCopyBtn($rMyKey); ?></div>
							<?php else: ?>
								<span class="text-warning">Aún no tienes API Key — genérala en <a href="edit_profile">User Profile</a>.</span>
							<?php endif; ?>
							</p>

							<p class="mt-3 mb-1"><strong>Tu primera llamada (verificar cuenta y créditos):</strong></p>
							<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=user_info"'); ?>
							<p class="mt-2 mb-0 text-muted">También puedes usar POST con los mismos parámetros. El endpoint alternativo es <code><?= htmlspecialchars($rApiUrl); ?>api.php?action=...</code>.</p>
						<?php endif; ?>
					</div>
				</div>

				<div class="card">
					<div class="card-body">
						<h5 class="header-title mb-3"><i class="fas fa-code text-info"></i> 2. Formato de respuesta y errores</h5>
						<p>Todas las respuestas son JSON. En operaciones exitosas recibirás <code>STATUS_SUCCESS</code> y en <code>data</code> la línea/dispositivo completo:</p>
						<div class="docs-curl"><code>{"status":"STATUS_SUCCESS","data":{"id":"8","username":"H4vJSBmbvc","password":"...","exp_date":"1797372374","bouquet":"[1]","max_connections":"1", "...":"..."}}</code></div>
						<p class="mt-3">Si algo falla, <code>status</code> indica el motivo y <code>data</code> devuelve lo que enviaste:</p>
						<?= apiDocsStatusTable(); ?>
					</div>
				</div>

				<div class="card">
					<div class="card-body">
						<h5 class="header-title mb-3"><i class="fas fa-magic text-success"></i> 3. Recetas: lo que más vas a usar</h5>

						<h6 class="mt-3 text-success">Crear un DEMO (prueba gratis)</h6>
						<p class="text-muted mb-1">Usa un paquete con <code>trial=1</code>: el vencimiento sale de la duración trial del paquete y el coste es el precio trial (normalmente 0 créditos).<?php if (!$rCanTrials): ?> <span class="text-warning">Tu grupo no tiene trials habilitados.</span><?php endif; ?></p>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=create_line&package=' . $rPkgID . '&trial=1"'); ?>

						<h6 class="mt-4 text-success">Crear una línea de pago</h6>
						<p class="text-muted mb-1">El paquete define bouquet, outputs, conexiones y duración. Puedes fijar usuario/password (si tu grupo lo permite) o dejar que se generen solos.<?php if ($rCanRestrictions): ?> Opcional: <code>allowed_ips</code>, <code>allowed_ua</code>, <code>is_isplock=1</code>.<?php endif; ?></p>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=create_line&package=' . $rPkgID . '&username=cliente1&password=pass1234&contact=whatsapp%3A%2B59899111222"'); ?>
						<p class="text-muted mb-0">En la respuesta, <code>data.username</code> y <code>data.password</code> son las credenciales que le das a tu cliente.</p>

						<h6 class="mt-4 text-success">Renovar una línea</h6>
						<p class="text-muted mb-1">Pasa el <code>id</code> de la línea y el <code>package</code>: el vencimiento se extiende desde la fecha actual de expiración (o desde hoy si ya venció) y se descuentan los créditos del paquete.</p>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=edit_line&id=8&package=' . $rPkgID . '"'); ?>

						<h6 class="mt-4 text-success">Editar datos de una línea (sin coste)</h6>
						<p class="text-muted mb-1">Sin <code>package</code>, la edición no cuesta créditos: cambia contacto, notas, usuario o password.</p>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=edit_line&id=8&contact=nuevo%40correo.com&reseller_notes=cliente+VIP"'); ?>

						<h6 class="mt-4 text-success">Deshabilitar / habilitar / eliminar</h6>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=disable_line&id=8"'); ?>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=enable_line&id=8"'); ?>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=delete_line&id=8"'); ?>

						<h6 class="mt-4 text-success">Listar tus líneas</h6>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=get_lines&start=0&limit=20"'); ?>

						<h6 class="mt-4 text-success">Ver una línea concreta</h6>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=get_line&id=8"'); ?>

						<h6 class="mt-4 text-success">Crear un MAG o Enigma2</h6>
						<p class="text-muted mb-1">La MAC es obligatoria y única. La línea interna se crea sola (usuario/password de 32 caracteres).</p>
						<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=create_mag&mac=00:1A:79:00:00:01&package=' . $rPkgID . '"'); ?>

						<?php if ($rCanSubResellers): ?>
							<h6 class="mt-4 text-success">Crear una línea para uno de tus sub-resellers</h6>
							<p class="text-muted mb-1">Como super reseller, usa <code>member_id</code> con el ID del sub-reseller (lo ves en <a href="users">Users</a>). Los créditos se descuentan de TU cuenta.</p>
							<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=create_line&package=' . $rPkgID . '&member_id=6"'); ?>

							<h6 class="mt-4 text-success">Crear un sub-reseller</h6>
							<p class="text-muted mb-1">El <code>member_group_id</code> debe ser un grupo permitido para ti. La creación cuesta <code>create_sub_resellers_price</code> créditos de tu grupo.</p>
							<?php $rSubGroupID = (0 < count($rSubGroups) ? end($rSubGroups) : 2); ?>
							<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=create_user&username=subreseller2&password=pass1234&email=sub2%40correo.com&member_group_id=' . $rSubGroupID . '"'); ?>

							<h6 class="mt-4 text-success">Transferir créditos a un sub-reseller</h6>
							<?= apiDocsCurl('curl "' . $rApiUrl . '?api_key=' . $rKeyPlaceholder . '&action=adjust_credits&id=6&credits=10&note=recarga"'); ?>
						<?php endif; ?>
					</div>
				</div>

				<div class="card">
					<div class="card-body">
						<h5 class="header-title mb-3"><i class="fas fa-list text-info"></i> 4. Referencia completa de acciones</h5>
						<p class="text-muted">Parámetros comunes de las listas: <code>start</code> (offset), <code>limit</code> (50 por defecto).</p>
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
						<h5 class="header-title mb-3"><i class="fas fa-tv text-info"></i> 5. URLs para tus clientes</h5>
						<p>Estas URLs NO usan api_key: usan el usuario y password de la línea. Entregaselas a tus clientes según su app:</p>
						<table class="table table-striped table-borderless docs-table">
							<thead><tr><th style="width:35%;">Qué</th><th>URL</th></tr></thead>
							<tbody>
								<tr><td>Lista IPTV (m3u_plus) — la más común</td><td><code><?= htmlspecialchars($rApiUrl ? preg_replace('/\/[^\/]+\/$/', '/', $rApiUrl) : 'http://HOST/'); ?>get.php?username=USUARIO&password=PASSWORD&type=m3u_plus&output=ts</code></td></tr>
								<tr><td>Formato Xtream (apps tipo Xtream)</td><td><code>Servidor: <?= htmlspecialchars($rApiUrl ? preg_replace('/\/[^\/]+\/$/', '/', $rApiUrl) : 'http://HOST/'); ?> · Usuario: USUARIO · Password: PASSWORD</code></td></tr>
								<tr><td>Canal directo TS</td><td><code>.../live/USUARIO/PASSWORD/ID_CANAL.ts</code></td></tr>
								<tr><td>Canal HLS</td><td><code>.../live/USUARIO/PASSWORD/ID_CANAL.m3u8</code></td></tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="card">
					<div class="card-body">
						<h5 class="header-title mb-3"><i class="fas fa-question-circle text-info"></i> 6. Problemas frecuentes</h5>
						<ul class="mb-0">
							<li><strong>"Invalid API key"</strong> — la key no existe o está vacía: genérala en <a href="edit_profile">User Profile</a> y guarda.</li>
							<li><strong>STATUS_INVALID_PACKAGE</strong> — el paquete no está disponible para tu grupo: mira los tuyos con <code>action=packages</code>.</li>
							<li><strong>STATUS_INSUFFICIENT_CREDITS</strong> — no te alcanzan los créditos: pide una recarga a tu proveedor.</li>
							<li><strong>La línea no reproduce</strong> — comprueba <code>data.exp_date</code> (¿vencida?), <code>data.enabled</code> y <code>data.admin_enabled</code> con <code>get_line</code>. Tras crearla, la línea funciona al instante.</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
