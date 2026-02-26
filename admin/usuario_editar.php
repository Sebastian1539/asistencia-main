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

// Obtener roles personalizados
$roles_personalizados = $conn->query("SELECT id, nombre FROM roles ORDER BY nombre");

$mensaje = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validar campos obligatorios
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tipo_rol = $_POST['tipo_rol'] ?? 'sistema';
    $rol_sistema = $_POST['rol_sistema'] ?? 'usuario';
    $rol_personalizado = isset($_POST['rol_personalizado']) && $_POST['rol_personalizado'] !== '' ? intval($_POST['rol_personalizado']) : null;
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
            
            // Determinar qué rol asignar
            if ($tipo_rol === 'personalizado' && $rol_personalizado) {
                // Rol personalizado
                $rol = 'usuario'; // Por defecto, los roles personalizados son usuarios del sistema
                $rol_id = $rol_personalizado;
            } else {
                // Rol del sistema
                $rol = $rol_sistema;
                $rol_id = null;
            }
            
            // Actualizar usuario con prepared statement
            $sql = "UPDATE usuarios SET nombre = ?, email = ?, rol = ?, rol_id = ?, sede_id = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssiii", $nombre, $email, $rol, $rol_id, $sede, $id);
            
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
        
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            background: linear-gradient(135deg, #f5f7fa 0%, #f8f9fc 100%);
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
        
        .card-form {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            max-width: 650px;
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
        
        .btn-outline-danger {
            border: 2px solid #f56565;
            color: #f56565;
            border-radius: 12px;
            padding: 8px 20px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-danger:hover {
            background: #f56565;
            color: white;
            transform: translateY(-2px);
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
        
        .rol-option {
            background: #f7fafc;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .rol-option:hover {
            border-color: #667eea;
            background: white;
        }
        
        .rol-option.selected {
            border-color: #667eea;
            background: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }
        
        .rol-option input[type="radio"] {
            display: none;
        }
        
        .rol-badge-sistema {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.7rem;
            margin-left: 10px;
        }
        
        .rol-badge-personalizado {
            background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);
            color: white;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.7rem;
            margin-left: 10px;
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
                <form method="POST" class="needs-validation" novalidate id="formEditarUsuario">
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
                            if ($sedes && $sedes->num_rows > 0) {
                                $sedes->data_seek(0);
                                while ($s = $sedes->fetch_assoc()): 
                            ?>
                                <option value="<?= $s['id'] ?>" <?= $u['sede_id'] == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['nombre']) ?>
                                </option>
                            <?php 
                                endwhile;
                            } 
                            ?>
                        </select>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            La sede donde trabaja el usuario
                        </small>
                    </div>

                    <!-- Selección de Tipo de Rol -->
                    <div class="mb-4">
                        <label class="form-label d-flex align-items-center">
                            <i class="bi bi-shield me-2" style="color: #667eea;"></i>
                            Tipo de Rol
                            <span class="badge-required">*</span>
                        </label>
                        
                        <div class="row g-3">
                            <!-- Opción: Rol del sistema -->
                            <div class="col-md-6">
                                <?php 
                                $tipo_actual = empty($u['rol_id']) ? 'sistema' : 'personalizado';
                                ?>
                                <div class="rol-option <?= $tipo_actual === 'sistema' ? 'selected' : '' ?>" 
                                     onclick="document.getElementById('tipo_sistema').checked = true; mostrarOpcionesSistema()">
                                    <input type="radio" name="tipo_rol" id="tipo_sistema" value="sistema" 
                                           <?= $tipo_actual === 'sistema' ? 'checked' : '' ?> style="display: none;">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-gear fs-4 me-3" style="color: #667eea;"></i>
                                        <div>
                                            <h6 class="mb-1">Rol del Sistema</h6>
                                            <small class="text-muted">Administrador o Usuario básico</small>
                                        </div>
                                        <span class="rol-badge-sistema ms-auto">SISTEMA</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Opción: Rol personalizado -->
                            <div class="col-md-6">
                                <div class="rol-option <?= $tipo_actual === 'personalizado' ? 'selected' : '' ?>" 
                                     onclick="document.getElementById('tipo_personalizado').checked = true; mostrarOpcionesPersonalizado()">
                                    <input type="radio" name="tipo_rol" id="tipo_personalizado" value="personalizado" 
                                           <?= $tipo_actual === 'personalizado' ? 'checked' : '' ?> style="display: none;">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-star fs-4 me-3" style="color: #9f7aea;"></i>
                                        <div>
                                            <h6 class="mb-1">Rol Personalizado</h6>
                                            <small class="text-muted">Roles creados a medida</small>
                                        </div>
                                        <span class="rol-badge-personalizado ms-auto">PERSONALIZADO</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Opciones de Rol del Sistema -->
                    <div class="mb-4" id="opcionesSistema" style="display: <?= $tipo_actual === 'sistema' ? 'block' : 'none' ?>;">
                        <label class="form-label d-flex align-items-center">
                            <i class="bi bi-shield me-2" style="color: #667eea;"></i>
                            Seleccionar Rol del Sistema
                        </label>
                        <select name="rol_sistema" class="form-select">
                            <option value="usuario" <?= ($u['rol'] === 'usuario' && empty($u['rol_id'])) ? 'selected' : '' ?>>
                                👤 Usuario - Acceso básico al sistema
                            </option>
                            <option value="admin" <?= ($u['rol'] === 'admin' && empty($u['rol_id'])) ? 'selected' : '' ?>>
                                🔐 Administrador - Acceso completo
                            </option>
                        </select>
                        <small class="text-muted-custom">
                            <i class="bi bi-info-circle me-1"></i>
                            Los administradores tienen acceso al panel de control
                        </small>
                    </div>

                    <!-- Opciones de Rol Personalizado -->
                    <div class="mb-4" id="opcionesPersonalizado" style="display: <?= $tipo_actual === 'personalizado' ? 'block' : 'none' ?>;">
                        <label class="form-label d-flex align-items-center">
                            <i class="bi bi-star me-2" style="color: #9f7aea;"></i>
                            Seleccionar Rol Personalizado
                        </label>
                        <select name="rol_personalizado" class="form-select">
                            <option value="">Seleccione un rol personalizado...</option>
                            <?php 
                            if ($roles_personalizados && $roles_personalizados->num_rows > 0) {
                                $roles_personalizados->data_seek(0);
                                while($r = $roles_personalizados->fetch_assoc()): 
                                    $selected = ($u['rol_id'] == $r['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $r['id'] ?>" <?= $selected ?>>
                                    ⭐ <?= htmlspecialchars($r['nombre']) ?>
                                </option>
                            <?php 
                                endwhile;
                            } else {
                            ?>
                                <option value="" disabled>No hay roles personalizados creados</option>
                            <?php } ?>
                        </select>
                        <small class="text-muted-custom">
                            <i class="bi bi-info-circle me-1"></i>
                            Los roles personalizados tienen horarios específicos configurados
                        </small>
                        <?php if (!$roles_personalizados || $roles_personalizados->num_rows === 0): ?>
                            <div class="alert alert-warning mt-2 py-2 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                No hay roles personalizados. 
                                <a href="roles.php" class="alert-link">Crear rol personalizado</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Nota sobre contraseña -->
                    <div class="alert alert-info">
                        <i class="bi bi-key me-2"></i>
                        <strong>¿Cambiar contraseña?</strong><br>
                        <small>Para cambiar la contraseña, el usuario debe hacerlo desde su perfil o puedes usar la opción de recuperación.</small>
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

                <!-- Zona de peligro -->
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Scripts personalizados -->
<script>
// Función para mostrar opciones de rol del sistema
function mostrarOpcionesSistema() {
    document.getElementById('opcionesSistema').style.display = 'block';
    document.getElementById('opcionesPersonalizado').style.display = 'none';
    
    // Actualizar estilo visual
    document.querySelectorAll('.rol-option').forEach(opt => opt.classList.remove('selected'));
    document.querySelector('.rol-option:has(#tipo_sistema)').classList.add('selected');
}

// Función para mostrar opciones de rol personalizado
function mostrarOpcionesPersonalizado() {
    document.getElementById('opcionesSistema').style.display = 'none';
    document.getElementById('opcionesPersonalizado').style.display = 'block';
    
    // Actualizar estilo visual
    document.querySelectorAll('.rol-option').forEach(opt => opt.classList.remove('selected'));
    document.querySelector('.rol-option:has(#tipo_personalizado)').classList.add('selected');
}

// Validación del formulario
(function() {
    'use strict';
    
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            const tipoRol = document.querySelector('input[name="tipo_rol"]:checked');
            let isValid = true;
            
            // Validar que se haya seleccionado un tipo de rol
            if (!tipoRol) {
                alert('Debe seleccionar un tipo de rol');
                isValid = false;
            }
            
            // Si es rol personalizado, validar que se haya seleccionado uno
            if (tipoRol && tipoRol.value === 'personalizado') {
                const rolPersonalizado = document.querySelector('[name="rol_personalizado"]');
                if (!rolPersonalizado || !rolPersonalizado.value) {
                    alert('Debe seleccionar un rol personalizado');
                    isValid = false;
                }
            }
            
            if (!form.checkValidity() || !isValid) {
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