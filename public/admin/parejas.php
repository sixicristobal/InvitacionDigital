<?php
require_once __DIR__ . '/../../includes/functions.php';
requiereLogin();
$pdo = pdo();
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['accion']) && $_POST['accion']==='toggle') {
    $id=(int)$_POST['id_pareja'];
    $pdo->prepare('UPDATE parejas SET activo=IF(activo=1,0,1) WHERE id_pareja=?')->execute([$id]);
    flash('Estado actualizado.');
    redirigir(ADMIN_URL.'/parejas.php');
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nombre = trim($_POST['nombre_pareja'] ?? '');
    $novio = trim($_POST['novio'] ?? '');
    $novia = trim($_POST['novia'] ?? '');
    $fecha = $_POST['fecha_boda'] ?? '';
    $lugar = trim($_POST['lugar_boda'] ?? '');
    $hora = $_POST['hora_boda'] ?? null;
    // Nuevos campos
    $banco_alias = trim($_POST['banco_alias'] ?? '');
    $banco_nombre = trim($_POST['banco_nombre'] ?? '');
    $banco_cuenta = trim($_POST['banco_cuenta'] ?? '');
    $banco_titular = trim($_POST['banco_titular'] ?? '');
    $color_damas = $_POST['color_damas'] ?? '#B85323';
    $estilo = $_POST['estilo'] ?? 'moderno-organico';
    $color_fondo = $_POST['color_fondo'] ?? '#FDFBF7';
    $color_secundario = $_POST['color_secundario'] ?? '#8A9A86';
    $color_acento = $_POST['color_acento'] ?? '#B76E79';
    $msg = trim($_POST['mensaje_invitacion'] ?? '');
    $token = generarToken();
    $st = $pdo->prepare('INSERT INTO parejas (nombre_pareja,novio,novia,fecha_boda,lugar_boda,hora_boda,mensaje_invitacion,token_acceso,banco_alias,banco_nombre,banco_cuenta,banco_titular,color_damas,estilo,color_fondo,color_secundario,color_acento) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([$nombre,$novio,$novia,$fecha,$lugar,$hora,$msg,$token,$banco_alias,$banco_nombre,$banco_cuenta,$banco_titular,$color_damas,$estilo,$color_fondo,$color_secundario,$color_acento]);
    flash('Pareja agregada correctamente.');
    redirigir(ADMIN_URL.'/parejas.php');
}
$parejas = $pdo->query('SELECT * FROM parejas ORDER BY id_pareja')->fetchAll();
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Parejas</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"></head><body>
<div class="layout"><?php include __DIR__.'/../../includes/sidebar.php'; ?>
<main class="content">
<h2>Gestión de Parejas</h2>
<form method="post" class="form-card">
    <h3>Nueva pareja</h3>
    <input name="nombre_pareja" placeholder="Nombre de la pareja" required>
    <input name="novio" placeholder="Novio" required>
    <input name="novia" placeholder="Novia" required>
    <input type="date" name="fecha_boda" required>
    <input name="lugar_boda" placeholder="Lugar de la boda">
    <input type="time" name="hora_boda">
    <textarea name="mensaje_invitacion" placeholder="Mensaje de invitación"></textarea>
    <!-- Nuevos campos configurables -->
    <input name="banco_alias" placeholder="Alias del banco" required>
    <input name="banco_nombre" placeholder="Nombre del banco" required>
    <input name="banco_cuenta" placeholder="Número de cuenta" required>
    <input name="banco_titular" placeholder="Titular de la cuenta" required>
    <label>Color damas de honor</label>
    <input type="color" name="color_damas" value="#B85323" required>
    <label>Estilo</label>
    <select name="estilo"><option value="moderno-organico">Moderno Orgánico (Eucalipto & Sage)</option></select>
    <label>Color fondo</label><input type="color" name="color_fondo" value="#FDFBF7">
    <label>Color secundario</label><input type="color" name="color_secundario" value="#8A9A86">
    <label>Color acento</label><input type="color" name="color_acento" value="#B76E79">
    <button type="submit">Agregar pareja</button>
</form>
<table class="table">
<tr><th>ID</th><th>Pareja</th><th>Novios</th><th>Fecha</th><th>Lugar</th><th>Estado</th><th>Token/Link</th><th>Acción</th></tr>
<?php foreach($parejas as $p): ?>
<tr>
<td><?= $p['id_pareja'] ?></td>
<td><?= h($p['nombre_pareja']) ?></td>
<td><?= h($p['novio']) ?> & <?= h($p['novia']) ?></td>
<td><?= h($p['fecha_boda']) ?></td>
<td><?= h($p['lugar_boda']) ?></td>
<td><?= $p['activo']?'Activa':'Inactiva' ?></td>
<td><a href="<?= PUBLIC_URL ?>/invitacion.php?token=<?= $p['token_acceso'] ?>" target="_blank">Ver</a></td>
<td><form method="post" style="display:inline"><input type="hidden" name="accion" value="toggle"><input type="hidden" name="id_pareja" value="<?= $p['id_pareja'] ?>"><button type="submit"><?= $p['activo']?'Desactivar':'Activar' ?></button></form></td>
</tr>
<?php endforeach; ?>
</table>
</main></div></body></html>