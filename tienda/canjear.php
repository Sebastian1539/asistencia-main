<?php
include("../config/conexion.php");

if (!isset($_SESSION["user_id"])) {
  header("Location: ../auth/login.php");
  exit();
}

$uid = $_SESSION["user_id"];
$recompensa_id = intval($_POST['recompensa_id']);

// Obtener datos
$rRes = $conn->query("SELECT costo, stock FROM recompensas WHERE id = $recompensa_id AND activo = 1");
$r = $rRes->fetch_assoc();

$pRes = $conn->query("SELECT total FROM puntos WHERE usuario_id = $uid");
$puntos = $pRes->fetch_assoc()['total'];

if (!$r || $r['stock'] <= 0 || $puntos < $r['costo']) {
  header("Location: index.php?error=1");
  exit();
}

// Transacción simple
$conn->begin_transaction();

$conn->query("UPDATE puntos SET total = total - {$r['costo']} WHERE usuario_id = $uid");
$conn->query("UPDATE recompensas SET stock = stock - 1 WHERE id = $recompensa_id");
$conn->query("INSERT INTO canjes (usuario_id, recompensa_id) VALUES ($uid, $recompensa_id)");

$conn->commit();

header("Location: index.php?ok=1");
exit();
