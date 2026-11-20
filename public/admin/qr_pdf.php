<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';
$pdo = pdo();

$codigo = $_GET['codigo'] ?? '';
if (!$codigo) { http_response_code(400); echo 'Código requerido'; exit; }

$st = $pdo->prepare('SELECT i.*, p.* FROM invitados i JOIN parejas p ON i.id_pareja=p.id_pareja WHERE i.codigo_invitacion=?');
$st->execute([$codigo]);
$invitado = $st->fetch();
if (!$invitado) { http_response_code(404); echo 'Invitado no encontrado'; exit; }

// Generar QR
$qrData = $codigo;
$qrPath = sys_get_temp_dir() . '/qr_' . $codigo . '.png';
$writer = new \Endroid\QrCode\Writer\PngWriter();
$qrCode = $writer->write($qrData, 200);
$qrCode->saveToFile($qrPath);

// Mapa de la iglesia
$mapaIglesia = null;
if ($invitado['lugar_ceremonia']) {
    $mapaIglesia = geocodeMap($invitado['lugar_ceremonia']);
}
// Mapa del colegio sakura
$mapaFiesta = null;
if ($invitado['lugar_fiesta']) {
    $mapaFiesta = geocodeMap($invitado['lugar_fiesta']);
}

// Construir PDF
$html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
body { font-family: Arial, sans-serif; text-align: center; padding: 20px; color: #333; }
.title { font-size: 26px; font-weight: bold; margin-bottom: 10px; color: #2F483D; }
.subtitle { font-size: 16px; color: #666; margin-bottom: 30px; }
.guest-name { font-size: 20px; font-weight: bold; margin: 20px 0; color: #444; }
.details { text-align: left; max-width: 480px; margin: 0 auto 30px auto; font-size: 14px; color: #777; line-height: 1.6; }
.details p { margin: 6px 0; }
.qr-container { margin: 30px auto; width: 200px; height: 200px; border: 2px solid #ddd; display: flex; align-items: center; justify-content: center; }
.qr-container img { width: 180px; height: 180px; }
.lugares-section { text-align: left; max-width: 480px; margin: 30px auto; border-top: 2px solid #eee; padding-top: 25px; }
.lugar-item { margin-bottom: 22px; border: 1px solid #ddd; border-radius: 8px; padding: 12px 16px; background: #fafafa; }
.lugar-titulo { font-weight: bold; color: #2F483D; font-size: 15px; margin-bottom: 6px; display: block; }
.lugar-direccion { font-size: 13px; color: #555; margin-bottom: 8px; word-break: break-word; }
.lugar-mapa { margin-top: 8px; }
.lugar-mapa img { width: 100%; max-width: 440px; height: auto; border-radius: 6px; border: 1px solid #ccc; }
.lugar-fallback { font-size: 13px; color: #888; margin-top: 8px; }
.lugar-link { color: #2F483D; text-decoration: underline; }
.lugar-link:hover { text-decoration: none; }
.footer { margin-top: 40px; font-size: 12px; color: #999; }
.qr-hint { margin-top: 12px; font-size: 13px; color: #555; }
</style></head><body>
<div class="title">¡Gracias por confirmar tu asistencia!</div>
<div class="subtitle">' . h($invitado['nombre_pareja']) . '</div>
<div class="guest-name">' . h($invitado['nombre_completo']) . '</div>
<div class="details">
    <p><strong>Fecha:</strong> ' . h($invitado['fecha_boda']) . '</p>
    <p><strong>Hora:</strong> ' . h($invitado['hora_boda']) . '</p>
    <p><strong>Lugar:</strong> ' . h($invitado['lugar_boda']) . '</p>
    <p><strong>Personas:</strong> ' . (int)$invitado['cantidad_personas'] . '</p>
    <p><strong>Código:</strong> ' . h($invitado['codigo_invitacion']) . '</p>
</div>

<div class="lugares-section">
    <div class="lugar-item">
        <span class="lugar-titulo">🏛️ Iglesia - Ceremonia</span>
        <span class="lugar-direccion">' . ($invitado['lugar_ceremonia'] ? h($invitado['lugar_ceremonia']) : 'Dirección no disponible') . '</span>
        <div class="lugar-mapa">' . ($mapaIglesia['img'] ? '<img src="' . $mapaIglesia['img'] . '" alt="Mapa iglesia">' : '<p class="lugar-fallback">Mapa no disponible. <a href="' . $mapaIglesia['mapsUrl'] . '" class="lugar-link" target="_blank">Abrir en Google Maps</a></p>') . '</div>
    </div>
    <div class="lugar-item">
        <span class="lugar-titulo">🎓 Colegio Sakura - Fiesta</span>
        <span class="lugar-direccion">' . ($invitado['lugar_fiesta'] ? h($invitado['lugar_fiesta']) : 'Dirección no disponible') . '</span>
        <div class="lugar-mapa">' . ($mapaFiesta['img'] ? '<img src="' . $mapaFiesta['img'] . '" alt="Mapa colegio sakura">' : '<p class="lugar-fallback">Mapa no disponible. <a href="' . $mapaFiesta['mapsUrl'] . '" class="lugar-link" target="_blank">Abrir en Google Maps</a></p>') . '</div>
    </div>
</div>

<div class="qr-container"><img src="' . $qrPath . '" alt="QR"></div>
<div class="qr-hint">Escanea este QR en el evento para marcar tu llegada.</div>
<div class="footer">Invitaciones de Boda</div>
</body></html>';

$dompdf = new \Dompdf\Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('invitacion_' . $codigo . '.pdf', ['Attachment' => false]);
unlink($qrPath);
exit;