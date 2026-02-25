<?php
session_start();
require_once __DIR__ . "/../config/conexion.php";

header("Content-Type: application/json");

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "admin") {
    echo json_encode(["status"=>"error","message"=>"No autorizado"]);
    exit();
}

$accion = $_POST["accion"] ?? null;

if (!$accion) {
    echo json_encode(["status"=>"error"]);
    exit();
}

/* ============================
   SUBIR IMAGEN
============================ */
function subirImagen($file){
    if(!$file || $file["error"] != 0) return null;

    $ext = pathinfo($file["name"], PATHINFO_EXTENSION);
    $nombre = uniqid().".".$ext;
    $ruta = __DIR__."/../assets/img/recompensas/".$nombre;

    move_uploaded_file($file["tmp_name"], $ruta);
    return $nombre;
}

/* ============================
   CREAR
============================ */
if($accion=="crear"){

    $nombre = trim($_POST["nombre"]);
    $descripcion = trim($_POST["descripcion"]);
    $costo = intval($_POST["costo"]);
    $stock = intval($_POST["stock"]);

    $imagen = subirImagen($_FILES["imagen"] ?? null);

    $stmt=$conn->prepare("INSERT INTO recompensas(nombre,descripcion,costo,stock,imagen,activo)
                          VALUES(?,?,?,?,?,1)");
    $stmt->bind_param("ssiis",$nombre,$descripcion,$costo,$stock,$imagen);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["status"=>"success"]);
    exit();
}

/* ============================
   EDITAR
============================ */
if($accion=="editar"){

    $id=intval($_POST["id"]);
    $nombre=trim($_POST["nombre"]);
    $descripcion=trim($_POST["descripcion"]);
    $costo=intval($_POST["costo"]);
    $stock=intval($_POST["stock"]);

    $imagen=subirImagen($_FILES["imagen"] ?? null);

    if($imagen){
        $stmt=$conn->prepare("UPDATE recompensas SET nombre=?,descripcion=?,costo=?,stock=?,imagen=? WHERE id=?");
        $stmt->bind_param("ssiisi",$nombre,$descripcion,$costo,$stock,$imagen,$id);
    }else{
        $stmt=$conn->prepare("UPDATE recompensas SET nombre=?,descripcion=?,costo=?,stock=? WHERE id=?");
        $stmt->bind_param("ssiii",$nombre,$descripcion,$costo,$stock,$id);
    }

    $stmt->execute();
    $stmt->close();

    echo json_encode(["status"=>"success"]);
    exit();
}

/* ============================
   TOGGLE
============================ */
if($accion=="toggle"){
    $id=intval($_POST["id"]);
    $conn->query("UPDATE recompensas SET activo=IF(activo=1,0,1) WHERE id=$id");
    echo json_encode(["status"=>"success"]);
    exit();
}

/* ============================
   ELIMINAR
============================ */
if($accion=="eliminar"){
    $id=intval($_POST["id"]);
    $conn->query("DELETE FROM recompensas WHERE id=$id");
    echo json_encode(["status"=>"success"]);
    exit();
}