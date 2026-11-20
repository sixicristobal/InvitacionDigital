<?php
require_once __DIR__ . '/../../includes/functions.php';
requiereLogin();

$pdo = pdo();
$id = (int)($_GET['id'] ?? $_GET['pareja'] ?? 0);
if (!$id) { redirigir(ADMIN_URL . '/parejas.php'); }

$st = $pdo->prepare('SELECT * FROM parejas WHERE id_pareja=?');
$st->execute([$id]);
$pareja = $st->fetch();
if (!$pareja) { redirigir(ADMIN_URL . '/parejas.php'); }

$cfg = getParejaConfig($pareja);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_config') {
        $newCfg = $cfg;
        $newCfg['plantilla'] = $_POST['plantilla'] ?? 'clasica';
        $newCfg['hero']['subtitulo'] = trim($_POST['hero_subtitulo'] ?? '');
        $newCfg['hero']['musica_url'] = trim($_POST['hero_musica_url'] ?? '');
        $newCfg['hero']['autoplay'] = isset($_POST['hero_autoplay']);
        $newCfg['hero']['sobre_apertura'] = isset($_POST['hero_sobre_apertura']);

        $newCfg['historia']['activo'] = isset($_POST['historia_activo']);
        $newCfg['historia']['titulo'] = trim($_POST['historia_titulo'] ?? '');
        $newCfg['historia']['texto'] = trim($_POST['historia_texto'] ?? '');

        $eventos = [];
        $eventoTitulos = $_POST['evento_titulo'] ?? [];
        $eventoHoras = $_POST['evento_hora'] ?? [];
        $eventoLugares = $_POST['evento_lugar'] ?? [];
        $eventoDirecciones = $_POST['evento_direccion'] ?? [];
        $eventoMaps = $_POST['evento_maps'] ?? [];
        $eventoWaze = $_POST['evento_waze'] ?? [];
        foreach ($eventoTitulos as $i => $t) {
            if (trim($t) !== '') {
                $eventos[] = [
                    'titulo' => trim($t),
                    'hora' => trim($eventoHoras[$i] ?? ''),
                    'lugar' => trim($eventoLugares[$i] ?? ''),
                    'direccion' => trim($eventoDirecciones[$i] ?? ''),
                    'maps_url' => trim($eventoMaps[$i] ?? ''),
                    'waze_url' => trim($eventoWaze[$i] ?? ''),
                ];
            }
        }
        $newCfg['eventos'] = $eventos;

        $newCfg['vestimenta']['activo'] = isset($_POST['vestimenta_activo']);
        $newCfg['vestimenta']['codigo'] = trim($_POST['vestimenta_codigo'] ?? '');
        $newCfg['vestimenta']['nota'] = trim($_POST['vestimenta_nota'] ?? '');
        $newCfg['vestimenta']['colores_evitar'] = trim($_POST['vestimenta_colores'] ?? '');

        $newCfg['regalos']['activo'] = isset($_POST['regalos_activo']);
        $newCfg['regalos']['titulo'] = trim($_POST['regalos_titulo'] ?? '');
        $newCfg['regalos']['mensaje'] = trim($_POST['regalos_mensaje'] ?? '');
        $newCfg['regalos']['link_externo'] = trim($_POST['regalos_link'] ?? '');

        $newCfg['extras']['permitir_cancion'] = isset($_POST['extras_cancion']);
        $newCfg['extras']['permitir_restriccion'] = isset($_POST['extras_restriccion']);
        $newCfg['extras']['whatsapp_contacto'] = trim($_POST['extras_whatsapp'] ?? '');
        $newCfg['extras']['mensaje_whatsapp'] = trim($_POST['extras_whatsapp_msg'] ?? '');

        $pdo->prepare('UPDATE parejas SET plantilla=?, config_json=? WHERE id_pareja=?')
            ->execute([$newCfg['plantilla'], json_encode($newCfg, JSON_UNESCAPED_UNICODE), $id]);
        flash('Configuración guardada');
        redirigir(ADMIN_URL . "/configurar_invitacion.php?id=$id");
    }

    if ($action === 'upload_hero' && isset($_FILES['hero_foto'])) {
        $path = subirArchivoMultimedia($_FILES['hero_foto'], $id, 'imagen');
        if ($path) {
            $cfg['hero']['foto_portada'] = $path;
            $pdo->prepare('UPDATE parejas SET config_json=? WHERE id_pareja=?')
                ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $id]);
            flash('Foto de portada subida');
        } else {
            flash('Error al subir foto (formatos: JPG, PNG, WebP; máx 1600px ancho)', 'error');
        }
        redirigir(ADMIN_URL . "/configurar_invitacion.php?id=$id#tab-diseno");
    }

    if ($action === 'delete_hero') {
        if (!empty($cfg['hero']['foto_portada'])) {
            $filePath = __DIR__ . '/../../' . $cfg['hero']['foto_portada'];
            if (file_exists($filePath)) unlink($filePath);
            $cfg['hero']['foto_portada'] = '';
            $pdo->prepare('UPDATE parejas SET config_json=? WHERE id_pareja=?')
                ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $id]);
            flash('Foto de portada eliminada');
        }
        redirigir(ADMIN_URL . "/configurar_invitacion.php?id=$id#tab-diseno");
    }

    if ($action === 'upload_musica' && isset($_FILES['hero_musica'])) {
        $path = subirArchivoMultimedia($_FILES['hero_musica'], $id, 'audio');
        if ($path) {
            $cfg['hero']['musica_url'] = $path;
            $pdo->prepare('UPDATE parejas SET config_json=? WHERE id_pareja=?')
                ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $id]);
            flash('Música subida');
        } else {
            flash('Error al subir música (solo MP3, máx 10MB)', 'error');
        }
        redirigir(ADMIN_URL . "/configurar_invitacion.php?id=$id#tab-diseno");
    }

    if ($action === 'delete_musica') {
        if (!empty($cfg['hero']['musica_url'])) {
            $filePath = __DIR__ . '/../../' . $cfg['hero']['musica_url'];
            if (file_exists($filePath)) unlink($filePath);
            $cfg['hero']['musica_url'] = '';
            $pdo->prepare('UPDATE parejas SET config_json=? WHERE id_pareja=?')
                ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $id]);
            flash('Música eliminada');
        }
        redirigir(ADMIN_URL . "/configurar_invitacion.php?id=$id#tab-diseno");
    }

    if ($action === 'upload_galeria' && isset($_FILES['galeria_fotos'])) {
        $files = $_FILES['galeria_fotos'];
        $count = count($files['name']);
        $uploaded = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_OK) {
                $single = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
                $path = subirArchivoMultimedia($single, $id, 'imagen');
                if ($path) {
                    $cfg['galeria'][] = $path;
                    $uploaded++;
                }
            }
        }
        if ($uploaded) {
            $pdo->prepare('UPDATE parejas SET config_json=? WHERE id_pareja=?')
                ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $id]);
            flash("$uploaded foto(s) subidas a la galería");
        }
        redirigir(ADMIN_URL . "/configurar_invitacion.php?id=$id#tab-galeria");
    }

    if ($action === 'delete_galeria' && isset($_POST['galeria_index'])) {
        $idx = (int)$_POST['galeria_index'];
        if (isset($cfg['galeria'][$idx])) {
            $filePath = __DIR__ . '/../../' . $cfg['galeria'][$idx];
            if (file_exists($filePath)) unlink($filePath);
            unset($cfg['galeria'][$idx]);
            $cfg['galeria'] = array_values($cfg['galeria']);
            $pdo->prepare('UPDATE parejas SET config_json=? WHERE id_pareja=?')
                ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $id]);
            flash('Foto eliminada de la galería');
        }
        redirigir(ADMIN_URL . "/configurar_invitacion.php?id=$id#tab-galeria");
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Configurar Invitación - <?= h($pareja['novio']) ?> & <?= h($pareja['novia']) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
    .config-layout {display:grid;grid-template-columns:1fr 400px;gap:24px;}
    @media (max-width:1100px) {.config-layout {grid-template-columns:1fr;}}
    .config-sidebar {position:sticky;top:20px;height:fit-content;max-height:calc(100vh - 40px);overflow-y:auto;}
    .preview-frame {width:100%;border:none;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.12);background:#fff;}
    .preview-selector {display:flex;gap:8px;margin-bottom:12px;}
    .preview-selector button {flex:1;padding:8px;border:1px solid #ddd;border-radius:8px;background:#fff;cursor:pointer;font-size:13px;}
    .preview-selector button.active {background:#2F483D;color:#fff;border-color:#2F483D;}
    .tab-panel {display:none;}
    .tab-panel.active {display:block;}
    .tabs-nav {display:flex;gap:4px;margin-bottom:16px;flex-wrap:wrap;}
    .tab-btn {padding:10px 16px;border:1px solid #ddd;border-radius:8px 8px 0 0;background:#fff;cursor:pointer;font-weight:500;transition:all .2s;}
    .tab-btn.active {background:#2F483D;color:#fff;border-color:#2F483D;}
    .tab-btn:hover:not(.active) {border-color:#2F483D;color:#2F483D;}
    .form-group {margin-bottom:16px;}
    .form-group label {display:block;margin-bottom:6px;font-weight:500;font-size:14px;}
    .form-group input,.form-group select,.form-group textarea {width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px;}
    .form-group textarea {min-height:100px;resize:vertical;}
    .form-row {display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    @media (max-width:600px) {.form-row {grid-template-columns:1fr;}}
    .evento-block {border:1px solid #eee;padding:16px;border-radius:8px;margin-bottom:12px;background:#fafafa;position:relative;}
    .evento-block .remove-btn {position:absolute;top:8px;right:8px;padding:4px 8px;background:#ffebee;color:#c62828;border:none;border-radius:4px;cursor:pointer;font-size:12px;}
    .galeria-grid {display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px;}
    .galeria-item {position:relative;border:1px solid #ddd;border-radius:8px;overflow:hidden;aspect-ratio:1;}
    .galeria-item img {width:100%;height:100%;object-fit:cover;}
    .galeria-item .delete-btn {position:absolute;top:4px;right:4px;width:24px;height:24px;border-radius:50%;background:rgba(0,0,0,.6);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;}
    .color-inputs {display:flex;gap:12px;align-items:center;flex-wrap:wrap;}
    .color-inputs label {display:flex;align-items:center;gap:6px;font-size:13px;}
    .switch {display:flex;align-items:center;gap:10px;margin-bottom:12px;}
    .switch input[type=checkbox] {width:20px;height:20px;accent-color:#2F483D;}
    .file-upload {border:2px dashed #ddd;padding:20px;text-align:center;border-radius:8px;cursor:pointer;transition:border-color .2s;}
    .file-upload:hover,.file-upload.dragover {border-color:#2F483D;background:#f3fbf4;}
    .current-file {margin-top:12px;padding:12px;background:#f5f5f5;border-radius:8px;display:flex;justify-content:space-between;align-items:center;}
    .current-file audio {max-width:100%;}
    .help-text {font-size:12px;color:#666;margin-top:4px;}
</style>
</head>
<body>
<div class="layout">
<?php include __DIR__.'/../../includes/sidebar.php'; ?>
<main class="content">
<h2>Configurar Invitación: <?= h($pareja['novio']) ?> & <?= h($pareja['novia']) ?></h2>
<?php if($flash): ?><div class="alert <?= $flash['tipo'] ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<div class="config-layout">
    <div>
        <div class="tabs-nav" role="tablist">
            <button class="tab-btn active" role="tab" data-tab="tab-diseno">🎨 Diseño y Portada</button>
            <button class="tab-btn" role="tab" data-tab="tab-eventos">📍 Eventos y Ubicación</button>
            <button class="tab-btn" role="tab" data-tab="tab-historia">📖 Historia y Galería</button>
            <button class="tab-btn" role="tab" data-tab="tab-vestimenta">👗 Dress Code y Regalos</button>
            <button class="tab-btn" role="tab" data-tab="tab-rsvp">✉️ RSVP y Contacto</button>
        </div>

        <div class="tab-panel active" id="tab-diseno" role="tabpanel">
            <div class="form-group">
                <label>Plantilla</label>
                <select name="plantilla" id="plantilla-select">
                    <option value="clasica" <?= $cfg['plantilla']==='clasica'?'selected':'' ?>>Clásica</option>
                    <option value="floral" <?= $cfg['plantilla']==='floral'?'selected':'' ?>>Floral</option>
                    <option value="minimalista" <?= $cfg['plantilla']==='minimalista'?'selected':'' ?>>Minimalista</option>
                    <option value="editorial" <?= $cfg['plantilla']==='editorial'?'selected':'' ?>>Editorial</option>
                </select>
            </div>

            <div class="form-group">
                <label>Subtítulo Hero</label>
                <input type="text" name="hero_subtitulo" value="<?= h($cfg['hero']['subtitulo']) ?>" placeholder="¡Nos casamos!">
            </div>

            <div class="form-group">
                <label>Foto de Portada (Hero)</label>
                <div class="file-upload" id="drop-hero" ondragover="dragOver(event)" ondragleave="dragLeave(event)" ondrop="dropFile(event, 'hero_foto')">
                    <p>Arrastra una imagen aquí o haz clic para seleccionar</p>
                    <p class="help-text">Formatos: JPG, PNG, WebP · Máx 1600px ancho · Se convierte a WebP</p>
                    <input type="file" name="hero_foto" id="hero_foto" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="previewImage(this, 'preview-hero')">
                </div>
                <?php if (!empty($cfg['hero']['foto_portada'])): ?>
                <div class="current-file">
                    <div><img src="<?= BASE_URL ?>/../<?= h($cfg['hero']['foto_portada']) ?>" alt="Portada actual" style="max-height:80px;border-radius:4px;"></div>
                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="delete_hero"><button type="submit" class="btn secondary" style="padding:6px 12px;font-size:12px;">Eliminar</button></form>
                </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" id="form-hero-foto" style="display:none;">
                    <input type="hidden" name="action" value="upload_hero">
                    <input type="file" name="hero_foto" id="hero_foto_real" accept="image/jpeg,image/png,image/webp">
                </form>
            </div>

            <div class="form-group">
                <label>Música de Fondo (MP3)</label>
                <div class="file-upload" id="drop-musica" ondragover="dragOver(event)" ondragleave="dragLeave(event)" ondrop="dropFile(event, 'hero_musica')">
                    <p>Arrastra un archivo MP3 aquí o haz clic para seleccionar</p>
                    <p class="help-text">Solo MP3 · Máx 10MB</p>
                    <input type="file" name="hero_musica" id="hero_musica" accept="audio/mpeg,audio/mp3" style="display:none;">
                </div>
                <?php if (!empty($cfg['hero']['musica_url'])): ?>
                <div class="current-file">
                    <div>
                        <audio controls src="<?= BASE_URL ?>/../<?= h($cfg['hero']['musica_url']) ?>" style="max-width:300px;"></audio>
                    </div>
                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="delete_musica"><button type="submit" class="btn secondary" style="padding:6px 12px;font-size:12px;">Eliminar</button></form>
                </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" id="form-musica" style="display:none;">
                    <input type="hidden" name="action" value="upload_musica">
                    <input type="file" name="hero_musica" id="hero_musica_real" accept="audio/mpeg,audio/mp3">
                </form>
            </div>

            <div class="switch">
                <input type="checkbox" name="hero_autoplay" id="hero_autoplay" <?= $cfg['hero']['autoplay']?'checked':'' ?>>
                <label for="hero_autoplay">Reproducción automática</label>
            </div>
            <div class="switch">
                <input type="checkbox" name="hero_sobre_apertura" id="hero_sobre_apertura" <?= $cfg['hero']['sobre_apertura']?'checked':'' ?>>
                <label for="hero_sobre_apertura">Sobre de apertura animado</label>
            </div>

            <div class="form-group" style="margin-top:24px;">
                <form method="POST" id="form-save">
                    <input type="hidden" name="action" value="save_config">
                    <button type="submit" class="btn">Guardar toda la configuración</button>
                </form>
            </div>
        </div>

        <div class="tab-panel" id="tab-eventos" role="tabpanel">
            <h3 style="margin-bottom:16px;">Itinerario de Eventos</h3>
            <div id="eventos-container">
                <?php foreach ($cfg['eventos'] as $i => $ev): ?>
                <div class="evento-block" data-index="<?= $i ?>">
                    <button type="button" class="remove-btn" onclick="removeEvento(this)">Eliminar</button>
                    <div class="form-row">
                        <div class="form-group"><label>Título</label><input type="text" name="evento_titulo[]" value="<?= h($ev['titulo']) ?>" placeholder="Ceremonia"></div>
                        <div class="form-group"><label>Hora</label><input type="time" name="evento_hora[]" value="<?= h($ev['hora']) ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Lugar</label><input type="text" name="evento_lugar[]" value="<?= h($ev['lugar']) ?>" placeholder="Parroquia San José"></div>
                        <div class="form-group"><label>Dirección</label><input type="text" name="evento_direccion[]" value="<?= h($ev['direccion']) ?>" placeholder="Av. Principal 123, Ciudad"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Link Google Maps</label><input type="url" name="evento_maps[]" value="<?= h($ev['maps_url']) ?>" placeholder="https://maps.app.goo.gl/..."></div>
                        <div class="form-group"><label>Link Waze</label><input type="url" name="evento_waze[]" value="<?= h($ev['waze_url']) ?>" placeholder="https://waze.com/..."></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn secondary" onclick="addEvento()">+ Agregar evento</button>
        </div>

        <div class="tab-panel" id="tab-historia" role="tabpanel">
            <div class="switch">
                <input type="checkbox" name="historia_activo" id="historia_activo" <?= $cfg['historia']['activo']?'checked':'' ?>>
                <label for="historia_activo">Activar sección "Nuestra Historia"</label>
            </div>
            <div class="form-group">
                <label>Título</label>
                <input type="text" name="historia_titulo" value="<?= h($cfg['historia']['titulo']) ?>">
            </div>
            <div class="form-group">
                <label>Texto</label>
                <textarea name="historia_texto"><?= h($cfg['historia']['texto']) ?></textarea>
            </div>

            <h3 style="margin:24px 0 16px;">Galería de Fotos</h3>
            <div class="file-upload" id="drop-galeria" ondragover="dragOver(event)" ondragleave="dragLeave(event)" ondrop="dropFile(event, 'galeria_fotos')">
                <p>Arrastra fotos aquí o haz clic para seleccionar múltiples</p>
                <p class="help-text">Formatos: JPG, PNG, WebP · Se convierten a WebP</p>
                <input type="file" name="galeria_fotos" id="galeria_fotos" accept="image/jpeg,image/png,image/webp" multiple style="display:none;">
            </div>
            <form method="POST" enctype="multipart/form-data" id="form-galeria" style="display:none;">
                <input type="hidden" name="action" value="upload_galeria">
                <input type="file" name="galeria_fotos" id="galeria_fotos_real" accept="image/jpeg,image/png,image/webp" multiple>
            </form>

            <?php if (!empty($cfg['galeria'])): ?>
            <div class="galeria-grid" style="margin-top:16px;">
                <?php foreach ($cfg['galeria'] as $i => $img): ?>
                <div class="galeria-item">
                    <img src="<?= BASE_URL ?>/../<?= h($img) ?>" alt="Galería <?= $i+1 ?>">
                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="delete_galeria"><input type="hidden" name="galeria_index" value="<?= $i ?>"><button type="submit" class="delete-btn" title="Eliminar">×</button></form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="tab-panel" id="tab-vestimenta" role="tabpanel">
            <div class="switch">
                <input type="checkbox" name="vestimenta_activo" id="vestimenta_activo" <?= $cfg['vestimenta']['activo']?'checked':'' ?>>
                <label for="vestimenta_activo">Activar sección Dress Code</label>
            </div>
            <div class="form-group">
                <label>Código de vestimenta</label>
                <input type="text" name="vestimenta_codigo" value="<?= h($cfg['vestimenta']['codigo']) ?>" placeholder="Formal / Elegante">
            </div>
            <div class="form-group">
                <label>Nota adicional</label>
                <textarea name="vestimenta_nota"><?= h($cfg['vestimenta']['nota']) ?></textarea>
            </div>
            <div class="form-group">
                <label>Colores a evitar</label>
                <input type="text" name="vestimenta_colores" value="<?= h($cfg['vestimenta']['colores_evitar']) ?>" placeholder="Blanco, negro, rojo...">
            </div>

            <h3 style="margin:24px 0 16px;">Mesa de Regalos</h3>
            <div class="switch">
                <input type="checkbox" name="regalos_activo" id="regalos_activo" <?= $cfg['regalos']['activo']?'checked':'' ?>>
                <label for="regalos_activo">Activar sección de regalos</label>
            </div>
            <div class="form-group">
                <label>Título</label>
                <input type="text" name="regalos_titulo" value="<?= h($cfg['regalos']['titulo']) ?>">
            </div>
            <div class="form-group">
                <label>Mensaje</label>
                <textarea name="regalos_mensaje"><?= h($cfg['regalos']['mensaje']) ?></textarea>
            </div>
            <div class="form-group">
                <label>Enlace externo (opcional)</label>
                <input type="url" name="regalos_link" value="<?= h($cfg['regalos']['link_externo']) ?>" placeholder="https://...">
            </div>
            <p class="help-text">Los datos bancarios se toman de la ficha principal de la pareja.</p>
        </div>

        <div class="tab-panel" id="tab-rsvp" role="tabpanel">
            <div class="switch">
                <input type="checkbox" name="extras_cancion" id="extras_cancion" <?= $cfg['extras']['permitir_cancion']?'checked':'' ?>>
                <label for="extras_cancion">Permitir sugerencia de canción en RSVP</label>
            </div>
            <div class="switch">
                <input type="checkbox" name="extras_restriccion" id="extras_restriccion" <?= $cfg['extras']['permitir_restriccion']?'checked':'' ?>>
                <label for="extras_restriccion">Permitir restricción alimentaria en RSVP</label>
            </div>
            <div class="form-group">
                <label>WhatsApp de contacto</label>
                <input type="tel" name="extras_whatsapp" value="<?= h($cfg['extras']['whatsapp_contacto']) ?>" placeholder="+595 981 123456">
            </div>
            <div class="form-group">
                <label>Mensaje predeterminado WhatsApp</label>
                <input type="text" name="extras_whatsapp_msg" value="<?= h($cfg['extras']['mensaje_whatsapp']) ?>">
            </div>
        </div>
    </div>

    <aside class="config-sidebar">
        <div class="preview-selector">
            <button class="active" data-width="375">📱 Móvil (375px)</button>
            <button data-width="100%">💻 Escritorio (100%)</button>
        </div>
        <iframe class="preview-frame" id="preview-frame" src="<?= PUBLIC_URL ?>/invitacion.php?token=<?= $pareja['token_acceso'] ?>&preview=1" style="height:800px;"></iframe>
    </aside>
</div>
</main>
</div>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

document.querySelectorAll('.preview-selector button').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.preview-selector button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const frame = document.getElementById('preview-frame');
        frame.style.width = btn.dataset.width === '100%' ? '100%' : btn.dataset.width + 'px';
        frame.style.margin = btn.dataset.width === '100%' ? '0' : '0 auto';
    });
});

function dragOver(e) { e.preventDefault(); e.currentTarget.classList.add('dragover'); }
function dragLeave(e) { e.currentTarget.classList.remove('dragover'); }
function dropFile(e, inputId) {
    e.preventDefault();
    e.currentTarget.classList.remove('dragover');
    const input = document.getElementById(inputId);
    if (e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        const event = new Event('change', {bubbles: true});
        input.dispatchEvent(event);
    }
}

document.getElementById('hero_foto').addEventListener('change', e => {
    if (e.target.files.length) {
        document.getElementById('hero_foto_real').files = e.target.files;
        document.getElementById('form-hero-foto').submit();
    }
});

document.getElementById('hero_musica').addEventListener('change', e => {
    if (e.target.files.length) {
        document.getElementById('hero_musica_real').files = e.target.files;
        document.getElementById('form-musica').submit();
    }
});

document.getElementById('galeria_fotos').addEventListener('change', e => {
    if (e.target.files.length) {
        document.getElementById('galeria_fotos_real').files = e.target.files;
        document.getElementById('form-galeria').submit();
    }
});

let eventoIndex = <?= count($cfg['eventos']) ?>;
function addEvento() {
    const container = document.getElementById('eventos-container');
    const div = document.createElement('div');
    div.className = 'evento-block';
    div.dataset.index = eventoIndex;
    div.innerHTML = `
        <button type="button" class="remove-btn" onclick="removeEvento(this)">Eliminar</button>
        <div class="form-row">
            <div class="form-group"><label>Título</label><input type="text" name="evento_titulo[]" placeholder="Ceremonia"></div>
            <div class="form-group"><label>Hora</label><input type="time" name="evento_hora[]"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Lugar</label><input type="text" name="evento_lugar[]" placeholder="Parroquia San José"></div>
            <div class="form-group"><label>Dirección</label><input type="text" name="evento_direccion[]" placeholder="Av. Principal 123, Ciudad"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Link Google Maps</label><input type="url" name="evento_maps[]" placeholder="https://maps.app.goo.gl/..."></div>
            <div class="form-group"><label>Link Waze</label><input type="url" name="evento_waze[]" placeholder="https://waze.com/..."></div>
        </div>
    `;
    container.appendChild(div);
    eventoIndex++;
}

function removeEvento(btn) {
    btn.closest('.evento-block').remove();
}
</script>
</body>
</html>