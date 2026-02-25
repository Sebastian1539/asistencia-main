<?php
include("../config/conexion.php");
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=reporte_asistencias.xls");

$res = $conn->query("
  SELECT u.nombre, a.fecha, a.hora_entrada, a.puntos
  FROM asistencias a
  JOIN usuarios u ON a.usuario_id = u.id
");

echo "Nombre\tFecha\tHora\tPuntos\n";
while ($row = $res->fetch_assoc()) {
  echo "{$row['nombre']}\t{$row['fecha']}\t{$row['hora_entrada']}\t{$row['puntos']}\n";
}
exit();
