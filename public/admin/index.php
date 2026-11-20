<?php
require_once __DIR__ . '/../../includes/functions.php';
requiereLogin();

$pdo = pdo();
$parejas = $pdo->query('SELECT * FROM parejas WHERE activo=1 ORDER BY id_pareja')->fetchAll();
$parejaId = isset($_GET['pareja']) ? (int)$_GET['pareja'] : ($parejas[0]['id_pareja'] ?? 0);

$parejaActual = null;
if ($parejaId) {
    $st = $pdo->prepare('SELECT * FROM parejas WHERE id_pareja=?');
    $st->execute([$parejaId]);
    $parejaActual = $st->fetch();
}

$stats = [];
$invitados = [];
$llegaron = 0;
$faltan = 0;

if ($parejaActual) {
    $st = $pdo->prepare('SELECT estado, COUNT(*) c FROM invitados WHERE id_pareja=? GROUP BY estado');
    $st->execute([$parejaActual['id_pareja']]);
    $row = ['pendiente'=>0,'confirmado'=>0,'rechazado'=>0,'total'=>0];
    foreach ($st->fetchAll() as $r){ $row[$r['estado']]=$r['c']; $row['total']+=$r['c']; }
    $stats = $row;

    $st = $pdo->prepare('SELECT COUNT(*) c FROM invitados WHERE id_pareja=? AND llego=1');
    $st->execute([$parejaActual['id_pareja']]);
    $llegaron = (int)$st->fetchColumn();

    $st = $pdo->prepare('SELECT COUNT(*) c FROM invitados WHERE id_pareja=? AND estado="confirmado" AND llego=0');
    $st->execute([$parejaActual['id_pareja']]);
    $faltan = (int)$st->fetchColumn();

    $orden = $_GET['orden'] ?? 'llegada_desc';
    $filtro = $_GET['filtro'] ?? 'todos';
    $buscar = $_GET['buscar'] ?? '';
    $mesaFiltro = $_GET['mesa'] ?? '';

    $where = ['i.id_pareja = ?'];
    $params = [$parejaActual['id_pareja']];

    if ($filtro === 'llegaron') {
        $where[] = 'i.llego = 1';
    } elseif ($filtro === 'faltan') {
        $where[] = 'i.estado = "confirmado" AND i.llego = 0';
    } elseif ($filtro === 'mesa' && $mesaFiltro !== '') {
        $where[] = 'i.mesa = ?';
        $params[] = $mesaFiltro;
    }

    if ($buscar !== '') {
        $where[] = '(i.nombre_completo LIKE ? OR i.codigo_invitacion LIKE ?)';
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
    }

    $orderBy = match ($orden) {
        'llegada_desc' => 'i.llego DESC, i.hora_llegada DESC',
        'llegada_asc' => 'i.llego ASC, i.hora_llegada ASC',
        'mesa' => 'i.mesa IS NULL, i.mesa ASC, i.nombre_completo ASC',
        'nombre' => 'i.nombre_completo ASC',
        'pendientes_primero' => 'CASE WHEN i.estado="confirmado" AND i.llego=0 THEN 0 WHEN i.estado="pendiente" THEN 1 ELSE 2 END, i.nombre_completo ASC',
        default => 'i.llego DESC, i.hora_llegada DESC',
    };

    $sql = 'SELECT i.* FROM invitados i WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $invitados = $st->fetchAll();

    $st = $pdo->prepare('SELECT DISTINCT mesa FROM invitados WHERE id_pareja=? AND mesa IS NOT NULL AND mesa!="" ORDER BY mesa');
    $st->execute([$parejaActual['id_pareja']]);
    $mesas = $st->fetchAll(PDO::FETCH_COLUMN);
}

