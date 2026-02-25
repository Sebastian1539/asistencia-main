<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

// Verificar si es admin
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "admin") {
    header("Location: /asistencia/index.php");
    exit();
}

// Validar que exista el ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: usuarios.php");
    exit();
}

$id = intval($_GET['id']);

// Obtener datos del usuario con prepared statement
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();
$u = $resultado->fetch_assoc();
$stmt->close();

// Si no existe el usuario, redirigir
if (!$u) {
    header("Location: usuarios.php");
    exit();
}

// Obtener sedes activas
$sedes = $conn->query("SELECT id, nombre FROM sedes WHERE activo = 1 ORDER BY nombre");

$mensaje = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validar campos obligatorios
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rol = $_POST['rol'] ?? 'usuario';
    $sede = !empty($_POST['sede_id']) ? intval($_POST['sede_id']) : null;
    
    if (empty($nombre) || empty($email)) {
        $error = "El nombre y el email son obligatorios";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El formato del email no es válido";
    } else {
        
        // Verificar si el email ya existe para otro usuario
        $check = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Este email ya está registrado por otro usuario";
        } else {
            
            // Actualizar usuario con prepared statement
            if ($sede) {
                $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, sede_id = ? WHERE id = ?");
                $stmt->bind_param("sssii", $nombre, $email, $rol, $sede, $id);
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, sede_id = NULL WHERE id = ?");
                $stmt->bind_param("sssi", $nombre, $email, $rol, $id);
            }
            
            if ($stmt->execute()) {
                // Registrar en log de admin
                $log = $conn->prepare("INSERT INTO logs_admin (admin_id, accion) VALUES (?, ?)");
                $admin_id = $_SESSION['user_id'];
                $accion = "Editó usuario ID: $id - $nombre";
                $log->bind_param("is", $admin_id, $accion);
                $log->execute();
                $log->close();
                
                $_SESSION['mensaje'] = "Usuario actualizado correctamente";
                header("Location: usuarios.php");
                exit();
            } else {
                $error = "Error al actualizar: " . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario | Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: #f8f9fc;
        }
        
        .layout {
            display: flex;
            min-height: 100vh;
        }
        
        .contenido {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            background: linear-gradient(135deg, #f5f7fa 0%, #f8f9fc 100%);
        }
        
        @media (max-width: 768px) {
            .contenido {
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
        
        .card-form {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            max-width: 600px;
            margin: 0 auto;
            border: none;
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px 30px;
            margin: -30px -30px 20px -30px;
        }
        
        .form-label {
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #a0aec0 0%, #718096 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(113, 128, 150, 0.4);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .info-section {
            background: #f7fafc;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .info-section i {
            color: #667eea;
            margin-right: 10px;
        }
        
        .avatar-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            padding: 3px;
            background: white;
            margin-bottom: 15px;
        }
        
        .required-field::after {
            content: "*";
            color: #f56565;
            margin-left: 4px;
        }
    </style>
</head>
<body>

    <?php include(__DIR__ . "/../dashboard/sidebar.php"); ?>

    <main class="main-content">
        <div class="container-fluid">
            <!-- Título con botón volver -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title mb-0">
                    <i class="bi bi-pencil-square me-2" style="color: #667eea;"></i>
                    Editar Usuario
                </h1>
                <a href="usuarios.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Volver
                </a>
            </div>

            <!-- Tarjeta del formulario -->
            <div class="card-form">
                <div class="card-header-custom">
                    <h5 class="mb-0">
                        <i class="bi bi-person-badge me-2"></i>
                        Editando: <?= htmlspecialchars($u['nombre']) ?>
                    </h5>
                    <p class="text-white-50 mt-2 mb-0 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Los campos marcados con <span class="text-white fw-bold">*</span> son obligatorios
                    </p>
                </div>
                
                <!-- Mensajes de error -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Información del usuario -->
                <div class="info-section d-flex align-items-center">
                    <img src="<?= !empty($u['avatar']) ? '../assets/img/' . $u['avatar'] : '../assets/img/default.png' ?>" 
                         alt="Avatar" 
                         class="avatar-preview"
                         onerror="this.src='../assets/img/default.png'">
                    <div class="ms-3">
                        <h6 class="mb-1">Información actual</h6>
                        <p class="mb-0 text-muted small">
                            <i class="bi bi-envelope"></i> <?= htmlspecialchars($u['email']) ?><br>
                            <i class="bi bi-calendar"></i> Registrado: <?= date('d/m/Y', strtotime($u['fecha_registro'] ?? 'now')) ?>
                        </p>
                    </div>
                </div>

                <!-- Formulario de edición -->
                <form method="POST" class="needs-validation" novalidate>
                    <!-- Nombre -->
                    <div class="mb-4">
                        <label class="form-label required-field">
                            <i class="bi bi-person me-2" style="color: #667eea;"></i>
                            Nombre Completo
                        </label>
                        <input type="text" 
                               name="nombre" 
                               class="form-control" 
                               value="<?= htmlspecialchars($u['nombre']) ?>" 
                               required 
                               placeholder="Ej: Juan Pérez">
                        <div class="invalid-feedback">
                            El nombre es obligatorio
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="mb-4">
                        <label class="form-label required-field">
                            <i class="bi bi-envelope me-2" style="color: #667eea;"></i>
                            Correo Electrónico
                        </label>
                        <input type="email" 
                               name="email" 
                               class="form-control" 
                               value="<?= htmlspecialchars($u['email']) ?>" 
                               required 
                               placeholder="ejemplo@correo.com">
                        <div class="invalid-feedback">
                            Ingresa un email válido
                        </div>
                    </div>

                    <!-- Sede -->
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="bi bi-building me-2" style="color: #667eea;"></i>
                            Sede
                        </label>
                        <select name="sede_id" class="form-select">
                            <option value="">Sin sede asignada</option>
                            <?php 
                            $sedes->data_seek(0);
                            while ($s = $sedes->fetch_assoc()): 
                            ?>
                                <option value="<?= $s['id'] ?>" <?= $u['sede_id'] == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['nombre']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            La sede donde trabaja el usuario
                        </small>
                    </div>

                    <!-- Rol -->
                    <div class="mb-4">
                        <label class="form-label required-field">
                            <i class="bi bi-shield me-2" style="color: #667eea;"></i>
                            Rol
                        </label>
                        <select name="rol" class="form-select" required>
                            <option value="usuario" <?= $u['rol'] === 'usuario' ? 'selected' : '' ?>>
                                👤 Usuario - Acceso básico
                            </option>
                            <option value="admin" <?= $u['rol'] === 'admin' ? 'selected' : '' ?>>
                                🔐 Administrador - Acceso completo
                            </option>
                        </select>
                    </div>

                    <!-- Nota sobre contraseña -->
                    <div class="alert alert-info">
                        <i class="bi bi-key me-2"></i>
                        <strong>¿Cambiar contraseña?</strong><br>
                        <small>Para cambiar la contraseña, usa la opción en el perfil del usuario o en la página de edición avanzada.</small>
                    </div>

                    <!-- Botones -->
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-save me-2"></i>
                            Guardar Cambios
                        </button>
                        <a href="usuarios.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-2"></i>
                            Cancelar
                        </a>
                    </div>
                </form>

                <!-- Zona de peligro (opcional) -->
                <hr class="my-4">
                <div class="text-center">
                    <a href="usuario_eliminar.php?id=<?= $id ?>" 
                       class="btn btn-outline-danger btn-sm"
                       onclick="return confirm('¿Estás seguro de eliminar este usuario? Esta acción no se puede deshacer.')">
                        <i class="bi bi-trash me-2"></i>
                        Eliminar Usuario
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Validación del formulario -->
<script>
(function() {
    'use strict';
    
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

</body>
</html>