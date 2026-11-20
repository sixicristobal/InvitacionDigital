<?php
require_once __DIR__ . '/../../includes/functions.php';
requiereLogin();
$parejas = pdo()->query('SELECT * FROM parejas WHERE activo=1 ORDER BY id_pareja')->fetchAll();
$stats = [];
foreach ($parejas as $p) {
    $st = pdo()->prepare('SELECT estado, COUNT(*) c FROM invitados WHERE id_pareja=? GROUP BY estado');
    $st->execute([$p['id_pareja']]);
    $row = ['pendiente'=>0,'confirmado'=>0,'rechazado'=>0,'total'=>0];
    foreach ($st->fetchAll() as $r){ $row[$r['estado']]=$r['c']; $row['total']+=$r['c']; }
    $stats[$p['id_pareja']] = $row;
}
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard - <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include __DIR__.'/../../includes/sidebar.php'; ?>
<main class="content">
<h2>Dashboard</h2>
<?php if($flash): ?><div class="alert <?= $flash['tipo'] ?>"><?= h($flash['msg']) ?></div><?php endif; ?>
<div class="cards">
<?php foreach($parejas as $p): $s=$stats[$p['id_pareja']]; ?>
<div class="card">
    <h3>💍 <?= h($p['nombre_pareja']) ?></h3>
    <p><?= h($p['novio']) ?> & <?= h($p['novia']) ?></p>
    <p>📅 <?= h($p['fecha_boda']) ?> · <?= h($p['lugar_boda']) ?></p>
    <div class="stat-row">
        <span>Total: <?= $s['total'] ?></span>
        <span class="ok">Confirmados: <?= $s['confirmado'] ?></span>
        <span class="warn">Pendientes: <?= $s['pendiente'] ?></span>
        <span class="bad">Rechazados: <?= $s['rechazado'] ?></span>
    </div>
    <div class="stat-row">
        <span>👥 Capacidad: <?= (int)$p['max_personas'] ?> persona(s)</span>
        <span>✅ Confirmadas: <?= $s['confirmado'] ?> de <?= (int)$p['max_personas'] ?> persona(s)</span>
    </div>
<a class="btn" href="<?= ADMIN_URL ?>/invitados.php?pareja=<?= $p['id_pareja'] ?>">Ver invitados</a>
    <a class="btn secondary" href="<?= PUBLIC_URL ?>/invitacion.php?token=<?= $p['token_acceso'] ?>" target="_blank">Ver invitación</a>
    <a class="btn" href="<?= ADMIN_URL ?>/asistencia.php?pareja=<?= $p['id_pareja'] ?>&tab=mesas">🪑 Configurar mesas</a>
</div>
<?php endforeach; ?>
</div>
</main>
</div>
</body>
</html>