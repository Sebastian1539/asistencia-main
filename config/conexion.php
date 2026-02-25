<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "clinica_gamificacion"; // 👈 ESTE es el nombre correcto

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
  die("❌ Error de conexión: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
