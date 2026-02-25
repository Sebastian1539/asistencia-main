// /excel/exportar.php
<?php
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
include("../config/conexion.php");

$sheet = new Spreadsheet();
$hoja = $sheet->getActiveSheet();
$hoja->fromArray(["ID","Usuario","Fecha","Estado"], NULL, "A1");

$res = $conn->query("SELECT a.id, u.nombre, a.fecha, a.estado
                     FROM asistencias a
                     JOIN usuarios u ON a.usuario_id = u.id");

$fila = 2;
while ($row = $res->fetch_assoc()) {
  $hoja->fromArray($row, NULL, "A".$fila++);
}

$writer = new Xlsx($sheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="asistencias.xlsx"');
$writer->save("php://output");
