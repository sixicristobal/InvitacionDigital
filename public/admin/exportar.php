<?php
require_once __DIR__ . '/../../includes/functions.php';
requiereLogin();
require __DIR__ . '/../../vendor/autoload.php';
$pdo = pdo();
$parejaSel = isset($_GET['pareja']) ? (int)$_GET['pareja'] : 0;
$formato = $_GET['formato'] ?? 'excel';

$sql = 'SELECT i.id_invitado, p.nombre_pareja, i.nombre_completo, i.telefono, i.correo, i.cantidad_personas, i.mesa, i.llego, i.estado, i.mensaje, i.fecha_confirmacion, i.cancion_sugerida, i.restriccion_alimentaria
        FROM invitados i JOIN parejas p ON i.id_pareja=p.id_pareja';
if ($parejaSel) $sql .= ' WHERE i.id_pareja='.$parejaSel;
$sql .= ' ORDER BY p.id_pareja, i.id_invitado';
$rows = $pdo->query($sql)->fetchAll();

if ($formato === 'excel') {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $headers = ['ID','Pareja','Nombre','Teléfono','Correo','Personas','Mesa','¿Llegó?','Estado','Mensaje','Fecha confirmación','Canción sugerida','Restricción alimentaria'];
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
        $sheet->setCellValue('G'.$r, $row['mesa']);
        $sheet->setCellValue('H'.$r, $row['llego'] ? 'Sí' : 'No');
        $sheet->setCellValue('I'.$r, $row['estado']);
        $sheet->setCellValue('J'.$r, $row['mensaje']);
        $sheet->setCellValue('K'.$r, $row['fecha_confirmacion']);
        $sheet->setCellValue('L'.$r, $row['cancion_sugerida'] ?? '');
        $sheet->setCellValue('M'.$r, $row['restriccion_alimentaria'] ?? '');
        $r++;
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="invitados.xlsx"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} else {
    $html = '<h1>Listado de Invitados</h1><table border="1" cellpadding="4"><tr>';
    foreach (['ID','Pareja','Nombre','Tel','Correo','Personas','Mesa','¿Llegó?','Estado','Mensaje','Fecha','Canción','Restricción'] as $h) $html .= '<th>'.$h.'</th>';
    $html .= '</tr>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach (['id_invitado','nombre_pareja','nombre_completo','telefono','correo','cantidad_personas','mesa','llego','estado','mensaje','fecha_confirmacion','cancion_sugerida','restriccion_alimentaria'] as $k) {
            $val = $row[$k];
            if ($k === 'llego') $val = $val ? 'Sí' : 'No';
            $html .= '<td>'.htmlspecialchars((string)$val).'</td>';
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