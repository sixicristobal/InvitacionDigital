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
    $lugarCeremonia = trim($_POST['lugar_ceremonia'] ?? '');
    $lugarFiesta = trim($_POST['lugar_fiesta'] ?? '');
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
    $maxPersonas = (int)($_POST['max_personas'] ?? 2);
    if ($maxPersonas < 1) $maxPersonas = 1;
    if ($maxPersonas > 6) $maxPersonas = 6;
    $token = generarToken();
    $st = $pdo->prepare('INSERT INTO parejas (nombre_pareja,novio,novia,fecha_boda,lugar_boda,lugar_ceremonia,lugar_fiesta,hora_boda,mensagem_invitacion,token_acceso,banco_alias,banco_nombre,banco_cuenta,banco_titular,color_damas,estilo,color_fondo,color_secundario,color_acento,max_personas) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([$nombre,$novio,$novia,$fecha,$lugar,$lugarCeremonia,$lugarFiesta,$hora,$msg,$token,$banco_alias,$banco_nombre,$banco_cuenta,$banco_titular,$color_damas,$estilo,$color_fondo,$color_secundario,$color_acento,$maxPersonas]);
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
    <input name="lugar_ceremonia" placeholder="Dirección ceremonia (Iglesia)">
    <input name="lugar_fiesta" placeholder="Dirección fiesta (Colegio Sakura)">
    <input type="number" name="max_personas" id="max_personas" value="2" min="1" max="6" required>
    <label for="max_personas">Máximo personas por invitación (1-6)</label>
    <input type="time" name="hora_boda">
    <textarea name="mensaje_invitacion" placeholder="Mensaje de invitación"></textarea>
    <!-- Nuevos campos configurables -->
    <input name="banco_alias" placeholder="Alias del banco" required>
    <input name="banco_nombre" placeholder="Nombre del banco" required>
    <input name="banco_cuenta" placeholder="Número de cuenta" required>
    <input name="banco_titular" placeholder="Titular de la cuenta" required>
    <label>Color damas de honor</label>
    <input type="color" name="color_damas" id="color-damas" value="#B85323" required onchange="actualizarPreviewColor('color-damas','pv-damas')">
    <span id="pv-damas" style="display:inline-block;width:24px;height:24px;border:1px solid #ccc;border-radius:4px;vertical-align:middle;margin-left:6px;"></span>
    <label>Estilo</label>
    <select name="estilo" id="selector-estilo" onchange="cambiarColores()">
        <option value="minimalista-elegante">Minimalista Elegante</option>
        <option value="bohemio-terracota">Bohemio Terracota</option>
        <option value="moderno-organico">Moderno Orgánico (Eucalipto & Sage)</option>
    </select>
    <div id="estilo-preview" style="display:flex;gap:6px;margin-top:6px;">
        <span id="pv-fondo" style="width:24px;height:24px;border:1px solid #ccc;border-radius:4px;"></span>
        <span id="pv-secundario" style="width:24px;height:24px;border:1px solid #ccc;border-radius:4px;"></span>
        <span id="pv-acento" style="width:24px;height:24px;border:1px solid #ccc;border-radius:4px;"></span>
    </div>

    <label>Color fondo</label>
    <input type="color" name="color_fondo" id="color-fondo" value="#FFFFFF" onchange="actualizarPreviewColor('color-fondo','pv-fondo2')">
    <span id="pv-fondo2" style="display:inline-block;width:24px;height:24px;border:1px solid #ccc;border-radius:4px;vertical-align:middle;margin-left:6px;"></span>

    <label>Color secundario</label>
    <input type="color" name="color_secundario" id="color-secundario" value="#1A1A1A" onchange="actualizarPreviewColor('color-secundario','pv-secundario2')">
    <span id="pv-secundario2" style="display:inline-block;width:24px;height:24px;border:1px solid #ccc;border-radius:4px;vertical-align:middle;margin-left:6px;"></span>

    <label>Color acento</label>
    <input type="color" name="color_acento" id="color-acento" value="#D4AF37" onchange="actualizarPreviewColor('color-acento','pv-acento2')">
    <span id="pv-acento2" style="display:inline-block;width:24px;height:24px;border:1px solid #ccc;border-radius:4px;vertical-align:middle;margin-left:6px;"></span>
    

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
    <td>
        <a class="btn" href="<?= ADMIN_URL ?>/configurar_invitacion.php?id=<?= $p['id_pareja'] ?>">Personalizar Invitación</a>
        <form method="post" style="display:inline;margin-top:4px;"><input type="hidden" name="accion" value="toggle"><input type="hidden" name="id_pareja" value="<?= $p['id_pareja'] ?>"><button type="submit" class="btn secondary"><?= $p['activo']?'Desactivar':'Activar' ?></button></form>
    </td>
</tr>
<?php endforeach; ?>
</table>
</main></div>

<script>
function cambiarColores() {
    const estilo = document.getElementById('selector-estilo').value;
    
    const paletas = {
        'minimalista-elegante': {
            fondo: '#FFFFFF',
            secundario: '#1A1A1A',
            acento: '#D4AF37'
        },
        'bohemio-terracota': {
            fondo: '#FAF4F0',
            secundario: '#C85A17',
            acento: '#D4A373'
        },
        'moderno-organico': {
            fondo: '#FDFBF7',
            secundario: '#8A9A86',
            acento: '#B76E79'
        }
    };

    if (paletas[estilo]) {
        document.getElementById('color-fondo').value = paletas[estilo].fondo;
        document.getElementById('color-secundario').value = paletas[estilo].secundario;
        document.getElementById('color-acento').value = paletas[estilo].acento;
        document.getElementById('pv-fondo').style.background = paletas[estilo].fondo;
        document.getElementById('pv-secundario').style.background = paletas[estilo].secundario;
        document.getElementById('pv-acento').style.background = paletas[estilo].acento;
        actualizarPreviewColor('color-fondo','pv-fondo2');
        actualizarPreviewColor('color-secundario','pv-secundario2');
        actualizarPreviewColor('color-acento','pv-acento2');
    }
}
function actualizarPreviewColor(inputId, previewId) {
    document.getElementById(previewId).style.background = document.getElementById(inputId).value;
}
// Ejecutar al cargar la página por si acaso
document.addEventListener('DOMContentLoaded', function(){ cambiarColores(); actualizarPreviewColor('color-fondo','pv-fondo2'); actualizarPreviewColor('color-secundario','pv-secundario2'); actualizarPreviewColor('color-acento','pv-acento2'); actualizarPreviewColor('color-damas','pv-damas'); });
</script>

</body></html>