$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'toggle_llegada' && $parejaActual) {
    $idInvitado = (int)$_POST['id_invitado'];
    $st = $pdo->prepare('SELECT llego FROM invitados WHERE id_invitado=? AND id_pareja=?');
    $st->execute([$idInvitado, $parejaActual['id_pareja']]);
    $inv = $st->fetch();
    if ($inv) {
        $nuevoLlego = $inv['llego'] ? 0 : 1;
        $hora = $nuevoLlego ? 'NOW()' : 'NULL';
        $pdo->prepare("UPDATE invitados SET llego=?, hora_llegada=$hora WHERE id_invitado=?")->execute([$nuevoLlego, $idInvitado]);
        flash($nuevoLlego ? 'Llegada registrada' : 'Llegada desmarcada');
    }
    redirigir(ADMIN_URL . '/index.php?pareja=' . $parejaActual['id_pareja'] . '&orden=' . urlencode($orden) . '&filtro=' . urlencode($filtro) . '&buscar=' . urlencode($buscar) . '&mesa=' . urlencode($mesaFiltro));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard - <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
    .selector-pareja {margin-bottom:20px;padding:16px;background:#fff;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.06);}
    .selector-pareja label {font-weight:600;margin-right:10px;}
    .selector-pareja select {padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px;}
    .metricas {display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px;}
    .metrica {background:#fff;padding:16px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.06);text-align:center;}
    .metrica .valor {font-size:28px;font-weight:700;color:#2F483D;}
    .metrica .label {font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.5px;}
    .metrica.llego .valor {color:#2e7d32;}
    .metrica.faltan .valor {color:#ef6c00;}
    .filtros {display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;background:#fff;padding:16px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.06);}
    .filtros select,.filtros input {padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px;}
    .filtros input[type=search] {flex:1;min-width:200px;}
    .tabla-invitados {width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,.06);}
    .tabla-invitados th,.tabla-invitados td {padding:12px;border-bottom:1px solid #eee;text-align:left;font-size:14px;}
    .tabla-invitados th {background:#F7F3EB;color:#2F483D;font-weight:600;}
    .tabla-invitados tr.llego td {background:#f3fbf4;}
    .tabla-invitados tr:hover td {background:#fafafa;}
    .badge-estado {padding:4px 8px;border-radius:6px;font-size:12px;font-weight:600;}
    .badge-estado.confirmado {background:#e8f5e9;color:#2e7d32;}
    .badge-estado.pendiente {background:#fff3e0;color:#ef6c00;}
    .badge-estado.rechazado {background:#ffebee;color:#c62828;}
    .badge-llego {padding:4px 8px;border-radius:6px;font-size:12px;font-weight:600;}
    .badge-llego.si {background:#e8f5e9;color:#2e7d32;}
    .badge-llego.no {background:#fff3e0;color:#ef6c00;}
    .btn-checkin {padding:6px 12px;font-size:12px;border-radius:6px;cursor:pointer;border:none;transition:all .2s;}
    .btn-checkin.llego {background:#2e7d32;color:#fff;}
    .btn-checkin.no-llego {background:#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9;}
    .hora-llegada {font-family:monospace;font-size:13px;color:#666;}
    @media (max-width:768px) {
        .tabla-invitados {display:block;overflow-x:auto;white-space:nowrap;}
        .filtros {flex-direction:column;}
        .filtros input[type=search] {width:100%;}
    }
</style>
</head>
<body>
<div class="layout">
<?php include __DIR__.'/../../includes/sidebar.php'; ?>
<main class="content">
<h2>Dashboard</h2>
<?php if($flash): ?><div class="alert <?= $flash['tipo'] ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<div class="selector-pareja">
    <label for="pareja-select">Evento activo:</label>
    <form method="GET" style="display:inline;">
        <select name="pareja" id="pareja-select" onchange="this.form.submit()">
            <?php foreach($parejas as $p): ?>
            <option value="<?= $p['id_pareja'] ?>" <?= $p['id_pareja']==$parejaId?'selected':'' ?>>
                <?= h($p['novio']) ?> & <?= h($p['novia']) ?> (<?= h($p['fecha_boda']) ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit">Filtrar</button></noscript>
    </form>
</div>

<?php if ($parejaActual): ?>
<div class="metricas">
    <div class="metrica"><div class="valor"><?= $stats['total'] ?></div><div class="label">Total Invitados</div></div>
    <div class="metrica"><div class="valor"><?= $stats['confirmado'] ?></div><div class="label">Confirmados</div></div>
    <div class="metrica"><div class="valor"><?= $stats['pendiente'] ?></div><div class="label">Pendientes</div></div>
    <div class="metrica"><div class="valor"><?= $stats['rechazado'] ?></div><div class="label">Rechazados</div></div>
    <div class="metrica llego"><div class="valor"><?= $llegaron ?></div><div class="label">Ya Llegaron</div></div>
    <div class="metrica faltan"><div class="valor"><?= $faltan ?></div><div class="label">Faltan por Llegar</div></div>
</div>

<div class="filtros">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;">
        <input type="hidden" name="pareja" value="<?= $parejaActual['id_pareja'] ?>">
        <select name="orden">
            <option value="llegada_desc" <?= $orden==='llegada_desc'?'selected':'' ?>>Orden: Llegada (recientes primero)</option>
            <option value="llegada_asc" <?= $orden==='llegada_asc'?'selected':'' ?>>Orden: Llegada (antiguas primero)</option>
            <option value="mesa" <?= $orden==='mesa'?'selected':'' ?>>Orden: Número de Mesa</option>
            <option value="nombre" <?= $orden==='nombre'?'selected':'' ?>>Orden: Nombre A-Z</option>
            <option value="pendientes_primero" <?= $orden==='pendientes_primero'?'selected':'' ?>>Orden: Pendientes de llegar primero</option>
        </select>
        <select name="filtro">
            <option value="todos" <?= $filtro==='todos'?'selected':'' ?>>Filtro: Todos</option>
            <option value="llegaron" <?= $filtro==='llegaron'?'selected':'' ?>>Filtro: Ya llegaron</option>
            <option value="faltan" <?= $filtro==='faltan'?'selected':'' ?>>Filtro: Faltan por llegar (confirmados)</option>
            <option value="mesa" <?= $filtro==='mesa'?'selected':'' ?>>Filtro: Por Mesa</option>
        </select>
        <?php if ($mesas): ?>
        <select name="mesa">
            <option value="">Todas las mesas</option>
            <?php foreach($mesas as $m): ?>
            <option value="<?= h($m) ?>" <?= $mesaFiltro===$m?'selected':'' ?>>Mesa <?= h($m) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <input type="search" name="buscar" placeholder="Buscar por nombre o código..." value="<?= h($buscar) ?>">
        <button class="btn" type="submit">Aplicar</button>
    </form>
</div>

<div style="overflow-x:auto;">
<table class="tabla-invitados">
<thead>
<tr>
    <th>Invitado</th>
    <th>Pases</th>
    <th>Mesa</th>
    <th>Estado RSVP</th>
    <th>Llegada</th>
    <th>Hora Llegada</th>
    <th>Restricción Alimentaria</th>
    <th>Canción Sugerida</th>
    <th>Acción</th>
</tr>
</thead>
<tbody>
<?php foreach($invitados as $inv): ?>
<tr class="<?= $inv['llego'] ? 'llego' : '' ?>">
    <td><?= h($inv['nombre_completo']) ?><br><small>#<?= h($inv['codigo_invitacion']) ?></small></td>
    <td><?= (int)$inv['cantidad_personas'] ?></td>
    <td><?= $inv['mesa'] ? h($inv['mesa']) : '<em>Sin asignar</em>' ?></td>
    <td><span class="badge-estado <?= h($inv['estado']) ?>"><?= ucfirst(h($inv['estado'])) ?></span></td>
    <td><span class="badge-llego <?= $inv['llego'] ? 'si' : 'no' ?>"><?= $inv['llego'] ? '✓ Sí' : '✗ No' ?></span></td>
    <td class="hora-llegada"><?= $inv['hora_llegada'] ? h($inv['hora_llegada']) : '—' ?></td>
    <td><?= $inv['restriccion_alimentaria'] ? h($inv['restriccion_alimentaria']) : '—' ?></td>
    <td><?= $inv['cancion_sugerida'] ? h($inv['cancion_sugerida']) : '—' ?></td>
    <td>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="accion" value="toggle_llegada">
            <input type="hidden" name="id_invitado" value="<?= $inv['id_invitado'] ?>">
            <input type="hidden" name="orden" value="<?= h($orden) ?>">
            <input type="hidden" name="filtro" value="<?= h($filtro) ?>">
            <input type="hidden" name="buscar" value="<?= h($buscar) ?>">
            <input type="hidden" name="mesa" value="<?= h($mesaFiltro) ?>">
            <button type="submit" class="btn-checkin <?= $inv['llego'] ? 'llego' : 'no-llego' ?>">
                <?= $inv['llego'] ? 'Desmarcar' : 'Marcar llegada' ?>
            </button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($invitados)): ?>
<tr><td colspan="9" style="text-align:center;padding:40px;color:#666;">No hay invitados que coincidan con los filtros.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
    <a class="btn" href="<?= ADMIN_URL ?>/invitados.php?pareja=<?= $parejaActual['id_pareja'] ?>">Gestionar Invitados</a>
    <a class="btn" href="<?= ADMIN_URL ?>/asistencia.php?pareja=<?= $parejaActual['id_pareja'] ?>&tab=mesas">🪑 Configurar Mesas</a>
    <a class="btn secondary" href="<?= ADMIN_URL ?>/qr_checkin.php?pareja=<?= $parejaActual['id_pareja'] ?>">📱 QR Check-in</a>
    <a class="btn secondary" href="<?= PUBLIC_URL ?>/invitacion.php?token=<?= $parejaActual['token_acceso'] ?>" target="_blank">Ver Invitación Pública</a>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:40px;">
    <p>No hay parejas activas. <a class="btn" href="<?= ADMIN_URL ?>/parejas.php">Crear primera pareja</a></p>
</div>
<?php endif; ?>
</main>
</div>
</body>
</html>