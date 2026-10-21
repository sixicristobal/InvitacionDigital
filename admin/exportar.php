<?php
require_once __DIR__ . '/../includes/functions.php';
requiereLogin();
require __DIR__ . '/../vendor/autoload.php';
$pdo = pdo();
$parejaSel = isset($_GET['pareja']) ? (int)$_GET['pareja'] : 0;
$formato = $_GET['formato'] ?? 'excel';

$sql = 'SELECT i.id_invitado, p.nombre_pareja, i.nombre_completo, i.telefono, i.correo, i.cantidad_personas, i.estado, i.mensaje, i.fecha_confirmacion
        FROM invitados i JOIN parejas p ON i.id_pareja=p.id_pareja';
if ($parejaSel) $sql .= ' WHERE i.id_pareja='.$parejaSel;
$sql .= ' ORDER BY p.id_pareja, i.id_invitado';
$rows = $pdo->query($sql)->fetchAll();

if ($formato === 'excel') {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $headers = ['ID','Pareja','Nombre','Teléfono','Correo','Personas','Estado','Mensaje','Fecha confirmación'];
    $col = 'A';
    foreach ($headers as $h) { $sheet->setCellValue($col.'1', $h); $col++; }
    $r = 2;
    foreach ($rows as $row) {
        $sheet->setCellValue('A'.$r, $row['id_invitado']);
        $sheet->setCellValue('B'.$r, $row['nombre_pareja']);
        $sheet->setCellValue('C'.$r, $row['nombre_completo']);
        $sheet->setCellValue('D'.$r, $row['telefono']);
        $sheet->setCellValue('E'.$r, $row['correo']);
        $sheet->setCellValue('F'.$r, $row['cantidad_personas']);
        $sheet->setCellValue('G'.$r, $row['estado']);
        $sheet->setCellValue('H'.$r, $row['mensaje']);
        $sheet->setCellValue('I'.$r, $row['fecha_confirmacion']);
        $r++;
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="invitados.xlsx"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} else {
    $html = '<h1>Listado de Invitados</h1><table border="1" cellpadding="4"><tr>';
    foreach (['ID','Pareja','Nombre','Tel','Correo','Personas','Estado','Mensaje','Fecha'] as $h) $html .= '<th>'.$h.'</th>';
    $html .= '</tr>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach (['id_invitado','nombre_pareja','nombre_completo','telefono','correo','cantidad_personas','estado','mensaje','fecha_confirmacion'] as $k) {
            $html .= '<td>'.htmlspecialchars((string)$row[$k]).'</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','landscape');
    $dompdf->render();
    $dompdf->stream('invitados.pdf', ['Attachment'=>true]);
    exit;
}