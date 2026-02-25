<?php
include("../config/conexion.php");

if ($_FILES) {
  $file = $_FILES['excel']['tmp_name'];
  $data = array_map('str_getcsv', file($file));

  foreach ($data as $i => $row) {
    if ($i == 0) continue; // encabezado
    $usuario_id = $row[0];
    $fecha = $row[1];
    $hora = $row[2];
    $puntos = $row[3];

    $conn->query("INSERT INTO asistencias (usuario_id, fecha, hora_entrada, puntos)
      VALUES ('$usuario_id','$fecha','$hora','$puntos')");
  }
  echo "Importado correctamente";
}
?>

<form method="POST" enctype="multipart/form-data">
  <input type="file" name="excel" accept=".csv" required>
  <button>Importar Excel</button>
</form>
