<?php
require_once __DIR__ . '/../includes/functions.php';
$pdo = pdo();
$token = $_GET['token'] ?? '';
$codigo = $_GET['codigo'] ?? '';
$invitado = null; $pareja = null;
if ($codigo) {
    $st = $pdo->prepare('SELECT i.*, p.* FROM invitados i JOIN parejas p ON i.id_pareja=p.id_pareja WHERE i.codigo_invitacion=?');
    $st->execute([$codigo]); $invitado = $st->fetch();
    if ($invitado) $pareja = $invitado;
}
if (!$invitado && $token) {
    $st = $pdo->prepare('SELECT * FROM parejas WHERE token_acceso=?');
    $st->execute([$token]); $pareja = $st->fetch();
}
if (!$pareja) { die('Invitación no encontrada.'); }

$mensaje = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && $invitado) {
    $estado = $_POST['estado'] ?? 'confirmado';
    $cant = (int)($_POST['cantidad'] ?? $invitado['cantidad_personas']);
    $msg = trim($_POST['mensaje'] ?? '');
    $pdo->prepare('UPDATE invitados SET estado=?, cantidad_personas=?, mensaje=?, fecha_confirmacion=NOW() WHERE id_invitado=?')
        ->execute([$estado,$cant,$msg,$invitado['id_invitado']]);
    $mensaje = '¡Gracias por confirmar!';
    // recargar
    $st = $pdo->prepare('SELECT i.*, p.* FROM invitados i JOIN parejas p ON i.id_pareja=p.id_pareja WHERE i.codigo_invitacion=?');
    $st->execute([$codigo]); $invitado = $st->fetch();
}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<title>Invitación - <?= h($pareja['nombre_pareja']) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"></head>
<body class="invite-body">
<div class="invite-card">
    <h1>💍 <?= h($pareja['nombre_pareja']) ?></h1>
    <p class="sub"><?= h($pareja['novio']) ?> &amp; <?= h($pareja['novia']) ?></p>
    <p>📅 <?= h($pareja['fecha_boda']) ?> · 🕐 <?= h($pareja['hora_boda']) ?></p>
    <p>📍 <?= h($pareja['lugar_boda']) ?></p>
    <p class="msg"><?= nl2br(h($pareja['mensaje_invitacion'])) ?></p>
    <?php if($mensaje): ?><div class="alert success"><?= h($mensaje) ?></div><?php endif; ?>
    <?php if($invitado): ?>
        <h3>Hola, <?= h($invitado['nombre_completo']) ?></h3>
        <p>Estado actual: <strong><?= h($invitado['estado']) ?></strong></p>
        <form method="post">
            <label>Asistirás?</label>
            <select name="estado">
                <option value="confirmado">Sí, asistiré</option>
                <option value="rechazado">No podré asistir</option>
            </select>
            <label>Cantidad de personas</label>
            <input type="number" name="cantidad" value="<?= (int)$invitado['cantidad_personas'] ?>" min="1">
            <label>Mensaje (opcional)</label>
            <textarea name="mensaje"></textarea>
            <button type="submit">Confirmar asistencia</button>
        </form>
    <?php else: ?>
        <p>Invitación general de la pareja. Si recibiste un link personal, úsalo para confirmar.</p>
    <?php endif; ?>
</div>
</body></html>