<?php
// 1. Iniciar sesión y conexión
session_start();
include("../config/conexion.php");

$error = "";

// 2. Lógica de autenticación (Tu funcionalidad original)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $sql = "SELECT id, nombre, email, password, rol FROM usuarios WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Error en prepare(): " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 1) {
        $user = $res->fetch_assoc();

        if (password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["nombre"]  = $user["nombre"];
            $_SESSION["rol"]     = $user["rol"];

            header("Location: ../dashboard/index.php");
            exit();
        } else {
            $error = "❌ Contraseña incorrecta";
        }
    } else {
        $error = "❌ Usuario no encontrado";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Centro Médico AMC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            background-color: #eef2f7;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        .login-header {
            background-color: #d1f2ff; /* Celeste AMC */
            padding: 40px 20px;
            text-align: center;
        }

        .login-body {
            padding: 30px 40px;
        }

        /* Inputs estilo minimalista (solo línea inferior) */
        .form-control-minimal {
            border: none;
            border-bottom: 1px solid #dee2e6;
            border-radius: 0;
            padding: 10px 0;
            box-shadow: none !important;
        }

        .form-control-minimal:focus {
            border-color: #727cf5;
        }

        .btn-ingresar {
            background-color: #727cf5;
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 5px;
            margin-top: 20px;
        }

        .input-group-text {
            background: none;
            border: none;
            border-bottom: 1px solid #dee2e6;
            border-radius: 0;
            color: #adb5bd;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <img src="../assets/img/logo_principal.png" alt="Logo" style="max-height: 70px;" 
             onerror="this.src='https://via.placeholder.com/180x60/d1f2ff/333?text=Centro+Medico+AMC'">
    </div>

    <div class="login-body">
        <h5 class="text-center mb-1">Ingresar</h5>
        <p class="text-center text-muted small mb-4">Ingresa tu email y contraseña para continuar.</p>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted mb-0">Email</label>
                <input type="email" name="email" class="form-control form-control-minimal" 
                       placeholder="Ingresa tu email" required 
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold text-muted mb-0">Contraseña</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control form-control-minimal" 
                           placeholder="Ingresa tu contraseña" required>
                    <span class="input-group-text" onclick="togglePassword()" style="cursor: pointer;">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small mb-3 border-0">
                    <i class="bi bi-exclamation-circle me-2"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                <label class="form-check-label small text-muted" for="remember">Recuérdame</label>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-ingresar">Ingresar</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Función para mostrar/ocultar contraseña
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    }
</script>

</body>
</html>
