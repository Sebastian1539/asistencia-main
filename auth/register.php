<?php
include("../config/conexion.php");

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $nombre = trim($_POST["nombre"]);
  $email = trim($_POST["email"]);
  $password = $_POST["password"];
  $sede_id = $_POST["sede_id"];

  if (strlen($password) < 6) {
    $mensaje = "❌ La contraseña debe tener al menos 6 caracteres";
  } else {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol, sede_id) VALUES (?, ?, ?, 'usuario', ?)");
    $stmt->bind_param("sssi", $nombre, $email, $hash, $sede_id);

    if ($stmt->execute()) {
      // Crear registro de puntos
      $uid = $conn->insert_id;
      $conn->query("INSERT INTO puntos (usuario_id, total) VALUES ($uid, 0)");

      header("Location: login.php");
      exit();
    } else {
      $mensaje = "❌ Error: el correo ya existe";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
  <h2>Registro</h2>

  <?php if ($mensaje): ?>
    <p style="color:red;"><?= $mensaje ?></p>
  <?php endif; ?>

  <form method="POST">
    <input type="text" name="nombre" placeholder="Nombre completo" required><br>
    <input type="email" name="email" placeholder="Correo" required><br>
    <input type="password" name="password" placeholder="Contraseña" required><br>

    <select name="sede_id" required>
      <option value="">Seleccione sede</option>
      <?php
      $sedes = $conn->query("SELECT id, nombre FROM sedes WHERE activo = 1");
      while ($s = $sedes->fetch_assoc()):
      ?>
        <option value="<?= $s['id'] ?>"><?= $s['nombre'] ?></option>
      <?php endwhile; ?>
    </select><br>

    <button type="submit">Registrarme</button>
  </form>

  <p><a href="login.php">Volver al login</a></p>
</body>
</html>
