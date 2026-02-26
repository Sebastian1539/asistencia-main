<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../auth/login.php"); exit();
}

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;

$id = intval($_GET['id'] ?? 0);
if ($id === 0) { header("Location: ../dashboard/index.php"); exit(); }

// ============================================================
// Datos del usuario
// ============================================================
$stmt = $conn->prepare("
    SELECT u.id, u.nombre, u.apodo, u.email, u.rol, u.codigo_biometrico,
           s.nombre AS sede
    FROM usuarios u
    LEFT JOIN sedes s ON s.id = u.sede_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) { header("Location: ../dashboard/index.php"); exit(); }

// Puntos
$stmt = $conn->prepare("SELECT total FROM puntos WHERE usuario_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$puntos_total = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Horario (tolerancia)
$config = $conn->query("SELECT * FROM configuracion_horarios WHERE activo = 1 LIMIT 1")->fetch_assoc();
$hora_entrada       = $config['hora_entrada']       ?? '08:00:00';
$minutos_tolerancia = $config['minutos_tolerancia'] ?? 15;
$hora_tope          = date('H:i:s', strtotime($hora_entrada . ' + ' . $minutos_tolerancia . ' minutes'));

// Marcaciones agrupadas por día
$stmt = $conn->prepare("
    SELECT
        m.fecha,
        MIN(m.hora) AS hora_entrada,
        MAX(CASE WHEN m.tipo_evento = 'salida' THEN m.hora END) AS hora_salida,
        COUNT(CASE WHEN m.tipo_evento = 'entrada' THEN 1 END) AS total_entradas,
        COUNT(CASE WHEN m.tipo_evento = 'salida'  THEN 1 END) AS total_salidas
    FROM marcaciones m
    WHERE m.usuario_id = ?
    GROUP BY m.fecha
    ORDER BY m.fecha DESC
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$filas = [];
$stats = ['total'=>0,'temprano'=>0,'tarde'=>0,'falto'=>0,'pts_total'=>0];

while ($dia = $result->fetch_assoc()) {
    $stats['total']++;
    $estado = 'Sin marcación';
    $puntos = 0;

    if (!empty($dia['hora_entrada'])) {
        if (strtotime($dia['hora_entrada']) <= strtotime($hora_tope)) {
            $estado = 'Temprano'; $stats['temprano']++; $puntos = 5;
        } else {
            $estado = 'Tarde'; $stats['tarde']++; $puntos = 3;
        }
    } else {
        $stats['falto']++;
    }
    $stats['pts_total'] += $puntos;

    $diasSemana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $filas[] = [
        'fecha'          => date('d/m/Y', strtotime($dia['fecha'])),
        'dia'            => $diasSemana[date('w', strtotime($dia['fecha']))],
        'hora_entrada'   => $dia['hora_entrada']  ? date('H:i', strtotime($dia['hora_entrada']))  : '—',
        'hora_salida'    => $dia['hora_salida']   ? date('H:i', strtotime($dia['hora_salida']))   : '—',
        'estado'         => $estado,
        'puntos'         => $puntos,
        'total_entradas' => $dia['total_entradas'],
        'total_salidas'  => $dia['total_salidas'],
    ];
}

// ============================================================
// Construir Excel con PhpSpreadsheet
// ============================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Asistencias');

// --- Colores ---
$COLOR_HEADER_BG   = '4F46E5';  // indigo oscuro
$COLOR_HEADER_FG   = 'FFFFFF';
$COLOR_TITULO_BG   = '667EEA';
$COLOR_SUBHDR_BG   = 'EEF2FF';
$COLOR_TEMPRANO_BG = 'D1FAE5'; $COLOR_TEMPRANO_FG = '065F46';
$COLOR_TARDE_BG    = 'FEF3C7'; $COLOR_TARDE_FG    = '92400E';
$COLOR_FALTO_BG    = 'FEE2E2'; $COLOR_FALTO_FG    = '991B1B';
$COLOR_FILA_PAR    = 'F8F9FF';
$COLOR_BORDE       = 'D1D5DB';
$COLOR_PUNTOS      = '667EEA';
$COLOR_RESUMEN_BG  = 'EEF2FF';

// ============================================================
// BLOQUE 1: Encabezado del reporte (filas 1-7)
// ============================================================
$sheet->mergeCells('A1:H1');
$sheet->setCellValue('A1', 'REPORTE DE ASISTENCIAS');
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['name'=>'Arial','bold'=>true,'size'=>16,'color'=>['rgb'=>$COLOR_HEADER_FG]],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'color'=>['rgb'=>$COLOR_HEADER_BG]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(32);

$sheet->mergeCells('A2:H2');
$sheet->setCellValue('A2', 'Clínica Gamificación — ' . date('d/m/Y H:i'));
$sheet->getStyle('A2')->applyFromArray([
    'font'      => ['name'=>'Arial','size'=>10,'color'=>['rgb'=>$COLOR_HEADER_FG],'italic'=>true],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'color'=>['rgb'=>$COLOR_TITULO_BG]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

// Fila vacía
$sheet->mergeCells('A3:H3');
$sheet->getStyle('A3')->applyFromArray([
    'fill' => ['fillType'=>Fill::FILL_SOLID,'color'=>['rgb'=>'F3F4F6']],
]);

// Datos del empleado
$infoStyle = [
    'font'      => ['name'=>'Arial','size'=>10],
    'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
];
$labelStyle = array_merge_recursive($infoStyle, [
    'font' => ['bold'=>true,'color'=>['rgb'=>'374151']],
    'fill' => ['fillType'=>Fill::FILL_SOLID,'color'=>['rgb'=>'EEF2FF']],
]);

$info = [
    ['A4','B4', 'Empleado:',         $usuario['nombre'] . (!empty($usuario['apodo']) ? ' ("'.$usuario['apodo'].'")' : '')],
    ['A5','B5', 'Email:',            $usuario['email']],
    ['A6','B6', 'Sede:',             $usuario['sede'] ?? 'Sin sede'],
    ['A7','B7', 'Cód. Biométrico:',  $usuario['codigo_biometrico'] ?? '—'],
    ['D4','E4', 'Total marcaciones:', $stats['total']],
    ['D5','E5', 'A tiempo:',          $stats['temprano'] . ' día(s)'],
    ['D6','E6', 'Tarde:',             $stats['tarde'] . ' día(s)'],
    ['D7','E7', 'Puntos ganados:',    $stats['pts_total'] . ' pts'],
];

foreach ($info as [$colL, $colV, $label, $value]) {
    $sheet->setCellValue($colL, $label);
    $sheet->setCellValue($colV, $value);
    $sheet->getStyle($colL)->applyFromArray($labelStyle);
    $sheet->getStyle($colV)->applyFromArray($infoStyle);
}
$sheet->mergeCells('B4:C4'); $sheet->mergeCells('B5:C5');
$sheet->mergeCells('B6:C6'); $sheet->mergeCells('B7:C7');
$sheet->mergeCells('E4:H4'); $sheet->mergeCells('E5:H5');
$sheet->mergeCells('E6:H6'); $sheet->mergeCells('E7:H7');

for ($r = 4; $r <= 7; $r++) $sheet->getRowDimension($r)->setRowHeight(20);

// ============================================================
// BLOQUE 2: Encabezados de la tabla (fila 9)
// ============================================================
$fila_inicio = 9;
$headers = ['#', 'Fecha', 'Día', 'Entrada', 'Salida', 'Estado', 'Puntos', 'Marcaciones'];
$cols    = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

foreach ($headers as $i => $h) {
    $cell = $cols[$i] . $fila_inicio;
    $sheet->setCellValue($cell, $h);
    $sheet->getStyle($cell)->applyFromArray([
        'font'      => ['name'=>'Arial','bold'=>true,'size'=>10,'color'=>['rgb'=>$COLOR_HEADER_FG]],
        'fill'      => ['fillType'=>Fill::FILL_SOLID,'color'=>['rgb'=>$COLOR_HEADER_BG]],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'3730A3']]],
    ]);
}
$sheet->getRowDimension($fila_inicio)->setRowHeight(22);

// ============================================================
// BLOQUE 3: Datos (fila 10 en adelante)
// ============================================================
$fila = $fila_inicio + 1;
foreach ($filas as $idx => $d) {
    $esPar = ($idx % 2 === 0);

    // Color base de fila
    $bgBase = $esPar ? 'FFFFFF' : $COLOR_FILA_PAR;

    $rowData = [
        'A' => $idx + 1,
        'B' => $d['fecha'],
        'C' => $d['dia'],
        'D' => $d['hora_entrada'],
        'E' => $d['hora_salida'],
        'F' => $d['estado'],
        'G' => $d['puntos'] > 0 ? '+' . $d['puntos'] . ' pts' : '0',
        'H' => 'E:'.$d['total_entradas'].' / S:'.$d['total_salidas'],
    ];

    foreach ($rowData as $col => $val) {
        $cell = $col . $fila;
        $sheet->setCellValue($cell, $val);

        $style = [
            'font'      => ['name'=>'Arial','size'=>9],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'color'=>['rgb'=>$bgBase]],
            'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_HAIR,'color'=>['rgb'=>$COLOR_BORDE]]],
        ];

        // Centrar columnas específicas
        if (in_array($col, ['A','C','D','E','G','H'])) {
            $style['alignment']['horizontal'] = Alignment::HORIZONTAL_CENTER;
        }

        // Color especial para la columna Estado (F)
        if ($col === 'F') {
            switch ($d['estado']) {
                case 'Temprano':
                    $style['fill']['color']['rgb'] = $COLOR_TEMPRANO_BG;
                    $style['font']['bold'] = true;
                    $style['font']['color'] = ['rgb' => $COLOR_TEMPRANO_FG];
                    break;
                case 'Tarde':
                    $style['fill']['color']['rgb'] = $COLOR_TARDE_BG;
                    $style['font']['bold'] = true;
                    $style['font']['color'] = ['rgb' => $COLOR_TARDE_FG];
                    break;
                default:
                    $style['fill']['color']['rgb'] = $COLOR_FALTO_BG;
                    $style['font']['bold'] = true;
                    $style['font']['color'] = ['rgb' => $COLOR_FALTO_FG];
                    break;
            }
            $style['alignment']['horizontal'] = Alignment::HORIZONTAL_CENTER;
        }

        // Color especial para columna Puntos (G)
        if ($col === 'G' && $d['puntos'] > 0) {
            $style['font']['bold'] = true;
            $style['font']['color'] = ['rgb' => $COLOR_PUNTOS];
        }

        $sheet->getStyle($cell)->applyFromArray($style);
    }

    $sheet->getRowDimension($fila)->setRowHeight(18);
    $fila++;
}

