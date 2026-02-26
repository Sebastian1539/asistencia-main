<?php
session_start();

// ============================================================
// MODO DEBUG: Cambia a false cuando todo funcione
// ============================================================
$debug = false; // Cambiado a false para producción

include("../config/conexion.php");

// Acepta $conn, $con, $db o $mysqli como nombre de conexión
if (!isset($conn)) {
    if (isset($con))        { $conn = $con; }
    elseif (isset($db))     { $conn = $db; }
    elseif (isset($mysqli)) { $conn = $mysqli; }
    else { die("❌ ERROR: No se encontró variable de conexión. Revisa conexion.php y asegúrate de que exporta \$conn, \$con, \$db o \$mysqli"); }
}

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    if ($debug) {
        die("❌ DEBUG: No hay sesión activa.<br>Variables de sesión disponibles: <pre>" . print_r($_SESSION, true) . "</pre>");
    }
    header("Location: ../auth/login.php");
    exit();
}

$uid = intval($_SESSION["user_id"]);

// -------------------------------------------------------
// Consulta principal con manejo explícito de errores
// -------------------------------------------------------
$perfil       = null;
$queryError = null;

// Modificada la consulta para incluir el rol personalizado
$sql = "SELECT u.id, u.nombre, u.apodo, u.email, u.rol, u.rol_id, u.sede_id,
               u.avatar, u.fecha_registro, u.codigo_biometrico,
               s.nombre AS sede_nombre,
               r.nombre AS rol_personalizado_nombre
        FROM usuarios u
        LEFT JOIN sedes s ON u.sede_id = s.id
        LEFT JOIN roles r ON u.rol_id = r.id
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $queryError = "prepare() falló: " . $conn->error;
} else {
    $stmt->bind_param("i", $uid);
    if (!$stmt->execute()) {
        $queryError = "execute() falló: " . $stmt->error;
    } else {
        $userRes = $stmt->get_result();
        if ($userRes && $userRes->num_rows > 0) {
            $perfil = $userRes->fetch_assoc();
        } else {
            $queryError = "No se encontró usuario con id=$uid en la tabla usuarios.";
        }
    }
    $stmt->close();
}

// Si no se encontró el usuario y no estamos en modo debug → redirigir
if (!$perfil) {
    if (!$debug) {
        session_destroy();
        header("Location: ../auth/login.php");
        exit();
    }
    $perfil = []; // En debug dejamos seguir para ver el estado de la página
}

// -------------------------------------------------------
// Consulta DISC
// -------------------------------------------------------
$disc = ['dominante' => 0, 'influyente' => 0, 'estable' => 0, 'cumplidor' => 0];
$stmtDisc = $conn->prepare("SELECT dominante, influyente, estable, cumplidor FROM resultados_disc WHERE usuario_id = ?");
if ($stmtDisc) {
    $stmtDisc->bind_param("i", $uid);
    $stmtDisc->execute();
    $discRes = $stmtDisc->get_result();
    if ($discRes && $discRes->num_rows > 0) {
        $discData = $discRes->fetch_assoc();
        if (is_array($discData)) $disc = $discData;
    }
    $stmtDisc->close();
}

$total = array_sum($disc);
$D = ($total > 0) ? round($disc['dominante']  * 100 / $total) : 0;
$I = ($total > 0) ? round($disc['influyente'] * 100 / $total) : 0;
$S = ($total > 0) ? round($disc['estable']    * 100 / $total) : 0;
$C = ($total > 0) ? round($disc['cumplidor']  * 100 / $total) : 0;

$avatar  = !empty($perfil['avatar']) ? $perfil['avatar'] : 'default.png';
$mensaje = $_SESSION['mensaje'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['mensaje'], $_SESSION['error']);

