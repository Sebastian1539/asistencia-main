<?php
session_start();
include("../config/conexion.php");
include("../dashboard/sidebar.php");

// Verificar si es admin
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

$mensaje = "";
$error = "";

// Procesar creación/edición de rol
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {
    
    if ($_POST["accion"] === "crear" || $_POST["accion"] === "editar") {
        $nombre = trim($_POST["nombre"]);
        $descripcion = trim($_POST["descripcion"]);
        $nivel_acceso = intval($_POST["nivel_acceso"]);
        
        if (empty($nombre)) {
            $error = "El nombre del rol es obligatorio";
        } else {
            if ($_POST["accion"] === "crear") {
                $stmt = $conn->prepare("INSERT INTO roles (nombre, descripcion, nivel_acceso) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $nombre, $descripcion, $nivel_acceso);
                
                if ($stmt->execute()) {
                    $mensaje = "Rol creado correctamente";
                } else {
                    $error = "Error al crear rol: " . $conn->error;
                }
                $stmt->close();
            } else {
                $id = intval($_POST["id"]);
                $stmt = $conn->prepare("UPDATE roles SET nombre = ?, descripcion = ?, nivel_acceso = ? WHERE id = ?");
                $stmt->bind_param("ssii", $nombre, $descripcion, $nivel_acceso, $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Rol actualizado correctamente";
                } else {
                    $error = "Error al actualizar rol: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
    
    if ($_POST["accion"] === "eliminar") {
        $id = intval($_POST["id"]);
        
        // Verificar si hay usuarios usando este rol
        $check = $conn->prepare("SELECT COUNT(*) as total FROM usuarios WHERE rol_id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $result = $check->get_result();
        $usuarios_con_rol = $result->fetch_assoc()['total'];
        $check->close();
        
        if ($usuarios_con_rol > 0) {
            $error = "No se puede eliminar el rol porque tiene $usuarios_con_rol usuarios asignados";
        } else {
            $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $mensaje = "Rol eliminado correctamente";
            } else {
                $error = "Error al eliminar rol";
            }
            $stmt->close();
        }
    }
}

// Obtener todos los roles
$roles = $conn->query("SELECT * FROM roles ORDER BY nivel_acceso DESC, nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Roles</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #f8f9fc; }
        
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }
        
        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 20px; }
        }
        
        .page-title {
            font-weight: 700;
            color: #2d3748;
            position: relative;
            padding-bottom: 0.75rem;
            margin-bottom: 2rem;
        }
        
        .page-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 2px;
        }
        
        .rol-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }
        
        .rol-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102,126,234,0.15);
        }
        
        .nivel-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .nivel-1 { background: #e2e8f0; color: #2d3748; }
        .nivel-2 { background: #bee3f8; color: #2c5282; }
        .nivel-3 { background: #fed7d7; color: #c53030; }
        
        .btn-accion {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-ver { background: #e2e8f0; color: #4a5568; }
        .btn-ver:hover { background: #667eea; color: white; transform: translateY(-2px); }
        
        .btn-editar { background: #e2e8f0; color: #4a5568; }
        .btn-editar:hover { background: #48bb78; color: white; transform: translateY(-2px); }
        
        .btn-eliminar { background: #e2e8f0; color: #4a5568; }
        .btn-eliminar:hover { background: #f56565; color: white; transform: translateY(-2px); }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        .modal-content { border-radius: 20px; border: none; }
        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px 20px 0 0;
            border: none;
        }
        
        /* Eliminar el filtro del botón close que causaba problemas */
        .modal-header .btn-close {
            filter: none;
            background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
            opacity: 1;
        }
        
        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
    </style>
</head>
<body>

<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">
                <i class="bi bi-person-badge me-2" style="color:#667eea;"></i>
                Gestión de Roles
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRol">
                <i class="bi bi-plus-circle me-2"></i>Nuevo Rol
            </button>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <?php if ($roles && $roles->num_rows > 0): ?>
                <?php while ($r = $roles->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="rol-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($r['nombre']) ?></h5>
                                    <span class="nivel-badge nivel-<?= $r['nivel_acceso'] ?>">
                                        Nivel <?= $r['nivel_acceso'] ?>
                                    </span>
                                </div>
                                <div>
                                    <button class="btn-accion btn-ver" 
                                            onclick="verHorarios(<?= $r['id'] ?>, '<?= htmlspecialchars($r['nombre']) ?>')"
                                            title="Ver horarios">
                                        <i class="bi bi-clock-history"></i>
                                    </button>
                                    <button class="btn-accion btn-editar" 
                                            onclick="editarRol(<?= $r['id'] ?>, '<?= htmlspecialchars($r['nombre']) ?>', 
                                                      '<?= htmlspecialchars($r['descripcion'] ?? '') ?>', <?= $r['nivel_acceso'] ?>)"
                                            title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn-accion btn-eliminar" 
                                            onclick="eliminarRol(<?= $r['id'] ?>, '<?= htmlspecialchars($r['nombre']) ?>')"
                                            title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="text-muted small mt-2 mb-0">
                                <?= htmlspecialchars($r['descripcion'] ?? 'Sin descripción') ?>
                            </p>
                            <small class="text-muted">
                                <i class="bi bi-calendar me-1"></i>Creado: <?= date('d/m/Y', strtotime($r['creado_en'])) ?>
                            </small>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
                        <h5>No hay roles creados</h5>
                        <p>Crea tu primer rol usando el botón "Nuevo Rol"</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal para crear/editar rol -->
<div class="modal fade" id="modalRol" tabindex="-1" aria-labelledby="modalRolLabel" aria-hidden="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRolLabel">Nuevo Rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="formRol">
                <div class="modal-body">
                    <input type="hidden" name="accion" id="accion" value="crear">
                    <input type="hidden" name="id" id="rolId">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre del Rol *</label>
                        <input type="text" name="nombre" id="rolNombre" class="form-control" required 
                               placeholder="Ej: Médico Especialista">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" id="rolDescripcion" class="form-control" rows="3" 
                                  placeholder="Funciones y responsabilidades del rol"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nivel de Acceso</label>
                        <select name="nivel_acceso" id="rolNivel" class="form-select">
                            <option value="1">Nivel 1 - Básico</option>
                            <option value="2">Nivel 2 - Medio</option>
                            <option value="3">Nivel 3 - Alto</option>
                        </select>
                        <small class="text-muted">Define el nivel de permisos del rol</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Rol</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para confirmar eliminación -->
<div class="modal fade" id="modalEliminar" tabindex="-1" aria-labelledby="modalEliminarLabel" aria-hidden="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalEliminarLabel">Confirmar Eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" id="eliminarId">
                    <p>¿Estás seguro de eliminar el rol <strong id="eliminarNombre"></strong>?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para ver horarios del rol -->
<div class="modal fade" id="modalHorarios" tabindex="-1" aria-labelledby="modalHorariosLabel" aria-hidden="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalHorariosLabel">Horarios del Rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="horariosContent">
                <!-- Contenido cargado vía JavaScript -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <a href="horarios_por_rol.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Configurar Horarios
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Función para editar rol
function editarRol(id, nombre, descripcion, nivel) {
    document.getElementById('accion').value = 'editar';
    document.getElementById('rolId').value = id;
    document.getElementById('rolNombre').value = nombre;
    document.getElementById('rolDescripcion').value = descripcion;
    document.getElementById('rolNivel').value = nivel;
    document.getElementById('modalRolLabel').textContent = 'Editar Rol';
    
    const modal = new bootstrap.Modal(document.getElementById('modalRol'));
    modal.show();
}

// Función para eliminar rol
function eliminarRol(id, nombre) {
    document.getElementById('eliminarId').value = id;
    document.getElementById('eliminarNombre').textContent = nombre;
    
    const modal = new bootstrap.Modal(document.getElementById('modalEliminar'));
    modal.show();
}

// Función para ver horarios del rol
function verHorarios(rolId, rolNombre) {
    document.getElementById('modalHorariosLabel').textContent = 'Horarios - ' + rolNombre;
    
    // Mostrar spinner
    document.getElementById('horariosContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `;
    
    // Cargar contenido vía fetch
    fetch('ajax_horarios_rol.php?rol_id=' + rolId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('horariosContent').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('horariosContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Error al cargar horarios
                </div>
            `;
        });
    
    const modal = new bootstrap.Modal(document.getElementById('modalHorarios'));
    modal.show();
}

// Resetear modal al abrir nuevo
document.querySelector('[data-bs-target="#modalRol"]').addEventListener('click', function() {
    document.getElementById('formRol').reset();
    document.getElementById('accion').value = 'crear';
    document.getElementById('rolId').value = '';
    document.getElementById('modalRolLabel').textContent = 'Nuevo Rol';
});

// Manejar cierre de modales para evitar problemas de foco
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('hidden.bs.modal', function () {
        // Remover cualquier elemento que haya quedado con foco
        if (document.activeElement && document.activeElement.tagName === 'BUTTON') {
            document.activeElement.blur();
        }
    });
});
</script>

</body>
</html>