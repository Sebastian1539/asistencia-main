<?php
session_start();
require_once __DIR__ . "/../config/conexion.php";

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../dashboard/index.php");
    exit();
}

$resumen = $conn->query("
    SELECT 
        COUNT(*) total,
        SUM(activo=1) activas,
        SUM(activo=0) inactivas
    FROM sedes
")->fetch_assoc();

$sedes = $conn->query("SELECT * FROM sedes ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gestión de Sedes</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.stat-card {
    background: linear-gradient(135deg,#667eea,#764ba2);
    color:white;
    border-radius:15px;
    padding:25px;
    text-align:center;
}
.table-container {
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 10px 25px rgba(0,0,0,0.05);
}
</style>
</head>
<body>

<?php include(__DIR__ . "/../dashboard/sidebar.php"); ?>

<main class="main-content">
<div class="container-fluid">

<h1 class="mb-4">
<i class="bi bi-buildings text-primary me-2"></i>
Gestión Profesional de Sedes
</h1>

<div class="row mb-4">
<div class="col-md-4"><div class="stat-card"><h3><?= $resumen["total"] ?></h3>Total</div></div>
<div class="col-md-4"><div class="stat-card"><h3><?= $resumen["activas"] ?></h3>Activas</div></div>
<div class="col-md-4"><div class="stat-card"><h3><?= $resumen["inactivas"] ?></h3>Inactivas</div></div>
</div>

<button class="btn btn-primary mb-3" onclick="abrirCrear()">
<i class="bi bi-plus-circle"></i> Nueva Sede
</button>

<div class="table-container">
<table id="tablaSedes" class="table table-striped">
<thead>
<tr>
<th>ID</th>
<th>Nombre</th>
<th>Dirección</th>
<th>Estado</th>
<th>Acciones</th>
</tr>
</thead>
<tbody>

<?php foreach($sedes as $s): ?>
<tr>
<td>#<?= $s["id"] ?></td>
<td><?= htmlspecialchars($s["nombre"]) ?></td>
<td><?= htmlspecialchars($s["direccion"]) ?></td>
<td>
<?= $s["activo"] ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-danger">Inactiva</span>' ?>
</td>
<td>
<button class="btn btn-warning btn-sm" onclick="toggleSede(<?= $s['id'] ?>)">
<i class="bi bi-arrow-repeat"></i>
</button>
<button class="btn btn-success btn-sm" onclick="editarSede(<?= $s['id'] ?>,'<?= htmlspecialchars($s['nombre'],ENT_QUOTES) ?>','<?= htmlspecialchars($s['direccion'],ENT_QUOTES) ?>')">
<i class="bi bi-pencil"></i>
</button>
<button class="btn btn-danger btn-sm" onclick="eliminarSede(<?= $s['id'] ?>)">
<i class="bi bi-trash"></i>
</button>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

</div>
</main>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$('#tablaSedes').DataTable();

function abrirCrear() {
    Swal.fire({
        title: 'Nueva Sede',
        html:
            '<input id="nombre" class="swal2-input" placeholder="Nombre">' +
            '<input id="direccion" class="swal2-input" placeholder="Dirección">',
        confirmButtonText: 'Crear',
        preConfirm: () => {
            return fetch('sedes_controller.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'accion=crear&nombre=' +
                      document.getElementById('nombre').value +
                      '&direccion=' +
                      document.getElementById('direccion').value
            }).then(res => res.json())
        }
    }).then(() => location.reload());
}

function editarSede(id,nombre,direccion){
    Swal.fire({
        title:'Editar Sede',
        html:
            `<input id="nombre" class="swal2-input" value="${nombre}">` +
            `<input id="direccion" class="swal2-input" value="${direccion}">`,
        confirmButtonText:'Guardar',
        preConfirm:()=>{
            return fetch('sedes_controller.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`accion=editar&id=${id}&nombre=`+
                     document.getElementById('nombre').value+
                     `&direccion=`+
                     document.getElementById('direccion').value
            }).then(res=>res.json())
        }
    }).then(()=>location.reload());
}

function toggleSede(id){
    fetch('sedes_controller.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`accion=toggle&id=${id}`
    }).then(()=>location.reload());
}

function eliminarSede(id){
    Swal.fire({
        title:'¿Eliminar sede?',
        icon:'warning',
        showCancelButton:true,
        confirmButtonText:'Eliminar'
    }).then(result=>{
        if(result.isConfirmed){
            fetch('sedes_controller.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`accion=eliminar&id=${id}`
            }).then(()=>location.reload());
        }
    });
}
</script>

</body>
</html>