// Función para determinar el badge del rol según el tipo
function getRolBadge($perfil) {
    // Si tiene rol personalizado
    if (!empty($perfil['rol_personalizado_nombre'])) {
        $rol_nombre = $perfil['rol_personalizado_nombre'];
        
        // Asignar color según el tipo de rol personalizado
        if (stripos($rol_nombre, 'enfermer') !== false || stripos($rol_nombre, 'enfermera') !== false || stripos($rol_nombre, 'enfermero') !== false) {
            return '<span class="badge-role" style="background: linear-gradient(135deg, #4aa3df 0%, #2c7abe 100%);">
                        <i class="bi bi-heart-pulse me-1"></i>' . htmlspecialchars($rol_nombre) . '
                    </span>';
        } elseif (stripos($rol_nombre, 'limpieza') !== false || stripos($rol_nombre, 'aseo') !== false || stripos($rol_nombre, 'mantenimiento') !== false) {
            return '<span class="badge-role" style="background: linear-gradient(135deg, #9aa9b9 0%, #6c7a8d 100%);">
                        <i class="bi bi-brush me-1"></i>' . htmlspecialchars($rol_nombre) . '
                    </span>';
        } elseif (stripos($rol_nombre, 'medico') !== false || stripos($rol_nombre, 'médico') !== false || stripos($rol_nombre, 'doctor') !== false) {
            return '<span class="badge-role" style="background: linear-gradient(135deg, #38b2ac 0%, #2c7a7b 100%);">
                        <i class="bi bi-hospital me-1"></i>' . htmlspecialchars($rol_nombre) . '
                    </span>';
        } elseif (stripos($rol_nombre, 'administrativo') !== false || stripos($rol_nombre, 'secretaria') !== false || stripos($rol_nombre, 'recepcion') !== false) {
            return '<span class="badge-role" style="background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);">
                        <i class="bi bi-file-text me-1"></i>' . htmlspecialchars($rol_nombre) . '
                    </span>';
        } elseif (stripos($rol_nombre, 'seguridad') !== false || stripos($rol_nombre, 'vigilante') !== false) {
            return '<span class="badge-role" style="background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);">
                        <i class="bi bi-shield me-1"></i>' . htmlspecialchars($rol_nombre) . '
                    </span>';
        } else {
            // Color genérico para roles personalizados no categorizados
            return '<span class="badge-role" style="background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);">
                        <i class="bi bi-star-fill me-1"></i>' . htmlspecialchars($rol_nombre) . '
                    </span>';
        }
    } else {
        // Roles del sistema
        if ($perfil['rol'] === 'admin') {
            return '<span class="badge-role badge-admin">
                        <i class="bi bi-shield-lock me-1"></i>Administrador
                    </span>';
        } else {
            return '<span class="badge-role" style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);">
                        <i class="bi bi-person me-1"></i>Usuario
                    </span>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | Clínica Gamificación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #f8f9fc; }
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            background: linear-gradient(135deg, #f5f7fa 0%, #f8f9fc 100%);
        }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 20px; } }
        .page-title {
            font-weight: 700; color: #2d3748;
            position: relative; padding-bottom: .75rem; margin-bottom: 2rem;
        }
        .page-title:after {
            content: ''; position: absolute; bottom: 0; left: 0;
            width: 60px; height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        .perfil-card {
            background: white; border-radius: 20px; padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,.08);
            max-width: 900px; margin: 0 auto;
            transition: transform .3s ease;
        }
        .perfil-card:hover { transform: translateY(-5px); }
        .perfil-grid { display: grid; grid-template-columns: 200px 1fr; gap: 30px; align-items: start; }
        @media (max-width: 768px) { .perfil-grid { grid-template-columns: 1fr; } }
        .avatar-section { text-align: center; }
        .avatar-container { position: relative; width: 180px; height: 180px; margin: 0 auto 20px; }
        .avatar {
            width: 100%; height: 100%; border-radius: 50%; object-fit: cover;
            border: 4px solid #fff; box-shadow: 0 5px 20px rgba(102,126,234,.3);
            transition: all .3s ease;
        }
        .avatar:hover { transform: scale(1.05); box-shadow: 0 8px 25px rgba(102,126,234,.4); }
        .avatar-upload { margin-top: 15px; }
        .avatar-upload .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; padding: 8px 20px;
            border-radius: 25px; font-size: .9rem; font-weight: 500; transition: all .3s ease;
        }
        .avatar-upload .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,.4); }
        .info-section { padding: 10px 0; }
        .info-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #e2e8f0; }
        .info-item:last-child { border-bottom: none; }
        .info-icon {
            width: 40px; height: 40px; flex-shrink: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin-right: 15px; color: white; font-size: 1.2rem;
        }
        .info-label { font-weight: 600; color: #4a5568; min-width: 100px; }
        .info-value { color: #2d3748; font-weight: 500; }
        .badge-role {
            padding: 5px 12px; border-radius: 20px; font-size: .85rem; font-weight: 500;
            display: inline-flex; align-items: center; color: white;
        }
        .badge-admin { background: linear-gradient(135deg, #fc8181 0%, #f56565 100%); }
        .section-title { font-size: 1.2rem; font-weight: 600; color: #2d3748; margin: 30px 0 20px; display: flex; align-items: center; }
        .section-title i { margin-right: 10px; color: #667eea; }
        .form-group { margin-bottom: 20px; }
        .form-group label { font-weight: 500; color: #4a5568; margin-bottom: 8px; display: block; }
        .form-control { border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px 15px; font-size: .95rem; transition: all .2s ease; }
        .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.1); outline: none; }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none; border-radius: 12px; padding: 12px 25px; font-weight: 600;
            transition: all .3s ease; box-shadow: 0 4px 15px rgba(102,126,234,.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102,126,234,.4); }
        .btn-secondary {
            background: linear-gradient(135deg, #a0aec0 0%, #718096 100%);
            border: none; border-radius: 12px; padding: 12px 25px; font-weight: 600; transition: all .3s ease;
        }
        .disc-chart-container { background: #f7fafc; border-radius: 15px; padding: 20px; margin-top: 20px; }
        .disc-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px; }
        .disc-stat-item { background: white; border-radius: 12px; padding: 15px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
        .disc-stat-value { font-size: 1.5rem; font-weight: 700; color: #667eea; }
        .disc-stat-label { font-size: .8rem; color: #718096; text-transform: uppercase; letter-spacing: .5px; }
        .alert { border-radius: 12px; border: none; padding: 1rem 1.25rem; margin-bottom: 1.5rem; }
        
        /* Estilos específicos para badges de roles personalizados */
        .rol-info-card {
            background: #f0f4fa;
            border-radius: 12px;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        .rol-info-card i {
            color: #667eea;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <?php include("../dashboard/sidebar.php"); ?>

    <main class="main-content">
        <div class="container-fluid">
            <h1 class="page-title">
                <i class="bi bi-person-circle me-2" style="color:#667eea;"></i>Mi Perfil
            </h1>

            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="perfil-card">
                <div class="perfil-grid">

                    <!-- Avatar -->
                    <div class="avatar-section">
                        <div class="avatar-container">
                            <img class="avatar"
                                 src="/asistencia-main/assets/img/<?= htmlspecialchars($avatar) ?>"
                                 alt="Avatar" id="avatarPreview"
                                 onerror="this.src='/asistencia-main/assets/img/default.png'">
                        </div>
                        <form action="actualizar.php" method="POST" enctype="multipart/form-data" class="avatar-upload">
                            <button type="button" class="btn" onclick="document.getElementById('avatarInput').click();">
                                <i class="bi bi-camera me-2"></i>Cambiar foto
                            </button>
                            <input type="file" id="avatarInput" name="avatar"
                                   accept="image/jpeg,image/png,image/gif"
                                   style="display:none;" onchange="previewAvatar(this)">
                            <button type="submit" name="actualizar_avatar" class="btn mt-2" style="background:#48bb78;">
                                <i class="bi bi-check-circle me-2"></i>Guardar foto
                            </button>
                        </form>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle me-1"></i>Formatos: JPG, PNG, GIF (Máx: 2MB)
                        </small>
                    </div>

                    <!-- Información -->
                    <div class="info-section">
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-person"></i></div>
                            <span class="info-label">Nombre:</span>
                            <span class="info-value">
                                <strong><?= !empty($perfil['nombre']) ? htmlspecialchars($perfil['nombre']) : 'No disponible' ?></strong>
                            </span>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-star"></i></div>
                            <span class="info-label">Apodo:</span>
                            <span class="info-value"><?= !empty($perfil['apodo']) ? htmlspecialchars($perfil['apodo']) : 'No definido' ?></span>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-envelope"></i></div>
                            <span class="info-label">Correo:</span>
                            <span class="info-value"><?= !empty($perfil['email']) ? htmlspecialchars($perfil['email']) : 'No disponible' ?></span>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-building"></i></div>
                            <span class="info-label">Sede:</span>
                            <span class="info-value"><?= !empty($perfil['sede_nombre']) ? htmlspecialchars($perfil['sede_nombre']) : 'No asignada' ?></span>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-shield"></i></div>
                            <span class="info-label">Rol:</span>
                            <span class="info-value">
                                <?= getRolBadge($perfil) ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($perfil['rol_personalizado_nombre'])): ?>
                        <!-- Información adicional del rol personalizado -->
                        <div class="rol-info-card mt-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle-fill fs-5"></i>
                                <div>
                                    <small class="text-muted d-block">Tipo de rol personalizado</small>
                                    <span class="fw-semibold">
                                        <?php 
                                        if (stripos($perfil['rol_personalizado_nombre'], 'enfermer') !== false) {
                                            echo '<i class="bi bi-heart-pulse me-1 text-danger"></i> Personal de Enfermería';
                                        } elseif (stripos($perfil['rol_personalizado_nombre'], 'limpieza') !== false) {
                                            echo '<i class="bi bi-brush me-1 text-secondary"></i> Personal de Limpieza';
                                        } elseif (stripos($perfil['rol_personalizado_nombre'], 'medico') !== false || stripos($perfil['rol_personalizado_nombre'], 'médico') !== false) {
                                            echo '<i class="bi bi-hospital me-1 text-teal"></i> Personal Médico';
                                        } elseif (stripos($perfil['rol_personalizado_nombre'], 'seguridad') !== false) {
                                            echo '<i class="bi bi-shield me-1 text-dark"></i> Personal de Seguridad';
                                        } else {
                                            echo '<i class="bi bi-star-fill me-1 text-purple"></i> Rol Personalizado';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Editar información -->
                <div class="section-title"><i class="bi bi-pencil-square"></i>Editar información</div>
                <form action="actualizar.php" method="POST" class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="bi bi-star me-2"></i>Apodo / Nickname</label>
                            <input type="text" name="apodo" class="form-control"
                                   placeholder="Ej: Juanito123"
                                   value="<?= htmlspecialchars($perfil['apodo'] ?? '') ?>">
                            <small class="text-muted">Opcional - Cómo te llamarán en el sistema</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="bi bi-envelope me-2"></i>Correo electrónico</label>
                            <input type="email" name="email" class="form-control"
                                   placeholder="tucorreo@ejemplo.com"
                                   value="<?= htmlspecialchars($perfil['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="actualizar_datos" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Actualizar información
                        </button>
                    </div>
                </form>

                <!-- Cambiar contraseña -->
                <div class="section-title mt-4"><i class="bi bi-shield-lock"></i>Cambiar contraseña</div>
                <form action="actualizar.php" method="POST" class="row g-3" id="passwordForm">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><i class="bi bi-key me-2"></i>Contraseña actual</label>
                            <input type="password" name="pass_actual" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><i class="bi bi-key-fill me-2"></i>Nueva contraseña</label>
                            <input type="password" name="pass_nueva" class="form-control" placeholder="Mínimo 6 caracteres" minlength="6" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><i class="bi bi-key-fill me-2"></i>Confirmar contraseña</label>
                            <input type="password" name="pass_confirmar" class="form-control" placeholder="Repite la contraseña" minlength="6" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="cambiar_password" class="btn btn-primary">
                            <i class="bi bi-shield-check me-2"></i>Cambiar contraseña
                        </button>
                    </div>
                </form>

                <!-- DISC -->
                <div class="section-title mt-4"><i class="bi bi-bar-chart"></i>Perfil DISC</div>
                <div class="disc-chart-container">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <canvas id="discChart" width="300" height="300"></canvas>
                        </div>
                        <div class="col-md-6">
                            <div class="disc-stats">
                                <div class="disc-stat-item">
                                    <div class="disc-stat-value"><?= $D ?>%</div>
                                    <div class="disc-stat-label">Dominante</div>
                                    <small class="text-muted">Decidido, directo</small>
                                </div>
                                <div class="disc-stat-item">
                                    <div class="disc-stat-value"><?= $I ?>%</div>
                                    <div class="disc-stat-label">Influyente</div>
                                    <small class="text-muted">Sociable, entusiasta</small>
                                </div>
                                <div class="disc-stat-item">
                                    <div class="disc-stat-value"><?= $S ?>%</div>
                                    <div class="disc-stat-label">Estable</div>
                                    <small class="text-muted">Paciente, leal</small>
                                </div>
                                <div class="disc-stat-item">
                                    <div class="disc-stat-value"><?= $C ?>%</div>
                                    <div class="disc-stat-label">Cumplidor</div>
                                    <small class="text-muted">Analítico, preciso</small>
                                </div>
                            </div>
                            <?php if ($total === 0): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Aún no has realizado la encuesta DISC.
                                    <a href="../encuestas/disc.php" class="alert-link">Realizar encuesta</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="../dashboard/index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Volver al Dashboard
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => document.getElementById('avatarPreview').src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('discChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Dominante (D)', 'Influyente (I)', 'Estable (S)', 'Cumplidor (C)'],
                        datasets: [{
                            data: [<?= $D ?>, <?= $I ?>, <?= $S ?>, <?= $C ?>],
                            backgroundColor: ['rgba(239,71,111,.8)', 'rgba(255,209,102,.8)', 'rgba(6,214,160,.8)', 'rgba(17,138,178,.8)'],
                            borderColor: ['#ef476f', '#ffd166', '#06d6a0', '#118ab2'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'bottom', labels: { font: { size: 12, family: 'Inter' } } },
                            tooltip: { callbacks: { label: c => `${c.label}: ${c.raw}%` } }
                        }
                    }
                });
            }
        });
        document.getElementById('passwordForm')?.addEventListener('submit', function (e) {
            const pn = this.querySelector('[name="pass_nueva"]');
            const pc = this.querySelector('[name="pass_confirmar"]');
            if (pn.value !== pc.value) { e.preventDefault(); alert('Las contraseñas nuevas no coinciden'); pc.classList.add('is-invalid'); }
            if (pn.value.length < 6)   { e.preventDefault(); alert('La contraseña debe tener al menos 6 caracteres'); pn.classList.add('is-invalid'); }
        });
    </script>
</body>
</html>