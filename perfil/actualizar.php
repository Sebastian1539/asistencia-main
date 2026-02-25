<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION["user_id"])) {
  header("Location: ../auth/login.php");
  exit();
}
$uid = $_SESSION["user_id"];

// 📷 Subir avatar
if (isset($_FILES['avatar'])) {
  $nombre = time() . "_" . $_FILES['avatar']['name'];
  $ruta = "../assets/img/" . $nombre;

  if (move_uploaded_file($_FILES['avatar']['tmp_name'], $ruta)) {
    $conn->query("UPDATE usuarios SET avatar = '$nombre' WHERE id = $uid");
  }
  header("Location: perfil.php");
  exit();
}

// ✏️ Actualizar apodo y correo
if (isset($_POST['email'])) {
  $apodo = $_POST['apodo'];
  $email = $_POST['email'];

  $stmt = $conn->prepare("UPDATE usuarios SET apodo = ?, email = ? WHERE id = ?");
  $stmt->bind_param("ssi", $apodo, $email, $uid);
  $stmt->execute();

  header("Location: perfil.php");
  exit();
}

// 🔐 Cambiar contraseña
if (isset($_POST['pass_actual'])) {
  $actual = $_POST['pass_actual'];
  $nueva = $_POST['pass_nueva'];

  if (strlen($nueva) < 6) {
    header("Location: perfil.php?error=pass_corta");
    exit();
  }

  $res = $conn->query("SELECT password FROM usuarios WHERE id = $uid");
  $hash = $res->fetch_assoc()['password'];

  if (!password_verify($actual, $hash)) {
    header("Location: perfil.php?error=pass_incorrecta");
    exit();
  }

  $nuevoHash = password_hash($nueva, PASSWORD_DEFAULT);
  $conn->query("UPDATE usuarios SET password = '$nuevoHash' WHERE id = $uid");

  header("Location: perfil.php?ok=pass_cambiada");
  exit();
}

header("Location: perfil.php");
