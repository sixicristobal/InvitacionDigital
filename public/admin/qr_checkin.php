<?php
require_once __DIR__ . '/../../includes/functions.php';
$pdo = pdo();

header('Content-Type: application/json');

$codigo = trim($_POST['codigo'] ?? '');
if (!$codigo) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Código requerido']);
    exit;
}

$st = $pdo->prepare('SELECT i.*, p.nombre_pareja FROM invitados i JOIN parejas p ON i.id_pareja=p.id_pareja WHERE i.codigo_invitacion=?');
$st->execute([$codigo]);
$invitado = $st->fetch();

if (!$invitado) {
    echo json_encode(['ok' => false, 'msg' => 'Código no encontrado']);
    exit;
}

if ($invitado['llego']) {
    echo json_encode(['ok' => false, 'msg' => 'Este invitado ya marcó su llegada']);
    exit;
}

$pdo->prepare('UPDATE invitados SET llego=1, hora_llegada=NOW() WHERE id_invitado=?')->execute([$invitado['id_invitado']]);

echo json_encode([
    'ok' => true,
    'msg' => 'Llegada registrada',
    'invitado' => $invitado['nombre_completo'],
    'pareja' => $invitado['nombre_pareja'],
    'personas' => (int)$invitado['cantidad_personas'],
    'mesa' => $invitado['mesa'] ?: '—'
]);
exit;