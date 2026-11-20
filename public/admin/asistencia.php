<?php
require_once __DIR__ . '/../../includes/functions.php';
requiereLogin();
$pdo = pdo();

$parejaSel = isset($_GET['pareja']) ? (int)$_GET['pareja'] : 0;
$tab = ($_GET['tab'] ?? 'mesas') === 'checkin' ? 'checkin' : 'mesas';
$parejas = $pdo->query('SELECT * FROM parejas WHERE activo=1 ORDER BY id_pareja')->fetchAll();

// ------- Acciones POST -------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    if ($accion === 'asignar') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_map('intval', $ids);
        $mesa = trim($_POST['mesa_lote'] ?? '');
        if ($ids && $mesa !== '') {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("UPDATE invitados SET mesa=? WHERE id_invitado IN ($ph)");
            $st->execute(array_merge([$mesa], $ids));
            flash('Mesa "' . $mesa . '" asignada a ' . count($ids) . ' invitado(s).');
        } else {
            flash('Seleccioná al menos un invitado y una mesa.', 'error');
        }
        redirigir(ADMIN_URL . '/asistencia.php?tab=mesas' . ($parejaSel ? '&pareja=' . $parejaSel : ''));
    }
    if ($accion === 'desasignar') {
        $id = (int)($_POST['id_invitado'] ?? 0);
        $pdo->prepare('UPDATE invitados SET mesa=NULL WHERE id_invitado=?')->execute([$id]);
        flash('Asignación de mesa quitada.');
        redirigir(ADMIN_URL . '/asistencia.php?tab=mesas' . ($parejaSel ? '&pareja=' . $parejaSel : ''));
    }
    if ($accion === 'llegada') {
        $id = (int)($_POST['id_invitado'] ?? 0);
        $valor = (int)($_POST['valor'] ?? 1);
        $st = $pdo->prepare('SELECT id_pareja FROM invitados WHERE id_invitado=?');
        $st->execute([$id]);
        $in = $st->fetch();
        if ($in) {
            if ($valor) {
                $pdo->prepare('UPDATE invitados SET llego=1, hora_llegada=NOW() WHERE id_invitado=?')->execute([$id]);
                flash('¡Llegada registrada!');
            } else {
                $pdo->prepare('UPDATE invitados SET llego=0, hora_llegada=NULL WHERE id_invitado=?')->execute([$id]);
                flash('Llegada desmarcada.');
            }
        }
        redirigir(ADMIN_URL . '/asistencia.php?tab=checkin' . ($parejaSel ? '&pareja=' . $parejaSel : '') . (!empty($_POST['q']) ? '&q=' . urlencode($_POST['q']) : ''));
    }
}

$q = trim($_GET['q'] ?? '');

// ------- Consultas -------
$where = $parejaSel ? ' WHERE i.id_pareja=' . $parejaSel : ' WHERE 1=1';
$invitados = $pdo->query('SELECT i.*, p.nombre_pareja FROM invitados i JOIN parejas p ON i.id_pareja=p.id_pareja' . $where . ' ORDER BY i.id_pareja, i.nombre_completo')->fetchAll();

// Búsqueda para check-in
$buscados = [];
if ($q !== '') {
    $sql = 'SELECT i.*, p.nombre_pareja, p.novio, p.novia FROM invitados i JOIN parejas p ON i.id_pareja=p.id_pareja WHERE (i.nombre_completo LIKE :q OR i.codigo_invitacion LIKE :q2)';
    if ($parejaSel) { $sql .= ' AND i.id_pareja=' . $parejaSel; }
    $sql .= ' ORDER BY i.llego ASC, i.nombre_completo LIMIT 30';
    $st = $pdo->prepare($sql);
    $st->execute([':q' => '%' . $q . '%', ':q2' => '%' . $q . '%']);
    $buscados = $st->fetchAll();
}

// Check-in: listado por mesa (todos los confirmados/asignados)
$checkinInvitados = [];
if ($tab === 'checkin') {
    $sql = 'SELECT i.*, p.nombre_pareja FROM invitados i JOIN parejas p ON i.id_pareja=p.id_pareja';
    $sql .= $parejaSel ? ' WHERE i.id_pareja=' . $parejaSel . ' AND i.estado<>"rechazado"' : ' WHERE i.estado<>"rechazado"';
    $sql .= ' ORDER BY i.llego DESC, i.nombre_completo';
    $checkinInvitados = $pdo->query($sql)->fetchAll();
}