// ============================================================
// BLOQUE 4: Fila de resumen al final
// ============================================================
$fila++; // fila vacía de separación
$sheet->mergeCells("A{$fila}:E{$fila}");
$sheet->setCellValue("A{$fila}", 'RESUMEN TOTAL');
$sheet->getStyle("A{$fila}")->applyFromArray([
    'font'      => ['name'=>'Arial','bold'=>true,'size'=>10,'color'=>['rgb'=>$COLOR_HEADER_FG]],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'color'=>['rgb'=>$COLOR_HEADER_BG]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

$fila++;
$resumen = [
    ['Total días con marcación', $stats['total']],
    ['A tiempo (Temprano)',       $stats['temprano']],
    ['Tarde',                     $stats['tarde']],
    ['Sin marcación',             $stats['falto']],
    ['Puntos por asistencias',    $stats['pts_total'] . ' pts'],
    ['Puntos totales del usuario',$puntos_total . ' pts'],
];

foreach ($resumen as $i => [$label, $value]) {
    $esPar = ($i % 2 === 0);
    $bg = $esPar ? $COLOR_RESUMEN_BG : 'FFFFFF';

    $sheet->setCellValue("A{$fila}", $label);
    $sheet->setCellValue("B{$fila}", $value);
    $sheet->mergeCells("A{$fila}:E{$fila}");

    $sheet->getStyle("A{$fila}")->applyFromArray([
        'font'      => ['name'=>'Arial','size'=>9,'bold'=>true,'color'=>['rgb'=>'374151']],
        'fill'      => ['fillType'=>Fill::FILL_SOLID,'color'=>['rgb'=>$bg]],
        'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_HAIR,'color'=>['rgb'=>$COLOR_BORDE]]],
    ]);
    $sheet->getStyle("B{$fila}")->applyFromArray([
        'font'      => ['name'=>'Arial','size'=>9,'bold'=>true,'color'=>['rgb'=>$COLOR_PUNTOS]],
        'fill'      => ['fillType'=>Fill::FILL_SOLID,'color'=>['rgb'=>$bg]],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
        'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_HAIR,'color'=>['rgb'=>$COLOR_BORDE]]],
    ]);
    $fila++;
}

// ============================================================
// Anchos de columna
// ============================================================
$anchos = ['A'=>6, 'B'=>13, 'C'=>13, 'D'=>10, 'E'=>10, 'F'=>16, 'G'=>11, 'H'=>16];
foreach ($anchos as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Freeze pane en la fila de datos
$sheet->freezePane('A' . ($fila_inicio + 1));

// Autofilter sobre encabezados
$sheet->setAutoFilter('A' . $fila_inicio . ':H' . ($fila_inicio + count($filas)));

// ============================================================
// Propiedades del archivo
// ============================================================
$spreadsheet->getProperties()
    ->setCreator('Clínica Gamificación')
    ->setTitle('Asistencias — ' . $usuario['nombre'])
    ->setDescription('Reporte generado el ' . date('d/m/Y H:i'));

// ============================================================
// Enviar al navegador
// ============================================================
$nombreArchivo = 'Asistencias_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $usuario['nombre']) . '_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();