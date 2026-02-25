<?php
include("../config/conexion.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
  header("Location: ../dashboard/index.php");
  exit();
}

// Crear
if ($_POST) {
  $nombre = $_POST['nombre'];
  $descripcion = $_POST['descripcion'];
  $costo = intval($_POST['costo']);
  $stock = intval($_POST['stock']);

  $stmt = $conn->prepare("INSERT INTO recompensas (nombre, descripcion, costo, stock) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("ssii", $nombre, $descripcion, $costo, $stock);
  $stmt->execute();
}

// Listar
$lista = $conn->query("SELECT * FROM recompensas ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Admin - Recompensas</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>

<div class="layout">
  <?php include("../dashboard/sidebar.php"); ?>

  <main class="main-content">
    <h1>🎁 Administrar Recompensas</h1>

    <form method="POST" style="margin-bottom:20px;">
      <input type="text" name="nombre" placeholder="Nombre" required>
      <input type="text" name="descripcion" placeholder="Descripción" required>
      <input type="number" name="costo" placeholder="Costo en puntos" required>
      <input type="number" name="stock" placeholder="Stock" required>
      <button class="btn">Agregar</button>
    </form>

    <table>
      <tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Costo</th>
        <th>Stock</th>
      </tr>
      <?php while ($r = $lista->fetch_assoc()): ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['nombre']) ?></td>
          <td><?= $r['costo'] ?></td>
          <td><?= $r['stock'] ?></td>
        </tr>
      <?php endwhile; ?>
    </table>

  </main>
</div>

</body>
</html>
