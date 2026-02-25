<?php
session_start();
include("../config/conexion.php");

// Verificar si es admin
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

$mensaje = "";
$error = "";

// Procesar formulario de nuevo nivel
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {
    
    if ($_POST["accion"] === "crear" || $_POST["accion"] === "editar") {
        $nivel = intval($_POST["nivel"]);
        $nombre = trim($_POST["nombre"]);
        $puntos_minimos = intval($_POST["puntos_minimos"]);
        $puntos_maximos = !empty($_POST["puntos_maximos"]) ? intval($_POST["puntos_maximos"]) : null;
        $descripcion = trim($_POST["descripcion"]);
        $imagen = "";
        
        // Procesar imagen subida
        if (isset($_FILES["imagen"]) && $_FILES["imagen"]["error"] === 0) {
            $extension = strtolower(pathinfo($_FILES["imagen"]["name"], PATHINFO_EXTENSION));
            $formatos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($extension, $formatos_permitidos)) {
                $nombre_archivo = "nivel" . $nivel . "." . $extension;
                $ruta_destino = "../assets/img/" . $nombre_archivo;
                
                if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $ruta_destino)) {
                    $imagen = $nombre_archivo;
                } else {
                    $error = "Error al subir la imagen";
                }
            } else {
                $error = "Formato de imagen no permitido. Use: jpg, jpeg, png, gif, webp";
            }
        }
        
        if ($_POST["accion"] === "crear") {
            // Insertar nuevo nivel
            $stmt = $conn->prepare("
                INSERT INTO niveles (nivel, nombre, puntos_minimos, puntos_maximos, imagen, descripcion) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isiiss", $nivel, $nombre, $puntos_minimos, $puntos_maximos, $imagen, $descripcion);
            
            if ($stmt->execute()) {
                $mensaje = "Nivel creado correctamente";
            } else {
                $error = "Error al crear nivel: " . $conn->error;
            }
            $stmt->close();
            
        } else if ($_POST["accion"] === "editar") {
            $id = intval($_POST["id"]);
            
            // Actualizar nivel existente
            if (!empty($imagen)) {
                // Si se subió nueva imagen
                $stmt = $conn->prepare("
                    UPDATE niveles 
                    SET nivel=?, nombre=?, puntos_minimos=?, puntos_maximos=?, imagen=?, descripcion=? 
                    WHERE id=?
                ");
                $stmt->bind_param("isiissi", $nivel, $nombre, $puntos_minimos, $puntos_maximos, $imagen, $descripcion, $id);
            } else {
                // Sin cambiar imagen
                $stmt = $conn->prepare("
                    UPDATE niveles 
                    SET nivel=?, nombre=?, puntos_minimos=?, puntos_maximos=?, descripcion=? 
                    WHERE id=?
                ");
                $stmt->bind_param("isissi", $nivel, $nombre, $puntos_minimos, $puntos_maximos, $descripcion, $id);
            }
            
            if ($stmt->execute()) {
                $mensaje = "Nivel actualizado correctamente";
            } else {
                $error = "Error al actualizar nivel: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    if ($_POST["accion"] === "eliminar") {
        $id = intval($_POST["id"]);
        
        // Obtener información de la imagen antes de eliminar
        $stmt = $conn->prepare("SELECT imagen FROM niveles WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $nivel = $result->fetch_assoc();
        $stmt->close();
        
        // Eliminar archivo de imagen si existe
        if ($nivel && !empty($nivel['imagen']) && file_exists("../assets/img/" . $nivel['imagen'])) {
            unlink("../assets/img/" . $nivel['imagen']);
        }
        
        // Eliminar registro
        $stmt = $conn->prepare("DELETE FROM niveles WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $mensaje = "Nivel eliminado correctamente";
        } else {
            $error = "Error al eliminar nivel";
        }
        $stmt->close();
    }
}

// Obtener todos los niveles
$niveles = $conn->query("SELECT * FROM niveles ORDER BY nivel ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Niveles</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Inter', sans-serif;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        
        .nivel-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }
        
        .nivel-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.15);
        }
        
        .nivel-imagen {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            padding: 3px;
            background: white;
        }
        
        .badge-nivel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .modal-content {
            border-radius: 20px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>

    <?php include("../dashboard/sidebar.php"); ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title">
                    <i class="bi bi-trophy me-2" style="color: #667eea;"></i>
                    Administrar Niveles
                </h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNivel">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Nivel
                </button>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= $mensaje ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Lista de niveles -->
            <div class="row">
                <?php if ($niveles && $niveles->num_rows > 0): ?>
                    <?php while ($n = $niveles->fetch_assoc()): ?>
                        <div class="col-md-6">
                            <div class="nivel-card">
                                <div class="d-flex align-items-center">
                                    <img src="../assets/img/<?= !empty($n['imagen']) ? $n['imagen'] : 'default.png' ?>" 
                                         alt="Nivel <?= $n['nivel'] ?>" 
                                         class="nivel-imagen me-3"
                                         onerror="this.src='../assets/img/default.png'">
                                    
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h4 class="mb-1">
                                                <span class="badge-nivel me-2">NIVEL <?= $n['nivel'] ?></span>
                                                <?= htmlspecialchars($n['nombre']) ?>
                                            </h4>
                                            <div>
                                                <button class="btn btn-sm btn-outline-primary me-1" 
                                                        onclick="editarNivel(<?= $n['id'] ?>, 
                                                                        <?= $n['nivel'] ?>, 
                                                                        '<?= addslashes($n['nombre']) ?>', 
                                                                        <?= $n['puntos_minimos'] ?>, 
                                                                        <?= $n['puntos_maximos'] ?? 'null' ?>, 
                                                                        '<?= addslashes($n['descripcion']) ?>')">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="eliminarNivel(<?= $n['id'] ?>, <?= $n['nivel'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <p class="text-muted mb-2"><?= htmlspecialchars($n['descripcion']) ?></p>
                                        
                                        <div class="d-flex gap-3">
                                            <small class="text-primary">
                                                <i class="bi bi-arrow-up-circle"></i>
                                                Mínimo: <?= number_format($n['puntos_minimos']) ?> pts
                                            </small>
                                            <?php if ($n['puntos_maximos']): ?>
                                                <small class="text-secondary">
                                                    <i class="bi bi-arrow-down-circle"></i>
                                                    Máximo: <?= number_format($n['puntos_maximos']) ?> pts
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
                            <h5>No hay niveles configurados</h5>
                            <p>Crea tu primer nivel usando el botón "Nuevo Nivel"</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Nuevo/Editar Nivel -->
    <div class="modal fade" id="modalNivel" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">
                        <i class="bi bi-plus-circle me-2"></i>
                        <span id="modalTituloTexto">Nuevo Nivel</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="formNivel">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="crear">
                        <input type="hidden" name="id" id="nivelId">
                        
                        <div class="mb-3">
                            <label class="form-label">Número de Nivel *</label>
                            <input type="number" name="nivel" id="nivelNumero" class="form-control" required min="1">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre del Nivel *</label>
                            <input type="text" name="nombre" id="nivelNombre" class="form-control" required 
                                   placeholder="Ej: Principiante, Experto, Maestro">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Puntos Mínimos *</label>
                            <input type="number" name="puntos_minimos" id="nivelMin" class="form-control" required min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Puntos Máximos (opcional)</label>
                            <input type="number" name="puntos_maximos" id="nivelMax" class="form-control" min="0">
                            <small class="text-muted">Dejar vacío si no hay límite superior</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" id="nivelDescripcion" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Imagen del Nivel</label>
                            <input type="file" name="imagen" class="form-control" accept="image/*">
                            <small class="text-muted">Formatos: JPG, PNG, GIF, WEBP</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Nivel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar -->
    <div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" id="eliminarId">
                        <p>¿Estás seguro de eliminar el <strong id="eliminarTexto">Nivel</strong>?</p>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Función para editar nivel
    function editarNivel(id, nivel, nombre, min, max, desc) {
        document.getElementById('accion').value = 'editar';
        document.getElementById('nivelId').value = id;
        document.getElementById('nivelNumero').value = nivel;
        document.getElementById('nivelNombre').value = nombre;
        document.getElementById('nivelMin').value = min;
        document.getElementById('nivelMax').value = max || '';
        document.getElementById('nivelDescripcion').value = desc || '';
        
        document.getElementById('modalTituloTexto').textContent = 'Editar Nivel';
        
        const modal = new bootstrap.Modal(document.getElementById('modalNivel'));
        modal.show();
    }

    // Función para eliminar nivel
    function eliminarNivel(id, nivel) {
        document.getElementById('eliminarId').value = id;
        document.getElementById('eliminarTexto').textContent = 'Nivel ' + nivel;
        
        const modal = new bootstrap.Modal(document.getElementById('modalEliminar'));
        modal.show();
    }

    // Resetear formulario al abrir modal para nuevo nivel
    document.addEventListener('DOMContentLoaded', function() {
        const nuevoNivelBtn = document.querySelector('[data-bs-target="#modalNivel"]');
        if (nuevoNivelBtn) {
            nuevoNivelBtn.addEventListener('click', function() {
                // Resetear formulario
                document.getElementById('formNivel').reset();
                document.getElementById('accion').value = 'crear';
                document.getElementById('nivelId').value = '';
                document.getElementById('modalTituloTexto').textContent = 'Nuevo Nivel';
            });
        }
    });
    </script>
</body>
</html>