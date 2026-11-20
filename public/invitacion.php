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
    $st = $pdo->prepare('SELECT * FROM parejas WHERE token_acceso=? AND activo=1');
    $st->execute([$token]); $pareja = $st->fetch();
}
if (!$pareja) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Buscar invitación</title><link rel="stylesheet" href="'.BASE_URL.'/assets/css/style.css"></head><body class="invite-body"><div class="invite-card"><h2>Introduce tu código de invitación</h2><form method="GET"><label>Código:</label><input type="text" name="codigo" required><button type="submit">Buscar</button></form></div></body></html>';
    exit;
}
$novia = $pareja['novia'] ?? '';
$novio = $pareja['novio'] ?? '';
$fecha = $pareja['fecha_boda'] ?? '';
$ts = strtotime($fecha);
$meses = ['','ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
$dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$mes = $meses[(int)date('n',$ts)];
$diaSemana = $dias[(int)date('w',$ts)];
$dia = date('d',$ts);
$anio = date('Y',$ts);
$hora = $pareja['hora_boda'] ?? '';
$lugar = $pareja['lugar_boda'] ?? '';
$msg = $pareja['mensaje_invitacion'] ?? '';

$mensaje = '';
$registroCodigo = '';
$maxPersonas = (int)($pareja['max_personas'] ?? 2);
if ($maxPersonas < 1) $maxPersonas = 1;
if ($_SERVER['REQUEST_METHOD']==='POST' && !$invitado && $pareja) {
    $nombre = trim($_POST['nombre_completo'] ?? '');
    $tel = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $asiste = $_POST['asiste'] ?? 'si';
    $estado = ($asiste === 'si') ? 'confirmado' : 'rechazado';
    $cant = ($estado === 'rechazado') ? 0 : (int)($_POST['cantidad'] ?? 1);
    if ($cant < 1 || $cant > $maxPersonas) $cant = ($estado === 'rechazado') ? 0 : 1;
    if ($nombre !== '') {
        $codigo = generarToken(16);
        $pdo->prepare('INSERT INTO invitados (id_pareja,nombre_completo,telefono,correo,cantidad_personas,codigo_invitacion,estado,mensaje,fecha_confirmacion) VALUES (?,?,?,?,?,?,?,?,NOW())')
            ->execute([$pareja['id_pareja'],$nombre,$tel,$correo,$cant,$codigo,$estado,trim($_POST['mensaje'] ?? '')]);
        $registroCodigo = $codigo;
        $mensaje = '¡Gracias por registrarte!';
    }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && $invitado) {
    $estado = $_POST['estado'] ?? 'confirmado';
    $cant = (int)($_POST['cantidad'] ?? $invitado['cantidad_personas']);
    if ($cant < 1 || $cant > $maxPersonas) $cant = 1;
    $m = trim($_POST['mensaje'] ?? '');
    $pdo->prepare('UPDATE invitados SET estado=?, cantidad_personas=?, mensaje=?, fecha_confirmacion=NOW() WHERE id_invitado=?')
        ->execute([$estado,$cant,$m,$invitado['id_invitado']]);
    $mensaje = '¡Gracias por confirmar!';
    $st = $pdo->prepare('SELECT i.*, p.* FROM invitados i JOIN parejas p ON i.id_pareja=p.id_pareja WHERE i.codigo_invitacion=?');
    $st->execute([$codigo]); $invitado = $st->fetch(); $pareja = $invitado;
}

$formHtml = '';
$maxLabel = $maxPersonas === 1 ? 'máx. 1 persona' : 'máx. ' . $maxPersonas . ' personas';
$optSelect = '<option value="1">1 Persona (Solo yo)</option>';
for ($i = 2; $i <= $maxPersonas; $i++) {
    $optSelect .= '<option value="' . $i . '">' . $i . ' Personas (Con acompañante)</option>';
}
if ($invitado) {
    $formHtml = '<form class="space-y-4" method="post" id="rsvpForm">
        <div><label class="block font-sans text-xs font-semibold text-neutral-700 uppercase tracking-wider mb-1" for="nombre_completo">Nombre Completo</label>
        <input class="w-full text-sm rounded-lg border-neutral-300 py-2.5 px-3 bg-neutral-100 text-neutral-700" id="nombre_completo" name="nombre_completo" type="text" value="'.h($invitado['nombre_completo']).'" readonly /></div>
        <div><label class="block font-sans text-xs font-semibold text-neutral-700 uppercase tracking-wider mb-1" for="estado">¿Asistirás? *</label>
        <select class="w-full text-sm rounded-lg border-neutral-300 focus:border-terracotta focus:ring-terracotta py-2.5 px-3 bg-neutral-50/50" name="estado" id="estado">
            <option value="confirmado"'.($invitado['estado']==='confirmado'?' selected':'').'>Sí, con gusto</option>
            <option value="rechazado"'.($invitado['estado']==='rechazado'?' selected':'').'>No podré</option>
        </select></div>
        <div><label class="block font-sans text-xs font-semibold text-neutral-700 uppercase tracking-wider mb-1" for="cantidad">Pases / Acompañantes (' . $maxLabel . ')</label>
        <select class="w-full text-sm rounded-lg border-neutral-300 focus:border-terracotta focus:ring-terracotta py-2.5 px-3 bg-neutral-50/50" name="cantidad" id="cantidad">' . $optSelect . '</select></div>
        <div class="pt-2"><button class="w-full bg-[#2F483D] hover:bg-[#23372E] text-white font-serif tracking-[0.2em] uppercase text-sm font-semibold py-3.5 px-6 rounded-full shadow-md hover:shadow-xl transition transform active:scale-95 duration-150" type="submit">CLIC AQUÍ PARA CONFIRMAR</button></div>
    </form>';
} else {
    $formHtml = '<form class="space-y-4" method="post" id="rsvpForm">
        <div><label class="block font-sans text-xs font-semibold text-neutral-700 uppercase tracking-wider mb-1" for="nombre_completo">Nombre Completo *</label>
        <input class="w-full text-sm rounded-lg border-neutral-300 focus:border-terracotta focus:ring-terracotta py-2.5 px-3 bg-neutral-50/50" id="nombre_completo" name="nombre_completo" placeholder="Ej. Juan Pérez" required type="text" /></div>
        <div><label class="block font-sans text-xs font-semibold text-neutral-700 uppercase tracking-wider mb-1" for="telefono">Teléfono / WhatsApp</label>
        <input class="w-full text-sm rounded-lg border-neutral-300 focus:border-terracotta focus:ring-terracotta py-2.5 px-3 bg-neutral-50/50" id="telefono" name="telefono" placeholder="Ej. +595 981 123456" type="tel" /></div>
        <div><label class="block font-sans text-xs font-semibold text-neutral-700 uppercase tracking-wider mb-1" for="correo">Correo</label>
        <input class="w-full text-sm rounded-lg border-neutral-300 focus:border-terracotta focus:ring-terracotta py-2.5 px-3 bg-neutral-50/50" id="correo" name="correo" placeholder="Ej. correo@ejemplo.com" type="email" /></div>
        <div><label class="block font-sans text-xs font-semibold text-neutral-700 uppercase tracking-wider mb-2">¿Asistirás? *</label>
        <div class="grid grid-cols-2 gap-3">
            <label class="flex items-center justify-center p-3 border rounded-lg cursor-pointer transition hover:bg-neutral-50 has-[:checked]:border-terracotta has-[:checked]:bg-terracotta/5">
                <input class="text-terracotta focus:ring-terracotta mr-2" name="asiste" required type="radio" value="si" /><span class="text-xs sm:text-sm font-medium text-neutral-800">¡Sí, con gusto!</span></label>
            <label class="flex items-center justify-center p-3 border rounded-lg cursor-pointer transition hover:bg-neutral-50 has-[:checked]:border-neutral-400 has-[:checked]:bg-neutral-100">
                <input class="text-neutral-500 focus:ring-neutral-400 mr-2" name="asiste" type="radio" value="no" /><span class="text-xs sm:text-sm font-medium text-neutral-600">No podré</span></label>
        </div></div>
        <div><label class="block font-sans text-xs font-semibold text-neutral-700 uppercase tracking-wider mb-1" for="cantidad">Pases / Acompañantes (' . $maxLabel . ')</label>
        <select class="w-full text-sm rounded-lg border-neutral-300 focus:border-terracotta focus:ring-terracotta py-2.5 px-3 bg-neutral-50/50" id="cantidad" name="cantidad">' . $optSelect . '</select></div>
        <div class="pt-2"><button class="w-full bg-[#2F483D] hover:bg-[#23372E] text-white font-serif tracking-[0.2em] uppercase text-sm font-semibold py-3.5 px-6 rounded-full shadow-md hover:shadow-xl transition transform active:scale-95 duration-150" type="submit">CLIC AQUÍ PARA CONFIRMAR</button></div>
    </form>';
}

$html = file_get_contents(__DIR__.'/../index.html');
$html = preg_replace('/<form class="space-y-4" id="rsvpForm" onsubmit="handleRSVPSubmit\(event\)">.*?<\/form>/s', $formHtml, $html);
$accentColors = ['#B85323','#934018','#C96839','#C96B44','#D49A3D','#EAA66E','#F7C04A','#EFA98F','#E8CBB8','#E2C4B1','#FBE9DC','#FAF0E8','#EBE3D3','#ECE4D5','#EFE9DE','#F5E7CC','#E9E1D2','#3C1A09','#3D1E0A','#3D291D','#523927','#5A240C','#5F3316','#3C1A09'];
$secondaryColors = ['#6B7A58','#2F483D','#7A8A67','#849474','#8E9C7B','#8FA981','#4F6449','#43543E','#23372E','#13362E','#13473D','#1C5E51','#2E4A62','#334B64','#385472','#422517'];
$bgColors = ['#FDFBF7','#F7F3EB','#FFFFFF'];
foreach ($accentColors as $c) $html = str_replace($c, 'var(--accent)', $html);
foreach ($secondaryColors as $c) $html = str_replace($c, 'var(--secondary)', $html);
foreach ($bgColors as $c) $html = str_replace($c, 'var(--bg)', $html);
$cssVars = '<style>:root{--bg:'.htmlspecialchars($pareja['color_fondo'] ?? '#FDFBF7',ENT_QUOTES,'UTF-8').';--secondary:'.htmlspecialchars($pareja['color_secundario'] ?? '#8A9A86',ENT_QUOTES,'UTF-8').';--accent:'.htmlspecialchars($pareja['color_acento'] ?? '#B76E79',ENT_QUOTES,'UTF-8').';} body{background:var(--bg);} .text-terracotta,.text-accent{color:var(--accent)!important;} .bg-ivory,.bg-ivory-surface{background:var(--bg)!important;} .text-olive,.text-olive-dark{color:var(--secondary)!important;} </style>';
$html = str_replace('</head>', $cssVars.'</head>', $html);
$html = str_replace('Gabriela', htmlspecialchars($novia,ENT_QUOTES,'UTF-8'), $html);
$html = str_replace('Sixinio', htmlspecialchars($novio,ENT_QUOTES,'UTF-8'), $html);
$inicialNovia = mb_strtoupper(mb_substr($novia, 0, 1, 'UTF-8'), 'UTF-8');
$inicialNovio = mb_strtoupper(mb_substr($novio, 0, 1, 'UTF-8'), 'UTF-8');
$html = str_replace('<span>G</span>', '<span>'.htmlspecialchars($inicialNovia,ENT_QUOTES,'UTF-8').'</span>', $html);
$html = str_replace('<span>S</span>', '<span>'.htmlspecialchars($inicialNovio,ENT_QUOTES,'UTF-8').'</span>', $html);
$html = str_replace('D I C I E M B R E', implode(' ', str_split($mes)), $html);
$html = str_replace('Sábado', $diaSemana, $html);
$html = str_replace("new Date('2026-05-09T19:30:00')", "new Date('".addslashes($fecha.'T'.($hora ?: '19:30:00'))."')", $html);
$html = str_replace('<div class="font-serif text-5xl sm:text-6xl text-terracotta font-normal leading-none tracking-tight">\n                        26\n                    </div>',
    '<div class="font-serif text-5xl sm:text-6xl text-terracotta font-normal leading-none tracking-tight">\n                        '.$dia.'\n                    </div>', $html);
$html = str_replace('2026', $anio, $html);

// Inyectar datos bancarios dinámicos
$bancoAlias = h($pareja['banco_alias'] ?? '');
$bancoNombre = h($pareja['banco_nombre'] ?? '');
$bancoCuenta = h($pareja['banco_cuenta'] ?? '');
$bancoTitular = h($pareja['banco_titular'] ?? '');

$html = str_replace('__BANCO_ALIAS__', $bancoAlias, $html);
$html = str_replace('__BANCO_NOMBRE__', $bancoNombre, $html);
$html = str_replace('__BANCO_CUENTA__', $bancoCuenta, $html);
$html = str_replace('__BANCO_TITULAR__', $bancoTitular, $html);

$bankVars = '<script>window.bankDetails = { alias: ' . json_encode($bancoAlias, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ', banco: ' . json_encode($bancoNombre, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ', cuenta: ' . json_encode($bancoCuenta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ', titular: ' . json_encode($bancoTitular, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ' };</script>';
$html = str_replace('<script data-purpose="copy-clipboard-action">', $bankVars . "\n    <script data-purpose=\"copy-clipboard-action\">", $html);

if ($mensaje || $registroCodigo) {
    $nombreAlerta = ($registroCodigo !== '') ? $nombre : (string)($invitado['nombre_completo'] ?? '');
    if ($registroCodigo !== '') {
        $swalTitulo = '¡Gracias por registrarte, '.$nombreAlerta.'!';
        $swalTexto = 'Tu respuesta ha sido registrada exitosamente. Tu código personal: '.$registroCodigo;
        $swalIcono = 'success';
    } elseif (($invitado['estado'] ?? '') === 'rechazado') {
        $swalTitulo = 'Gracias por responder, '.$nombreAlerta;
        $swalTexto = 'Lamentamos que no puedas acompañarnos físicamente, ¡pero celebraremos con tu cariño en el corazón!';
        $swalIcono = 'info';
    } else {
        $swalTitulo = '¡Gracias por confirmar, '.$nombreAlerta.'!';
        $swalTexto = 'Hemos registrado tu asistencia para '.(int)($invitado['cantidad_personas'] ?? 1).' persona(s). ¡Será una noche inolvidable!';
        $swalIcono = 'success';
    }
    $codigoInvi = $registroCodigo !== '' ? $registroCodigo : ($invitado['codigo_invitacion'] ?? '');
    $pdfUrl = BASE_URL . '/admin/qr_pdf.php?codigo=' . urlencode($codigoInvi);
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
    $swal = '<script>window.addEventListener("load",function(){if(window.Swal){Swal.fire({'
        .'title:'.json_encode($swalTitulo, $jsonFlags).','
        .'text:'.json_encode($swalTexto, $jsonFlags).','
        .'icon:"'.$swalIcono.'",'
        .'confirmButtonText:"Cerrar",'
        .'confirmButtonColor:"#2F483D",'
        .'showDenyButton:true,'
        .'denyButtonText:"📄 Descargar PDF con QR",'
        .'denyButtonColor:"#2F483D",'
        .'denyButtonText:"📄 Descargar PDF con QR"'
        .'}).then(function(result){ if (result.isDenied) { window.open("'.$pdfUrl.'","_blank"); } });}});</script>';
    $html = str_replace('</body>', $swal.'</body>', $html);
}
echo $html;
