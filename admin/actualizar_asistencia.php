<?php
session_start();
include("../config/conexion.php");

if ($_SESSION["rol"] != "admin") {
  header("Location: ../index.php");
  exit();
}

$id_asistencia = $_POST['id_asistencia'];
$estado = $_POST['estado'];
$usuario_id = $_POST['usuario_id'];

$stmt = $conn->prepare("UPDATE asistencias SET estado=? WHERE id=?");
$stmt->bind_param("si", $estado, $id_asistencia);
$stmt->execute();

header("Location: ver_usuario.php?id=$usuario_id");
exit();