$porMesa = [];
$sinMesa = [];
foreach ($invitados as $i) {
    if ($i['estado'] === 'rechazado') continue;
    if (trim((string)$i['mesa']) !== '') $porMesa[trim($i['mesa'])][] = $i;
    else if ($i['estado'] === 'confirmado') $sinMesa[] = $i;
}
ksort($porMesa, SORT_NATURAL);

$flash = getFlash();
$tabUrl = function ($t) use ($parejaSel) {
    return ADMIN_URL . '/asistencia.php?tab=' . $t . ($parejaSel ? '&pareja=' . $parejaSel : '');
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Asistencia y Mesas - <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<main class="content">
<h2>Asistencia y Mesas</h2>
<?php if ($flash): ?><div class="alert <?= $flash['tipo'] ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<div class="tabs">
    <a class="tab <?= $tab === 'mesas' ? 'active' : '' ?>" href="<?= $tabUrl('mesas') ?>">🗂 Mesas</a>
    <a class="tab <?= $tab === 'checkin' ? 'active' : '' ?>" href="<?= $tabUrl('checkin') ?>">✓ Check-in / Recepción</a>
</div>

<form method="get" class="inline">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <select name="pareja" onchange="this.form.submit()">
        <option value="0">Todas las parejas</option>
        <?php foreach ($parejas as $p): ?>
        <option value="<?= $p['id_pareja'] ?>" <?= $parejaSel == $p['id_pareja'] ? 'selected' : '' ?>><?= h($p['nombre_pareja']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($tab === 'checkin'): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
</form>

<?php if ($tab === 'mesas'): ?>
    <!-- ============ PESTAÑA MESAS ============ -->
    <?php if ($sinMesa): ?>
    <div class="warn-box">⚠ <?= count($sinMesa) ?> invitado(s) confirmado(s) <em>sin mesa asignada</em> (filas resaltadas abajo).</div>
    <?php endif; ?>
    <?php if ($porMesa): ?>
    <h3>Invitados por mesa</h3>
    <?php endif; ?>
    <div class="mesa-grid">
    <?php foreach ($porMesa as $mesa => $lista): $personas = 0; foreach ($lista as $i) $personas += (int)$i['cantidad_personas']; ?>
    <div class="card mesa-card">
        <h3>🪑 <?= h($mesa) ?></h3>
        <p><?= count($lista) ?> grupo(s) · <?= $personas ?> persona(s)</p>
        <table class="table mesa-table">
        <?php foreach ($lista as $i): ?>
        <tr>
            <td><?= h($i['nombre_completo']) ?> <span class="badge <?= $i['estado'] ?>"><?= $i['estado'] ?></span></td>
            <td class="num"><?= $i['cantidad_personas'] ?>p</td>
            <td><form method="post" class="inline-form" onsubmit="return confirm('Quitar mesa a este invitado?')">
                <input type="hidden" name="accion" value="desasignar">
                <input type="hidden" name="id_invitado" value="<?= $i['id_invitado'] ?>">
                <button class="btn-mini" type="submit">✕</button>
            </form></td>
        </tr>
        <?php endforeach; ?>
        </table>
    </div>
    <?php endforeach; ?>
    </div>

    <?php if ($invitados): ?>
    <h3>Asignar mesas</h3>
    <form method="post" class="form-card">
        <input type="hidden" name="accion" value="asignar">
        <p><strong>Selecciona invitados y asignales una mesa:</strong> (se muestran todos menos los rechazados; para cambiar la mesa de uno solo, marcas solo su casilla).</p>
        <div class="batch-row">
            <input name="mesa_lote" placeholder="Ej: 5 o Mesa VIP" required>
            <button class="btn" type="submit">Aplicar a seleccionados</button>
        </div>
        <table class="table">
        <tr><th></th><th>Invitado</th><th>Personas</th><th>Estado</th><th>Mesa actual</th></tr>
        <?php foreach ($invitados as $i): if ($i['estado'] === 'rechazado') continue; $sin = trim((string)$i['mesa']) === ''; ?>
        <tr <?= $sin ? 'class="fila-sin-mesa"' : '' ?>>
            <td><input type="checkbox" name="ids[]" value="<?= $i['id_invitado'] ?>"></td>
            <td><?= h($i['nombre_completo']) ?><br><small><?= h($i['nombre_pareja']) ?></small></td>
            <td><?= $i['cantidad_personas'] ?></td>
            <td><span class="badge <?= $i['estado'] ?>"><?= $i['estado'] ?></span></td>
            <td><?= $i['mesa'] ? h($i['mesa']) : '<em style="color:#999">—</em>' ?></td>
        </tr>
        <?php endforeach; ?>
        </table>
        <div class="batch-row">
            <input name="mesa_lote" placeholder="Ej: 5 o Mesa VIP" required>
            <button class="btn" type="submit">Aplicar a seleccionados</button>
        </div>
    </form>
    <?php else: ?>
    <p>No hay invitados registrados todavía.</p>
    <?php endif; ?>

<?php else: ?>
    <!-- ============ PESTAÑA CHECK-IN ============ -->
    <div class="checkin-search">
        <form method="get">
            <input type="hidden" name="tab" value="checkin">
            <select name="pareja" onchange="this.form.submit()">
                <option value="0">Todas las parejas</option>
                <?php foreach ($parejas as $p): ?>
                <option value="<?= $p['id_pareja'] ?>" <?= $parejaSel == $p['id_pareja'] ? 'selected' : '' ?>><?= h($p['nombre_pareja']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="search" name="q" value="<?= h($q) ?>" placeholder="Buscar por nombre o código…" autofocus>
            <button class="btn" type="submit">🔍 Buscar</button>
        </form>
    </div>

    <!-- Escáner QR -->
    <div class="qr-scanner-section">
        <div class="qr-scanner-card">
            <h3>📷 Escanear QR</h3>
            <div class="qr-reader">
                <video id="qr-video" width="100%" playsinline></video>
                <div id="qr-shutter" class="qr-shutter">📷</div>
            </div>
            <div class="qr-controls">
                <button class="btn" id="qr-start">Iniciar cámara</button>
                <button class="btn secondary" id="qr-stop" style="display:none">Detener</button>
            </div>
            <div class="qr-manual">
                <input type="text" id="qr-manual-input" placeholder="O pegar código manualmente: ABC123" class="form-input">
                <button class="btn" id="qr-manual-btn">✅ Marcar por código</button>
            </div>
            <div id="qr-status" class="qr-status"></div>
        </div>
    </div>

    <?php if ($q !== ''): ?>
        <h3>Resultados para "<?= h($q) ?>"</h3>
        <?php if (!$buscados): ?>
            <div class="warn-box">Sin resultados. Probá con otro nombre o código.</div>
        <?php endif; ?>
        <?php foreach ($buscados as $b): $rechazado = $b['estado'] === 'rechazado'; ?>
        <div class="card checkin-card <?= $b['llego'] ? 'llego' : '' ?>">
            <div class="checkin-main">
                <div>
                    <span class="badge <?= $b['estado'] ?>"><?= $b['estado'] ?></span>
                    <strong class="checkin-nombre"><?= h($b['nombre_completo']) ?></strong>
                    <small><?= h($b['nombre_pareja']) ?></small>
                </div>
                <div class="big-mesa <?= $rechazado ? 'no' : '' ?>">
                    <?= $rechazado ? 'NO ASISTIRÁ' : ($b['mesa'] ? '🪑 ' . h($b['mesa']) : 'Sin mesa') ?>
                </div>
                <div class="checkin-meta">
                    👥 <?= $b['cantidad_personas'] ?> persona(s)
                </div>
            </div>
            <div class="checkin-actions">
            <?php if ($b['llego'] ?? 0): ?>
                <span class="llego-ok">✓ LLEGÓ<?= $b['hora_llegada'] ? ' ' . date('H:i', strtotime($b['hora_llegada'])) : '' ?></span>
                <form method="post" class="inline-form">
                    <input type="hidden" name="tab" value="checkin"><input type="hidden" name="q" value="<?= h($q) ?>">
                    <input type="hidden" name="accion" value="llegada"><input type="hidden" name="valor" value="0">
                    <input type="hidden" name="id_invitado" value="<?= $b['id_invitado'] ?>">
                    <button class="btn secondary btn-big" type="submit">Desmarcar</button>
                </form>
            <?php else: ?>
                <form method="post" class="inline-form">
                    <input type="hidden" name="tab" value="checkin"><input type="hidden" name="q" value="<?= h($q) ?>">
                    <input type="hidden" name="accion" value="llegada"><input type="hidden" name="valor" value="1">
                    <input type="hidden" name="id_invitado" value="<?= $b['id_invitado'] ?>">
                    <button class="btn btn-big" type="submit" <?= $rechazado ? 'onclick="return confirm(\'Está marcado como rechazado, marcar llegada igual?\')"' : '' ?>>✓ Marcar llegada</button>
                </form>
            <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="two-cols">
        <div>
            <h3>Ya llegaron</h3>
            <?php
            $llegaron = array_filter($checkinInvitados, fn($i) => $i['llego']);
            if (!$llegaron): ?><p>Nadie llegó todavía.</p><?php endif;
            foreach ($llegaron as $i): ?>
            <div class="card checkin-mini llego">
                <span><?= h($i['nombre_completo']) ?> · 👥 <?= (int)$i['cantidad_personas'] ?></span>
                <span class="badge ok">Mesa: <?= $i['mesa'] ? h($i['mesa']) : '—' ?><?= $i['hora_llegada'] ? ' · ' . date('H:i', strtotime($i['hora_llegada'])) : '' ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div>
            <h3>Pendientes de llegar (confirmados)</h3>
            <?php
            $pend = array_filter($checkinInvitados, fn($i) => !$i['llego']);
            if (!$pend): ?><p>Todos llegaron 🎉</p><?php endif;
            foreach (array_slice($pend, 0, 50) as $i): ?>
            <div class="card checkin-mini">
                <form method="post" class="inline-form" style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                    <input type="hidden" name="tab" value="checkin"><input type="hidden" name="q" value="<?= h($q) ?>">
                    <input type="hidden" name="accion" value="llegada"><input type="hidden" name="valor" value="1">
                    <input type="hidden" name="id_invitado" value="<?= $i['id_invitado'] ?>">
                    <button class="btn-mini" type="submit" title="Marcar llegada">✓</button>
                    <span style="text-align:left">
                        <?= h($i['nombre_completo']) ?>
                        <small style="display:block">Mesa: <?= $i['mesa'] ? h($i['mesa']) : '—' ?> · 👥 <?= (int)$i['cantidad_personas'] ?></small>
                    </span>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

</main>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.12/html5-qrcode.min.js"></script>
<script>
(function() {
    var video = document.getElementById('qr-video');
    var startBtn = document.getElementById('qr-start');
    var stopBtn = document.getElementById('qr-stop');
    var manualInput = document.getElementById('qr-manual-input');
    var manualBtn = document.getElementById('qr-manual-btn');
    var statusEl = document.getElementById('qr-status');
    var scanner = null;

    function setStatus(msg, type) {
        statusEl.textContent = msg;
        statusEl.className = 'qr-status ' + (type || '');
    }

    function processCode(code) {
        if (!code) return;
        var fd = new FormData();
        fd.append('codigo', code);
        fetch('qr_checkin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                setStatus('✓ ' + data.msg + ' — ' + data.invitado + ' (' + data.pareja + ')', 'success');
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Llegada registrada!',
                        text: data.invitado + ' · ' + data.pareja + ' · ' + data.personas + ' persona(s)',
                        confirmButtonText: 'Eliminar',
                        confirmButtonColor: '#2F483D'
                    });
                }
                // Recargar la página para actualizaciones
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                setStatus('⚠ ' + data.msg, 'error');
            }
        })
        .catch(() => setStatus('Error de conexión', 'error'));
    }

    startBtn.addEventListener('click', function() {
        if (scanner) return;
        scanner = new Html5Qrcode('qr-video');
        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 200, height: 200 } },
            function(qrCode) {
                stopScanner();
                processCode(qrCode);
            },
            function(error) { /* ignore */ }
        ).then(function() {
            startBtn.style.display = 'none';
            stopBtn.style.display = 'inline-block';
            setStatus('Cámara activa. Escaneá el QR.', 'info');
        }).catch(function(err) {
            setStatus('No se pudo acceder a la cámara: ' + err, 'error');
        });
    });

    function stopScanner() {
        if (scanner) {
            try { scanner.stop(); } catch(e) {}
            scanner = null;
            startBtn.style.display = 'inline-block';
            stopBtn.style.display = 'none';
        }
    }

    stopBtn.addEventListener('click', stopScanner);

    manualBtn.addEventListener('click', function() {
        processCode(manualInput.value.trim());
    });

    manualInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') processCode(manualInput.value.trim());
    });
})();
</script>
</body>
</html>
