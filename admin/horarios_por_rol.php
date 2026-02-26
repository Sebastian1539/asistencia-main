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

// Obtener todos los roles para el filtro
$roles = $conn->query("SELECT * FROM roles ORDER BY nombre");

// Obtener horarios con información del rol
$horarios = $conn->query("
    SELECT h.*, r.nombre as rol_nombre 
    FROM horarios_por_rol h
    JOIN roles r ON h.rol_id = r.id
    ORDER BY r.nombre, h.hora_entrada
");

// Procesar creación/edición de horario
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {
    
    if ($_POST["accion"] === "crear" || $_POST["accion"] === "editar") {
        $rol_id = intval($_POST["rol_id"]);
        $nombre_config = trim($_POST["nombre_config"]);
        $hora_entrada = $_POST["hora_entrada"];
        $minutos_tolerancia = intval($_POST["minutos_tolerancia"]);
        $hora_inicio_tardanza = $_POST["hora_inicio_tardanza"];
        $hora_salida = $_POST["hora_salida"];
        $dias_laborales = trim($_POST["dias_laborales"]);
        
        if (empty($nombre_config) || empty($hora_entrada) || empty($hora_salida)) {
            $error = "Todos los campos son obligatorios";
        } else {
            if ($_POST["accion"] === "crear") {
                $stmt = $conn->prepare("
                    INSERT INTO horarios_por_rol 
                    (rol_id, nombre_config, hora_entrada, minutos_tolerancia, hora_inicio_tardanza, hora_salida, dias_laborales) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("ississs", $rol_id, $nombre_config, $hora_entrada, $minutos_tolerancia, 
                                $hora_inicio_tardanza, $hora_salida, $dias_laborales);
                
                if ($stmt->execute()) {
                    $mensaje = "Horario creado correctamente";
                } else {
                    $error = "Error al crear horario: " . $conn->error;
                }
                $stmt->close();
            } else {
                $id = intval($_POST["id"]);
                $stmt = $conn->prepare("
                    UPDATE horarios_por_rol 
                    SET rol_id = ?, nombre_config = ?, hora_entrada = ?, minutos_tolerancia = ?, 
                        hora_inicio_tardanza = ?, hora_salida = ?, dias_laborales = ? 
                    WHERE id = ?
                ");
                $stmt->bind_param("ississsi", $rol_id, $nombre_config, $hora_entrada, $minutos_tolerancia, 
                                $hora_inicio_tardanza, $hora_salida, $dias_laborales, $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Horario actualizado correctamente";
                } else {
                    $error = "Error al actualizar horario: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
    
    if ($_POST["accion"] === "eliminar") {
        $id = intval($_POST["id"]);
        $stmt = $conn->prepare("DELETE FROM horarios_por_rol WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $mensaje = "Horario eliminado correctamente";
        } else {
            $error = "Error al eliminar horario";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horarios por Rol</title>
    
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
        
        .horario-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }
        
        .rol-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
        }
        
        .horario-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .horario-item {
            text-align: center;
            background: #f7fafc;
            padding: 10px;
            border-radius: 10px;
        }
        
        .horario-label {
            font-size: 0.7rem;
            color: #718096;
            text-transform: uppercase;
        }
        
        .horario-valor {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
        }
        
        .btn-accion {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 2px;
            transition: all 0.2s ease;
        }
        
        .btn-editar { background: #e2e8f0; color: #4a5568; }
        .btn-editar:hover { background: #48bb78; color: white; }
        .btn-eliminar { background: #e2e8f0; color: #4a5568; }
        .btn-eliminar:hover { background: #f56565; color: white; }
    </style>
</head>
<body>

<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">
                <i class="bi bi-clock-history me-2" style="color:#667eea;"></i>
                Horarios por Rol
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalHorario">
                <i class="bi bi-plus-circle me-2"></i>Nuevo Horario
            </button>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <?php if ($horarios && $horarios->num_rows > 0): ?>
                <?php while ($h = $horarios->fetch_assoc()): 
                    $hora_tope = date('H:i', strtotime($h['hora_entrada'] . ' + ' . $h['minutos_tolerancia'] . ' minutes'));
                ?>
                    <div class="col-md-6">
                        <div class="horario-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="rol-badge">
                                        <i class="bi bi-person-badge me-1"></i>
                                        <?= htmlspecialchars($h['rol_nombre']) ?>
                                    </span>
                                    <h5 class="mt-2 mb-1"><?= htmlspecialchars($h['nombre_config']) ?></h5>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar-week me-1"></i>
                                        <?= htmlspecialchars($h['dias_laborales'] ?? 'Lunes a Viernes') ?>
                                    </small>
                                </div>
                                <div>
                                    <button class="btn-accion btn-editar" 
                                            onclick="editarHorario(<?= $h['id'] ?>, <?= $h['rol_id'] ?>, 
                                                      '<?= htmlspecialchars($h['nombre_config']) ?>', 
                                                      '<?= $h['hora_entrada'] ?>', 
                                                      <?= $h['minutos_tolerancia'] ?>, 
                                                      '<?= $h['hora_inicio_tardanza'] ?>', 
                                                      '<?= $h['hora_salida'] ?>', 
                                                      '<?= htmlspecialchars($h['dias_laborales']) ?>')"
                                            title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn-accion btn-eliminar" 
                                            onclick="eliminarHorario(<?= $h['id'] ?>, '<?= htmlspecialchars($h['nombre_config']) ?>')"
                                            title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="horario-grid">
                                <div class="horario-item">
                                    <div class="horario-label">Entrada</div>
                                    <div class="horario-valor"><?= date('H:i', strtotime($h['hora_entrada'])) ?></div>
                                </div>
                                <div class="horario-item">
                                    <div class="horario-label">Tolerancia</div>
                                    <div class="horario-valor"><?= $h['minutos_tolerancia'] ?> min</div>
                                </div>
                                <div class="horario-item">
                                    <div class="horario-label">Tope temprano</div>
                                    <div class="horario-valor"><?= $hora_tope ?></div>
                                </div>
                                <div class="horario-item">
                                    <div class="horario-label">Tardanza</div>
                                    <div class="horario-valor"><?= date('H:i', strtotime($h['hora_inicio_tardanza'])) ?></div>
                                </div>
                                <div class="horario-item">
                                    <div class="horario-label">Salida</div>
                                    <div class="horario-valor"><?= date('H:i', strtotime($h['hora_salida'])) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
                        <h5>No hay horarios configurados</h5>
                        <p>Crea tu primer horario usando el botón "Nuevo Horario"</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal para crear/editar horario -->
<div class="modal fade" id="modalHorario" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalHorarioTitulo">Nuevo Horario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formHorario">
                <div class="modal-body">
                    <input type="hidden" name="accion" id="horarioAccion" value="crear">
                    <input type="hidden" name="id" id="horarioId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol *</label>
                            <select name="rol_id" id="horarioRol" class="form-select" required>
                                <option value="">Seleccionar rol</option>
                                <?php 
                                $roles->data_seek(0);
                                while ($r = $roles->fetch_assoc()): 
                                ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre del horario *</label>
                            <input type="text" name="nombre_config" id="horarioNombre" class="form-control" required 
                                   placeholder="Ej: Turno Mañana">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hora entrada *</label>
                            <input type="time" name="hora_entrada" id="horarioEntrada" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tolerancia (min) *</label>
                            <input type="number" name="minutos_tolerancia" id="horarioTolerancia" class="form-control" 
                                   value="15" min="0" max="120" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Inicio tardanza *</label>
                            <input type="time" name="hora_inicio_tardanza" id="horarioTardanza" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hora salida *</label>
                            <input type="time" name="hora_salida" id="horarioSalida" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Días laborales</label>
                            <input type="text" name="dias_laborales" id="horarioDias" class="form-control" 
                                   value="Lunes a Viernes" placeholder="Ej: Lunes a Sábado">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Horario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para confirmar eliminación -->
<div class="modal fade" id="modalEliminarHorario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmar Eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" id="eliminarHorarioId">
                    <p>¿Estás seguro de eliminar el horario <strong id="eliminarHorarioNombre"></strong>?</p>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function editarHorario(id, rolId, nombre, entrada, tolerancia, tardanza, salida, dias) {
    document.getElementById('horarioAccion').value = 'editar';
    document.getElementById('horarioId').value = id;
    document.getElementById('horarioRol').value = rolId;
    document.getElementById('horarioNombre').value = nombre;
    document.getElementById('horarioEntrada').value = entrada;
    document.getElementById('horarioTolerancia').value = tolerancia;
    document.getElementById('horarioTardanza').value = tardanza;
    document.getElementById('horarioSalida').value = salida;
    document.getElementById('horarioDias').value = dias;
    document.getElementById('modalHorarioTitulo').textContent = 'Editar Horario';
    new bootstrap.Modal(document.getElementById('modalHorario')).show();
}

function eliminarHorario(id, nombre) {
    document.getElementById('eliminarHorarioId').value = id;
    document.getElementById('eliminarHorarioNombre').textContent = nombre;
    new bootstrap.Modal(document.getElementById('modalEliminarHorario')).show();
}

document.querySelector('[data-bs-target="#modalHorario"]').addEventListener('click', function() {
    document.getElementById('formHorario').reset();
    document.getElementById('horarioAccion').value = 'crear';
    document.getElementById('horarioId').value = '';
    document.getElementById('modalHorarioTitulo').textContent = 'Nuevo Horario';
});
</script>

</body>
</html>