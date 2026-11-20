<?php
require_once __DIR__ . '/../../includes/functions.php';
requiereLogin();
$pdo = pdo();
$parejaSel = isset($_GET['pareja']) ? (int)$_GET['pareja'] : 0;
$parejas = $pdo->query('SELECT * FROM parejas WHERE activo=1 ORDER BY id_pareja')->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['accion']) && $_POST['accion']==='agregar') {
    $idp = (int)$_POST['id_pareja'];
    $nombre = trim($_POST['nombre_completo']);
    $tel = trim($_POST['telefono']);
    $correo = trim($_POST['correo']);
    $cant = (int)$_POST['cantidad_personas'];
    $cancion = trim($_POST['cancion_sugerida'] ?? '');
    $restriccion = trim($_POST['restriccion_alimentaria'] ?? '');
    $codigo = generarToken(16);
    $st = $pdo->prepare('INSERT INTO invitados (id_pareja,nombre_completo,telefono,correo,cantidad_personas,codigo_invitacion,cancion_sugerida,restriccion_alimentaria) VALUES (?,?,?,?,?,?,?,?)');
    $st->execute([$idp,$nombre,$tel,$correo,$cant,$codigo,$cancion,$restriccion]);
    flash('Invitado agregado.');
    redirigir(ADMIN_URL.'/invitados.php?pareja='.$idp);
}
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    $pdo->prepare('DELETE FROM invitados WHERE id_invitado=?')->execute([$id]);
    flash('Invitado eliminado.');
    redirigir(ADMIN_URL.'/invitados.php'.($parejaSel?'?pareja='.$parejaSel:''));
}

$sql = 'SELECT i.*, p.nombre_pareja FROM invitados i JOIN parejas p ON i.id_pareja=p.id_pareja';
if ($parejaSel) { $sql.=' WHERE i.id_pareja='.$parejaSel; }
$sql.=' ORDER BY i.id_invitado DESC';
$invitados = $pdo->query($sql)->fetchAll();
$porPareja = [];
foreach ($invitados as $i) $porPareja[$i['id_pareja']][] = $i;
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Invitados</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"></head><body>
<div class="layout"><?php include __DIR__.'/../../includes/sidebar.php'; ?>
<main class="content">
<h2>Invitados</h2>
<form method="get" class="inline">
<select name="pareja" onchange="this.form.submit()">
<option value="0">Todas las parejas</option>
<?php foreach($parejas as $p): ?>
<option value="<?= $p['id_pareja'] ?>" <?= $parejaSel==$p['id_pareja']?'selected':'' ?>><?= h($p['nombre_pareja']) ?></option>
<?php endforeach; ?>
</select>
</form>
<form method="post" class="form-card">
<input type="hidden" name="accion" value="agregar">
<select name="id_pareja" required>
<?php foreach($parejas as $p): ?><option value="<?= $p['id_pareja'] ?>"><?= h($p['nombre_pareja']) ?></option><?php endforeach; ?>
</select>
<input name="nombre_completo" placeholder="Nombre completo" required>
<input name="telefono" placeholder="Teléfono (WhatsApp)">
<input name="correo" placeholder="Correo">
<input type="number" name="cantidad_personas" value="1" min="1" required>
<input name="cancion_sugerida" placeholder="Canción sugerida (opcional)">
<input name="restriccion_alimentaria" placeholder="Restricción alimentaria (opcional)">
<button type="submit">Agregar invitado</button>
</form>
<?php foreach ($parejas as $p): if (!isset($porPareja[$p['id_pareja']])) continue; ?>
<h3><?= h($p['nombre_pareja']) ?> (<?= count($porPareja[$p['id_pareja']]) ?> invitados)</h3>
<table class="table">
<tr><th>Nombre</th><th>Tel</th><th>Personas</th><th>Estado</th><th>Canción</th><th>Restricción</th><th>Link invitación</th><th>WhatsApp</th><th>Acciones</th></tr>
<?php foreach($porPareja[$p['id_pareja']] as $i):
  $link = PUBLIC_URL.'/invitacion.php?codigo='.$i['codigo_invitacion'];
  $wa = 'https://wa.me/'.preg_replace('/\D/','',$i['telefono']).'?text='.urlencode('Hola '.explode(' ',$i['nombre_completo'])[0].', te invitamos a la boda. Confirma aquí: '.$link);
?>
<tr>
<td><?= h($i['nombre_completo']) ?></td>
<td><?= h($i['telefono']) ?></td>
<td><?= $i['cantidad_personas'] ?></td>
<td><span class="badge <?= $i['estado'] ?>"><?= $i['estado'] ?></span></td>
<td><?= h($i['cancion_sugerida'] ?? '') ?></td>
<td><?= h($i['restriccion_alimentaria'] ?? '') ?></td>
<td><a href="<?= $link ?>" target="_blank">Abrir</a></td>
<td><a href="<?= $wa ?>" target="_blank">Enviar</a></td>
<td><a href="?eliminar=<?= $i['id_invitado'] ?>" onclick="return confirm('¿Eliminar?')">Eliminar</a></td>
</tr>
<?php endforeach; ?>
</table>
<?php endforeach; ?>
</main></div></body></html>