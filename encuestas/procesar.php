<?php
include("../config/conexion.php");

if (!isset($_SESSION["user_id"])) {
  header("Location: ../auth/login.php");
  exit();
}

$uid = $_SESSION["user_id"];
$respuestas = $_POST['respuestas'] ?? [];

if (empty($respuestas)) {
  header("Location: disc.php");
  exit();
}

// Borrar respuestas anteriores del usuario (para poder rehacer la encuesta)
$conn->query("DELETE FROM respuestas WHERE usuario_id = $uid");

// Inicializar conteo DISC
$D = $I = $S = $C = 0;

foreach ($respuestas as $pregunta_id => $valor) {
  // Obtener tipo de pregunta
  $res = $conn->query("SELECT tipo FROM preguntas WHERE id = $pregunta_id");
  $tipo = $res->fetch_assoc()['tipo'];

  // Guardar respuesta
  $stmt = $conn->prepare("INSERT INTO respuestas (usuario_id, pregunta_id, valor) VALUES (?, ?, ?)");
  $stmt->bind_param("iii", $uid, $pregunta_id, $valor);
  $stmt->execute();

  // Sumar a DISC
  if ($tipo == 'D') $D += $valor;
  if ($tipo == 'I') $I += $valor;
  if ($tipo == 'S') $S += $valor;
  if ($tipo == 'C') $C += $valor;
}

// Guardar resultados (si ya existe, actualizar)
$check = $conn->query("SELECT id FROM resultados_disc WHERE usuario_id = $uid");

if ($check->num_rows > 0) {
  $conn->query("UPDATE resultados_disc SET dominante=$D, influyente=$I, estable=$S, cumplidor=$C WHERE usuario_id=$uid");
} else {
  $conn->query("INSERT INTO resultados_disc (usuario_id, dominante, influyente, estable, cumplidor)
                VALUES ($uid, $D, $I, $S, $C)");
}

header("Location: resultado.php");
exit();
