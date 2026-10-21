<?php
require_once __DIR__ . '/../includes/functions.php';
requiereLogin();
$pdo = pdo();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nombre = trim($_POST['nombre_pareja'] ?? '');
    $novio = trim($_POST['novio'] ?? '');
    $novia = trim($_POST['novia'] ?? '');
    $fecha = $_POST['fecha_boda'] ?? '';
    $lugar = trim($_POST['lugar_boda'] ?? '');
    $hora = $_POST['hora_boda'] ?? null;
    $msg = trim($_POST['mensaje_invitacion'] ?? '');
    $token = generarToken();
    $st = $pdo->prepare('INSERT INTO parejas (nombre_pareja,novio,novia,fecha_boda,lugar_boda,hora_boda,mensaje_invitacion,token_acceso) VALUES (?,?,?,?,?,?,?,?)');
    $st->execute([$nombre,$novio,$novia,$fecha,$lugar,$hora,$msg,$token]);
    flash('Pareja agregada correctamente.');
    redirigir(ADMIN_URL.'/parejas.php');
}
$parejas = $pdo->query('SELECT * FROM parejas ORDER BY id_pareja')->fetchAll();
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Parejas</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"></head><body>
<div class="layout"><?php include __DIR__.'/../includes/sidebar.php'; ?>
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
    <button type="submit">Agregar pareja</button>
</form>
<table class="table">
<tr><th>ID</th><th>Pareja</th><th>Novios</th><th>Fecha</th><th>Lugar</th><th>Token/Link</th></tr>
<?php foreach($parejas as $p): ?>
<tr>
<td><?= $p['id_pareja'] ?></td>
<td><?= h($p['nombre_pareja']) ?></td>
<td><?= h($p['novio']) ?> & <?= h($p['novia']) ?></td>
<td><?= h($p['fecha_boda']) ?></td>
<td><?= h($p['lugar_boda']) ?></td>
<td><a href="<?= PUBLIC_URL ?>/invitacion.php?token=<?= $p['token_acceso'] ?>" target="_blank">Ver</a></td>
</tr>
<?php endforeach; ?>
</table>
</main></div></body></html>
PHP
echo done