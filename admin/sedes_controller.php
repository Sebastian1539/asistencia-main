<?php
session_start();
require_once __DIR__ . "/../config/conexion.php";

header("Content-Type: application/json");

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "admin") {
    echo json_encode(["status" => "error", "message" => "No autorizado"]);
    exit();
}

$accion = $_POST["accion"] ?? null;

if (!$accion) {
    echo json_encode(["status" => "error", "message" => "Acción inválida"]);
    exit();
}

/* ===========================
   CREAR
=========================== */
if ($accion === "crear") {

    $nombre = trim($_POST["nombre"]);
    $direccion = trim($_POST["direccion"]);

    if ($nombre === "") {
        echo json_encode(["status" => "error", "message" => "Nombre obligatorio"]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO sedes (nombre, direccion, activo) VALUES (?, ?, 1)");
    $stmt->bind_param("ss", $nombre, $direccion);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["status" => "success"]);
    exit();
}

/* ===========================
   EDITAR
=========================== */
if ($accion === "editar") {

    $id = intval($_POST["id"]);
    $nombre = trim($_POST["nombre"]);
    $direccion = trim($_POST["direccion"]);

    $stmt = $conn->prepare("UPDATE sedes SET nombre=?, direccion=? WHERE id=?");
    $stmt->bind_param("ssi", $nombre, $direccion, $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["status" => "success"]);
    exit();
}

/* ===========================
   TOGGLE
=========================== */
if ($accion === "toggle") {

    $id = intval($_POST["id"]);

    $stmt = $conn->prepare("UPDATE sedes SET activo = IF(activo=1,0,1) WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["status" => "success"]);
    exit();
}

/* ===========================
   ELIMINAR
=========================== */
if ($accion === "eliminar") {

    $id = intval($_POST["id"]);

    $stmt = $conn->prepare("DELETE FROM sedes WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["status" => "success"]);
    exit();
}

echo json_encode(["status" => "error", "message" => "Acción desconocida"]);