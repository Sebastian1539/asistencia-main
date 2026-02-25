<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "admin") {
    header("Location: /asistencia/index.php");
    exit();
}

// Verificar conexión a la base de datos
if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Obtener sedes activas
$sedes = $conn->query("SELECT id, nombre FROM sedes WHERE activo = 1 ORDER BY nombre");

// Verificar si la consulta de sedes fue exitosa
if (!$sedes) {
    die("Error en la consulta de sedes: " . $conn->error);
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validar y sanitizar inputs
    $codigo = isset($_POST["codigo"]) ? intval($_POST["codigo"]) : 0;
    $nombre = isset($_POST["nombre"]) ? trim($_POST["nombre"]) : "";
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
    $rol = isset($_POST["rol"]) ? $_POST["rol"] : "usuario";
    $sede = isset($_POST["sede_id"]) && $_POST["sede_id"] !== "" ? intval($_POST["sede_id"]) : null;
    $pass = isset($_POST["password"]) ? $_POST["password"] : "";

    if ($codigo <= 0 || $nombre === "" || $email === "" || $pass === "") {
        $error = "Todos los campos obligatorios deben completarse";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El formato del correo electrónico no es válido";
    } elseif (strlen($pass) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } else {

        // Verificar código biométrico duplicado
        $checkCodigo = $conn->prepare("SELECT id FROM usuarios WHERE codigo_biometrico = ?");
        if ($checkCodigo) {
            $checkCodigo->bind_param("i", $codigo);
            $checkCodigo->execute();
            $resCodigo = $checkCodigo->get_result();

            if ($resCodigo->num_rows > 0) {
                $error = "Ese código biométrico ya existe";
            } else {

                // Verificar email duplicado
                $check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
                if ($check) {
                    $check->bind_param("s", $email);
                    $check->execute();
                    $res = $check->get_result();

                    if ($res->num_rows > 0) {
                        $error = "Ese correo ya está registrado";
                    } else {

                        $hash = password_hash($pass, PASSWORD_BCRYPT);

                        // Insertar usuario
                        $stmt = $conn->prepare("
                            INSERT INTO usuarios 
                            (codigo_biometrico, nombre, email, password, rol, sede_id) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");

                        if ($stmt) {
                            $stmt->bind_param("issssi", $codigo, $nombre, $email, $hash, $rol, $sede);
                            
                            if ($stmt->execute()) {
                                $uid = $stmt->insert_id;

                                // Crear registro de puntos para el nuevo usuario
                                $insertPuntos = $conn->prepare("INSERT INTO puntos (usuario_id, total) VALUES (?, 0)");
                                if ($insertPuntos) {
                                    $insertPuntos->bind_param("i", $uid);
                                    $insertPuntos->execute();
                                    $insertPuntos->close();
                                }

                                $success = "Usuario registrado correctamente";
                                
                                // Redireccionar después de 2 segundos
                                header("refresh:2;url=usuarios.php");
                            } else {
                                $error = "Error al registrar el usuario: " . $stmt->error;
                            }
                            $stmt->close();
                        } else {
                            $error = "Error en la preparación de la consulta: " . $conn->error;
                        }
                    }
                    $check->close();
                } else {
                    $error = "Error en la verificación de email: " . $conn->error;
                }
            }
            $checkCodigo->close();
        } else {
            $error = "Error en la verificación de código: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Usuario | Clínica Gamificación</title>
    
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
        
        .contenido {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #f8f9fc 100%);
        }
        
        .card-form {
            max-width: 600px;
            border: none;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }
        
        .card-form:hover {
            transform: translateY(-5px);
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0 !important;
            padding: 1.5rem;
            border: none;
        }
        
        .form-label {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background-color: white;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: #fc8181;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        
        .btn-success:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #feb2b2 0%, #fc8181 100%);
            color: #742a2a;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #9ae6b4 0%, #68d391 100%);
            color: #22543d;
        }
        
        .text-muted-custom {
            color: #718096;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: block;
        }
        
        .badge-required {
            background: linear-gradient(135deg, #feb2b2 0%, #fc8181 100%);
            color: #742a2a;
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            margin-left: 0.5rem;
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
        
        .icon-input {
            position: relative;
        }
        
        .icon-input i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }
        
        .icon-input .form-control {
            padding-left: 2.5rem;
        }
        
        .sedes-count {
            background: linear-gradient(135deg, #f6ad55 0%, #ed8936 100%);
            color: white;
            border-radius: 20px;
            padding: 0.2rem 0.8rem;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>

<?php include(__DIR__ . "/../dashboard/sidebar.php"); ?>

<main class="main-content">
    <div class="container-fluid px-4">
        <!-- Encabezado -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h3 class="page-title">
                <i class="bi bi-person-plus-fill me-2" style="color: #667eea;"></i>
                Registrar Nuevo Trabajador
            </h3>
            <a href="usuarios.php" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-arrow-left me-2"></i>Volver
            </a>
        </div>

        <!-- Tarjeta del formulario -->
        <div class="card card-form mx-auto">
            <div class="card-header-custom">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-person-badge me-2"></i>
                    Datos del trabajador
                </h5>
                <p class="text-white-50 mt-2 mb-0 small">
                    Complete todos los campos obligatorios marcados con <span class="badge bg-white text-dark">*</span>
                </p>
            </div>
            
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($success) ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <!-- Fila de código biométrico -->
                    <div class="mb-4">
                        <label class="form-label d-flex align-items-center">
                            <i class="bi bi-fingerprint me-2" style="color: #667eea;"></i>
                            Código Biométrico
                            <span class="badge-required">*</span>
                        </label>
                        <div class="icon-input">
                            <i class="bi bi-upc-scan"></i>
                            <input type="number" 
                                   name="codigo" 
                                   class="form-control" 
                                   required 
                                   placeholder="Ej: 123456"
                                   min="1">
                        </div>
                        <small class="text-muted-custom">
                            <i class="bi bi-info-circle me-1"></i>
                            Debe coincidir con el código configurado en el reloj biométrico
                        </small>
                    </div>

                    <!-- Fila de nombre completo -->
                    <div class="mb-4">
                        <label class="form-label d-flex align-items-center">
                            <i class="bi bi-person me-2" style="color: #667eea;"></i>
                            Nombre Completo
                            <span class="badge-required">*</span>
                        </label>
                        <div class="icon-input">
                            <i class="bi bi-person-badge"></i>
                            <input type="text" 
                                   name="nombre" 
                                   class="form-control" 
                                   required 
                                   placeholder="Ej: Juan Pérez García"
                                   pattern="[A-Za-zÁáÉéÍíÓóÚúÑñ\s]+"
                                   title="Solo letras y espacios">
                        </div>
                    </div>

                    <!-- Fila de correo -->
                    <div class="mb-4">
                        <label class="form-label d-flex align-items-center">
                            <i class="bi bi-envelope me-2" style="color: #667eea;"></i>
                            Correo Electrónico
                            <span class="badge-required">*</span>
                        </label>
                        <div class="icon-input">
                            <i class="bi bi-envelope-at"></i>
                            <input type="email" 
                                   name="email" 
                                   class="form-control" 
                                   required 
                                   placeholder="ejemplo@correo.com">
                        </div>
                    </div>

                    <!-- Fila de contraseña -->
                    <div class="mb-4">
                        <label class="form-label d-flex align-items-center">
                            <i class="bi bi-shield-lock me-2" style="color: #667eea;"></i>
                            Contraseña
                            <span class="badge-required">*</span>
                        </label>
                        <div class="icon-input">
                            <i class="bi bi-key"></i>
                            <input type="password" 
                                   name="password" 
                                   class="form-control" 
                                   required 
                                   placeholder="Mínimo 6 caracteres"
                                   minlength="6">
                        </div>
                        <small class="text-muted-custom">
                            <i class="bi bi-shield-check me-1"></i>
                            Mínimo 6 caracteres para mayor seguridad
                        </small>
                    </div>

                    <!-- Fila de sede -->
                    <div class="mb-4">
                        <label class="form-label d-flex align-items-center">
                            <i class="bi bi-building me-2" style="color: #667eea;"></i>
                            Sede de Trabajo
                        </label>
                        <select name="sede_id" class="form-select">
                            <option value="" selected>Seleccione una sede...</option>
                            <?php 
                            $sedes->data_seek(0); // Reiniciar el puntero del resultado
                            $sedesCount = 0;
                            while($s = $sedes->fetch_assoc()): 
                                $sedesCount++;
                            ?>
                                <option value="<?= $s['id'] ?>" class="py-2">
                                    <i class="bi bi-building me-2"></i>
                                    <?= htmlspecialchars($s['nombre']) ?>
                                </option>
                            <?php endwhile; ?>
                            
                            <?php if ($sedesCount === 0): ?>
                                <option value="" disabled class="text-warning">
                                    ⚠️ No hay sedes activas disponibles
                                </option>
                            <?php endif; ?>
                        </select>
                        
                        <?php if ($sedesCount > 0): ?>
                            <small class="text-muted-custom">
                                <i class="bi bi-diagram-3 me-1"></i>
                                <?= $sedesCount ?> sede(s) activa(s) disponible(s)
                            </small>
                        <?php else: ?>
                            <small class="text-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                No hay sedes registradas. 
                                <a href="sedes.php" class="text-decoration-none">Crear sede</a>
                            </small>
                        <?php endif; ?>
                    </div>

                    <!-- Fila de rol -->
                    <div class="mb-4">
                        <label class="form-label d-flex align-items-center">
                            <i class="bi bi-shield me-2" style="color: #667eea;"></i>
                            Rol del Usuario
                            <span class="badge-required">*</span>
                        </label>
                        <select name="rol" class="form-select" required>
                            <option value="usuario" selected class="py-2">
                                <i class="bi bi-person me-2"></i>
                                👤 Usuario - Acceso básico al sistema
                            </option>
                            <option value="admin" class="py-2">
                                <i class="bi bi-shield-lock me-2"></i>
                                🔐 Administrador - Acceso completo
                            </option>
                        </select>
                        <small class="text-muted-custom">
                            <i class="bi bi-info-circle me-1"></i>
                            Los administradores tienen acceso al panel de control
                        </small>
                    </div>

                    <!-- Botones de acción -->
                    <div class="d-grid gap-3 mt-5">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-save me-2"></i>
                            Guardar Trabajador
                        </button>
                        
                        <button type="reset" class="btn btn-outline-secondary rounded-pill">
                            <i class="bi bi-eraser me-2"></i>
                            Limpiar formulario
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Footer de la tarjeta -->
            <div class="card-footer bg-transparent border-0 p-4 pt-0">
                <div class="alert alert-info bg-light border-0 rounded-3 mb-0">
                    <div class="d-flex">
                        <i class="bi bi-info-circle-fill me-3 fs-4" style="color: #667eea;"></i>
                        <div>
                            <h6 class="fw-semibold mb-2">📌 Notas importantes:</h6>
                            <ul class="small text-secondary mb-0 ps-3">
                                <li>El código biométrico debe ser único en el sistema</li>
                                <li>Los puntos iniciales se asignan automáticamente (0 puntos)</li>
                                <li>Puede asignar una sede más tarde desde editar usuario</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Bootstrap JS y dependencias -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Validación de formulario con JavaScript -->
<script>
(function() {
    'use strict';
    
    // Validación personalizada del formulario
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            const codigo = form.querySelector('[name="codigo"]');
            const nombre = form.querySelector('[name="nombre"]');
            const email = form.querySelector('[name="email"]');
            const password = form.querySelector('[name="password"]');
            
            let isValid = true;
            
            // Validar código biométrico
            if (codigo.value <= 0 || isNaN(codigo.value)) {
                codigo.classList.add('is-invalid');
                isValid = false;
            } else {
                codigo.classList.remove('is-invalid');
            }
            
            // Validar nombre (solo letras y espacios)
            const nombreRegex = /^[A-Za-zÁáÉéÍíÓóÚúÑñ\s]+$/;
            if (!nombreRegex.test(nombre.value)) {
                nombre.classList.add('is-invalid');
                isValid = false;
            } else {
                nombre.classList.remove('is-invalid');
            }
            
            // Validar email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email.value)) {
                email.classList.add('is-invalid');
                isValid = false;
            } else {
                email.classList.remove('is-invalid');
            }
            
            // Validar contraseña
            if (password.value.length < 6) {
                password.classList.add('is-invalid');
                isValid = false;
            } else {
                password.classList.remove('is-invalid');
            }
            
            if (!form.checkValidity() || !isValid) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Mostrar/ocultar contraseña (opcional)
    const togglePassword = document.createElement('button');
    togglePassword.type = 'button';
    togglePassword.className = 'btn btn-outline-secondary position-absolute end-0 top-50 translate-middle-y me-2';
    togglePassword.innerHTML = '<i class="bi bi-eye"></i>';
    togglePassword.style.border = 'none';
    togglePassword.style.background = 'transparent';
    
    const passwordField = document.querySelector('[name="password"]');
    if (passwordField) {
        const parent = passwordField.parentElement;
        parent.style.position = 'relative';
        parent.appendChild(togglePassword);
        
        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
        });
    }
})();
</script>

</body>
</html>
