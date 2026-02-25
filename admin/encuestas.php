<?php
include("../config/conexion.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
  header("Location: ../dashboard/index.php");
  exit();
}

// Crear encuesta
if (isset($_POST['crear_encuesta'])) {
  $titulo = $_POST['titulo'];
  $desc = $_POST['descripcion'];

  $conn->query("UPDATE encuestas SET activo = 0");
  $stmt = $conn->prepare("INSERT INTO encuestas (titulo, descripcion, activo) VALUES (?, ?, 1)");
  $stmt->bind_param("ss", $titulo, $desc);
  $stmt->execute();
}

// Crear pregunta
if (isset($_POST['crear_pregunta'])) {
  $encuesta_id = intval($_POST['encuesta_id']);
  $pregunta = $_POST['pregunta'];
  $tipo = $_POST['tipo'];

  $stmt = $conn->prepare("INSERT INTO preguntas (encuesta_id, pregunta, tipo) VALUES (?, ?, ?)");
  $stmt->bind_param("iss", $encuesta_id, $pregunta, $tipo);
  $stmt->execute();
}

// Listar encuestas
$encuestas = $conn->query("SELECT * FROM encuestas ORDER BY id DESC");
$preguntas = $conn->query("SELECT p.*, e.titulo FROM preguntas p JOIN encuestas e ON p.encuesta_id = e.id ORDER BY p.id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Admin Encuestas</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>

  <?php include("../dashboard/sidebar.php"); ?>

  <main class="main-content">
    <h1>🧠 Administrar Encuestas DISC</h1>

    <h3>Crear encuesta</h3>
    <form method="POST">
      <input type="text" name="titulo" placeholder="Título" required>
      <input type="text" name="descripcion" placeholder="Descripción" required>
      <button name="crear_encuesta">Crear encuesta</button>
    </form>

    <h3>Agregar pregunta</h3>
    <form method="POST">
      <select name="encuesta_id" required>
        <?php while ($e = $encuestas->fetch_assoc()): ?>
          <option value="<?= $e['id'] ?>"><?= $e['titulo'] ?></option>
        <?php endwhile; ?>
      </select>

      <input type="text" name="pregunta" placeholder="Pregunta" required>

      <select name="tipo" required>
        <option value="D">Dominante</option>
        <option value="I">Influyente</option>
        <option value="S">Estable</option>
        <option value="C">Cumplidor</option>
      </select>

      <button name="crear_pregunta">Agregar</button>
    </form>

    <h3>Preguntas creadas</h3>
    <table>
      <tr>
        <th>ID</th>
        <th>Encuesta</th>
        <th>Pregunta</th>
        <th>Tipo</th>
      </tr>
      <?php while ($p = $preguntas->fetch_assoc()): ?>
        <tr>
          <td><?= $p['id'] ?></td>
          <td><?= htmlspecialchars($p['titulo']) ?></td>
          <td><?= htmlspecialchars($p['pregunta']) ?></td>
          <td><?= $p['tipo'] ?></td>
        </tr>
      <?php endwhile; ?>
    </table>

  </main>
</div>

</body>
</html>
