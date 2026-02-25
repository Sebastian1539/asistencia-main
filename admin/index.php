<?php
include("../config/conexion.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] != "admin") {
  header("Location: ../dashboard.php");
  exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Admin</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>

  <?php include("../dashboard/sidebar.php"); ?>

  <main class="main-content">
    <h1>🛠 Panel de Administración</h1>

    <div class="cards-admin">
      <a href="usuarios.php" class="card">👥 Usuarios</a>
      <a href="sedes.php" class="card">🏥 Sedes</a>
      <a href="encuestas.php" class="card">📋 Encuestas DISC</a>
      <a href="reportes.php" class="card">📤 Exportar Excel</a>
      <a href="importar_excel.php" class="card">📥 Importar Excel</a>
    </div>
  </main>
</div>

</body>
</html>
