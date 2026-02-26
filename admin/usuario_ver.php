<?php
session_start();
include(__DIR__ . "/../config/conexion.php");
include(__DIR__ . "/../dashboard/sidebar.php");

// Validar sesión y rol admin
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

$id = intval($_GET['id'] ?? 0);
if ($id === 0) {
    header("Location: ../dashboard/index.php");
    exit();
}

// ============================================
// 1. DATOS DEL USUARIO
// ============================================
$stmt = $conn->prepare("
    SELECT u.id, u.nombre, u.apodo, u.email, u.rol, u.avatar, u.rol_id, u.codigo_biometrico,
           s.nombre AS sede,
           r.nombre AS rol_personalizado
    FROM usuarios u
    LEFT JOIN sedes s ON s.id = u.sede_id
    LEFT JOIN roles r ON r.id = u.rol_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    header("Location: ../dashboard/index.php");
    exit();
}

// ============================================
// 2. PUNTOS TOTALES DEL USUARIO
// ============================================
$stmt = $conn->prepare("SELECT total FROM puntos WHERE usuario_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$puntosRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$puntos_total = intval($puntosRow['total'] ?? 0);

// ============================================
// 3. OBTENER ASISTENCIAS DESDE MARCACIONES
// ============================================
// Primero, obtener configuración de horarios
$config_horario = $conn->query("SELECT * FROM configuracion_horarios WHERE activo = 1 LIMIT 1")->fetch_assoc();

// Si no hay configuración, usar valores por defecto
if (!$config_horario) {
    $hora_entrada = '08:00:00';
    $minutos_tolerancia = 15;
    $hora_tope_temprano = date('H:i:s', strtotime($hora_entrada . ' + ' . $minutos_tolerancia . ' minutes'));
} else {
    $hora_entrada = $config_horario['hora_entrada'];
    $minutos_tolerancia = $config_horario['minutos_tolerancia'];
    $hora_tope_temprano = date('H:i:s', strtotime($hora_entrada . ' + ' . $minutos_tolerancia . ' minutes'));
}

// Filtros
$filtro_mes    = isset($_GET['mes'])    ? $_GET['mes']    : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Construir query base para marcaciones de entrada
$sql_marcaciones = "
    SELECT 
        m.id,
        m.fecha,
        MIN(m.hora) as hora_entrada,
        MAX(CASE WHEN m.tipo_evento = 'salida' THEN m.hora END) as hora_salida,
        COUNT(CASE WHEN m.tipo_evento = 'entrada' THEN 1 END) as total_entradas,
        COUNT(CASE WHEN m.tipo_evento = 'salida' THEN 1 END) as total_salidas
    FROM marcaciones m
    WHERE m.usuario_id = ?
";

$params = [$id];
$types = "i";

if ($filtro_mes !== '') {
    $sql_marcaciones .= " AND DATE_FORMAT(m.fecha, '%Y-%m') = ?";
    $params[] = $filtro_mes;
    $types .= "s";
}

$sql_marcaciones .= " GROUP BY m.fecha ORDER BY m.fecha DESC";

$stmt = $conn->prepare($sql_marcaciones);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$marcaciones = $stmt->get_result();
$stmt->close();

// ============================================
// 4. PROCESAR CADA DÍA PARA DETERMINAR ESTADO
// ============================================
$asistencias_procesadas = [];
$stats = [
    'total' => 0,
    'temprano' => 0,
    'tarde' => 0,
    'falto' => 0
];

while ($dia = $marcaciones->fetch_assoc()) {
    $stats['total']++;
    
    // Determinar estado según la hora de entrada
    $estado = 'falto'; // Por defecto
    
    if (!empty($dia['hora_entrada'])) {
        $hora_entrada_ts = strtotime($dia['hora_entrada']);
        $hora_tope_ts = strtotime($hora_tope_temprano);
        
        if ($hora_entrada_ts <= $hora_tope_ts) {
            $estado = 'temprano';
            $stats['temprano']++;
        } else {
            $estado = 'tarde';
            $stats['tarde']++;
        }
    } else {
        $stats['falto']++;
    }
    
    // Calcular puntos del día
    $puntos_dia = 0;
    if ($estado === 'temprano') $puntos_dia = 5;
    elseif ($estado === 'tarde') $puntos_dia = 3;
    
    $asistencias_procesadas[] = [
        'fecha' => $dia['fecha'],
        'hora_entrada' => $dia['hora_entrada'] ?? null,
        'hora_salida' => $dia['hora_salida'] ?? null,
        'estado' => $estado,
        'puntos' => $puntos_dia,
        'total_entradas' => $dia['total_entradas'],
        'total_salidas' => $dia['total_salidas']
    ];
}

// ============================================
// 5. CALCULAR PUNTOS TOTALES POR ASISTENCIA
// ============================================
$puntos_temprano = $stats['temprano'] * 5;
$puntos_tarde = $stats['tarde'] * 3;
$puntos_asistencias = $puntos_temprano + $puntos_tarde;

// ============================================
// 6. OBTENER HISTORIAL DE PUNTOS (opcional)
// ============================================
$historial_puntos = [];
$stmt = $conn->prepare("
    SELECT * FROM historial_puntos 
    WHERE usuario_id = ? 
    ORDER BY fecha DESC 
    LIMIT 10
");
$stmt->bind_param("i", $id);
$stmt->execute();
$historial = $stmt->get_result();
while ($h = $historial->fetch_assoc()) {
    $historial_puntos[] = $h;
}
$stmt->close();

// ============================================
// 7. FUNCIÓN PARA MOSTRAR ROL
// ============================================
function getRolDisplay($usuario) {
    if (!empty($usuario['rol_personalizado'])) {
        return $usuario['rol_personalizado'] . ' (Personalizado)';
    }
    return $usuario['rol'] === 'admin' ? 'Administrador' : 'Usuario';
}

// Avatar
$avatar_url = '/asistencia-main/assets/img/' . ($usuario['avatar'] ?: 'default.png');

// Mensaje de éxito
$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de <?= htmlspecialchars($usuario['nombre']) ?> | Clínica Gamificación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; box-sizing: border-box; }
        body { background-color: #f8f9fc; }

        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #f8f9fc 100%);
        }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }

        .page-title { font-weight: 700; color: #2d3748; position: relative; padding-bottom: .75rem; margin-bottom: 2rem; }
        .page-title:after { content:''; position:absolute; bottom:0; left:0; width:60px; height:4px; background:linear-gradient(135deg,#667eea,#764ba2); border-radius:2px; }

        .profile-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px; padding: 30px; color: white;
            margin-bottom: 25px; box-shadow: 0 10px 30px rgba(102,126,234,.3);
            position: relative; overflow: hidden;
        }
        .profile-hero::before { content:'👤'; position:absolute; right:20px; bottom:10px; font-size:110px; opacity:.08; }
        .profile-avatar-hero {
            width: 100px; height: 100px; border-radius: 50%;
            border: 4px solid white; object-fit: cover;
            box-shadow: 0 5px 20px rgba(0,0,0,.2);
        }
        .profile-badge { display:inline-block; padding:4px 14px; background:rgba(255,255,255,.2); border-radius:20px; font-size:.85rem; }

        .stat-card {
            background: white; border-radius: 15px; padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,.06); transition: all .3s; height: 100%;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(102,126,234,.15); }
        .stat-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; margin-bottom:12px; }
        .stat-icon i { font-size:1.4rem; color:white; }
        .stat-value { font-size:1.8rem; font-weight:700; color:#2d3748; }
        .stat-label { color:#718096; font-size:.85rem; }

        .table-card {
            background: white; border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,.07); overflow: hidden;
        }
        .table-card-header {
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: white; padding: 18px 24px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;
        }
        .excel-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .excel-table thead th {
            background: #f1f3f9; color: #4a5568;
            font-weight: 600; font-size: .8rem; text-transform: uppercase;
            letter-spacing: .5px; padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        .excel-table thead th:first-child { border-left: 4px solid #667eea; }
        .excel-table tbody tr { transition: background .15s; }
        .excel-table tbody tr:hover { background: #f7f8ff; }
        .excel-table td { padding: 11px 16px; border-bottom: 1px solid #edf2f7; vertical-align: middle; }

        .badge-temprano { background:#d1fae5; color:#065f46; padding:4px 12px; border-radius:20px; font-size:.8rem; font-weight:600; display:inline-block; }
        .badge-tarde    { background:#fef3c7; color:#92400e; padding:4px 12px; border-radius:20px; font-size:.8rem; font-weight:600; display:inline-block; }
        .badge-falto    { background:#fee2e2; color:#991b1b; padding:4px 12px; border-radius:20px; font-size:.8rem; font-weight:600; display:inline-block; }

        .puntos-ganados { font-weight:700; color:#667eea; }

        .filtros-card {
            background: white; border-radius: 15px; padding: 18px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,.06); margin-bottom: 20px;
        }
        .form-control, .form-select {
            border: 2px solid #e2e8f0; border-radius: 10px;
            padding: 9px 14px; font-size: .9rem; transition: all .2s;
        }
        .form-control:focus, .form-select:focus { border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.1); }

        .btn-purple {
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: white; border: none; border-radius: 10px;
            padding: 9px 20px; font-weight: 600; font-size:.9rem; transition: all .3s;
        }
        .btn-purple:hover { transform:translateY(-2px); box-shadow:0 5px 15px rgba(102,126,234,.4); color:white; }
        .btn-outline-purple { border:2px solid #667eea; color:#667eea; border-radius:10px; padding:9px 20px; font-weight:600; font-size:.9rem; transition:all .3s; background:transparent; }
        .btn-outline-purple:hover { background:#667eea; color:white; }
        .btn-outline-success { border:2px solid #48bb78; color:#48bb78; border-radius:10px; padding:9px 20px; font-weight:600; font-size:.9rem; transition:all .3s; background:transparent; }
        .btn-outline-success:hover { background:#48bb78; color:white; }

        .empty-state { text-align:center; padding:50px 20px; color:#a0aec0; }
        .empty-state i { font-size:3rem; display:block; margin-bottom:10px; }
        
        .puntos-resumen {
            background: #f0f4fa; border-radius: 12px; padding: 15px;
            border-left: 4px solid #667eea;
        }
        
        .hora-badge {
            background: #e2e8f0; color: #2d3748; padding: 2px 8px;
            border-radius: 12px; font-size: .75rem; font-weight: 600;
        }
    </style>
</head>
<body>

<main class="main-content">
    <div class="container-fluid">

        <!-- Título -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h1 class="page-title mb-0">
                <i class="bi bi-person-lines-fill me-2" style="color:#667eea;"></i>
                Perfil de <?= htmlspecialchars($usuario['nombre']) ?>
            </h1>
            <div class="d-flex gap-2">
                <a href="exportar_asistencias.php?id=<?= $id ?>" class="btn btn-outline-success" target="_blank">
                    <i class="bi bi-file-earmark-excel me-1"></i> Exportar a Excel
                </a>
                <a href="usuarios.php" class="btn btn-outline-purple">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>
        </div>

        <!-- Alerta éxito -->
        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Hero del usuario -->
        <div class="profile-hero mb-4">
            <div class="row align-items-center g-3">
                <div class="col-auto">
                    <img src="<?= htmlspecialchars($avatar_url) ?>"
                         alt="Avatar de <?= htmlspecialchars($usuario['nombre']) ?>"
                         class="profile-avatar-hero"
                         onerror="this.src='/asistencia-main/assets/img/default.png'">
                </div>
                <div class="col">
                    <h2 class="mb-1 fw-700" style="font-size:1.8rem;">
                        <?= htmlspecialchars($usuario['nombre']) ?>
                        <?php if (!empty($usuario['codigo_biometrico'])): ?>
                            <span class="profile-badge ms-2">#<?= $usuario['codigo_biometrico'] ?></span>
                        <?php endif; ?>
                    </h2>
                    <?php if (!empty($usuario['apodo'])): ?>
                        <div class="mb-1" style="opacity:.85;">
                            <i class="bi bi-star me-1"></i>"<?= htmlspecialchars($usuario['apodo']) ?>"
                        </div>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <span class="profile-badge"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($usuario['email']) ?></span>
                        <span class="profile-badge"><i class="bi bi-building me-1"></i><?= htmlspecialchars($usuario['sede'] ?? 'Sin sede') ?></span>
                        <span class="profile-badge"><i class="bi bi-shield me-1"></i><?= getRolDisplay($usuario) ?></span>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div style="background:rgba(255,255,255,.15);border-radius:15px;padding:16px;">
                        <div style="font-size:.85rem;opacity:.9;">Puntos totales</div>
                        <div style="font-size:2.5rem;font-weight:700;line-height:1.1;"><?= number_format($puntos_total) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats de asistencia -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#667eea,#764ba2)">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?= $stats['total'] ?></div>
                    <div class="stat-label">Días con marcación</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#06d6a0,#05b588)">
                        <i class="bi bi-clock-fill"></i>
                    </div>
                    <div class="stat-value" style="color:#06d6a0;"><?= $stats['temprano'] ?></div>
                    <div class="stat-label">A tiempo</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#ffd166,#edb83d)">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-value" style="color:#edb83d;"><?= $stats['tarde'] ?></div>
                    <div class="stat-label">Tardanzas</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#ef476f,#d64161)">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                    <div class="stat-value" style="color:#ef476f;"><?= $stats['falto'] ?></div>
                    <div class="stat-label">Sin marcación</div>
                </div>
            </div>
        </div>

        <!-- Resumen de puntos por asistencia -->
        <div class="puntos-resumen mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6 class="mb-2"><i class="bi bi-star-fill me-2" style="color:#fbbf24;"></i>Puntos ganados por asistencias</h6>
                    <div class="d-flex flex-wrap gap-4">
                        <div><span style="color:#065f46;font-weight:700;"><?= $stats['temprano'] ?></span> × 5 pts = <strong><?= $puntos_temprano ?> pts</strong></div>
                        <div><span style="color:#92400e;font-weight:700;"><?= $stats['tarde'] ?></span> × 3 pts = <strong><?= $puntos_tarde ?> pts</strong></div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <div class="bg-white p-3 rounded-3">
                        <small class="text-muted d-block">Total puntos por asistencia</small>
                        <span style="font-size:1.8rem;font-weight:700;color:#667eea;"><?= $puntos_asistencias ?></span> pts
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filtros-card">
            <form method="GET" class="row g-2 align-items-end" id="filtrosForm">
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="col-12 col-sm-auto">
                    <label class="form-label fw-600 mb-1">Mes</label>
                    <input type="month" name="mes" class="form-control"
                           value="<?= htmlspecialchars($filtro_mes) ?>"
                           style="min-width:160px;">
                </div>
                <div class="col-12 col-sm-auto">
                    <label class="form-label fw-600 mb-1">Estado</label>
                    <select name="estado" class="form-select" style="min-width:150px;">
                        <option value="">Todos</option>
                        <option value="temprano" <?= $filtro_estado==='temprano'?'selected':'' ?>>✅ Temprano</option>
                        <option value="tarde"    <?= $filtro_estado==='tarde'   ?'selected':'' ?>>⏰ Tarde</option>
                        <option value="falto"    <?= $filtro_estado==='falto'   ?'selected':'' ?>>❌ Sin marcación</option>
                    </select>
                </div>
                <div class="col-12 col-sm-auto d-flex gap-2">
                    <button type="submit" class="btn btn-purple">
                        <i class="bi bi-search me-1"></i>Filtrar
                    </button>
                    <a href="?id=<?= $id ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabla de asistencias -->
        <div class="table-card">
            <div class="table-card-header">
                <div>
                    <i class="bi bi-table me-2"></i>
                    <strong>Registro de Asistencias - <?= htmlspecialchars($usuario['nombre']) ?></strong>
                </div>
                <div>
                    <span class="badge bg-light text-dark">
                        <?= count($asistencias_procesadas) ?> días
                    </span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="excel-table" id="tablaAsistencias">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Día</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Estado</th>
                            <th>Puntos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        $diasSemana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                        
                        // Aplicar filtro de estado si existe
                        $asistencias_filtradas = $asistencias_procesadas;
                        if ($filtro_estado !== '') {
                            $asistencias_filtradas = array_filter($asistencias_procesadas, function($a) use ($filtro_estado) {
                                return $a['estado'] === $filtro_estado;
                            });
                        }
                        
                        if (count($asistencias_filtradas) > 0): 
                            foreach ($asistencias_filtradas as $dia):
                                $diaNombre = $diasSemana[date('w', strtotime($dia['fecha']))] ?? '';
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($dia['fecha'])) ?></strong>
                                <small class="text-muted d-block"><?= $dia['fecha'] ?></small>
                            </td>
                            <td><?= $diaNombre ?></td>
                            <td>
                                <?php if ($dia['hora_entrada']): ?>
                                    <span class="hora-badge">
                                        <?= date('H:i', strtotime($dia['hora_entrada'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#cbd5e0;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($dia['hora_salida']): ?>
                                    <span class="hora-badge">
                                        <?= date('H:i', strtotime($dia['hora_salida'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#cbd5e0;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($dia['estado'] === 'temprano'): ?>
                                    <span class="badge-temprano"><i class="bi bi-check-circle me-1"></i>Temprano</span>
                                <?php elseif ($dia['estado'] === 'tarde'): ?>
                                    <span class="badge-tarde"><i class="bi bi-clock me-1"></i>Tarde</span>
                                <?php else: ?>
                                    <span class="badge-falto"><i class="bi bi-x-circle me-1"></i>Sin marcación</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($dia['puntos'] > 0): ?>
                                    <span class="puntos-ganados">
                                        <i class="bi bi-star-fill me-1" style="color:#fbbf24;"></i>+<?= $dia['puntos'] ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#cbd5e0;">0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-calendar-x"></i>
                                    <h5 class="mt-2">No hay registros de asistencia</h5>
                                    <p class="text-muted">
                                        <?= ($filtro_mes || $filtro_estado) ? 'No se encontraron resultados con los filtros aplicados.' : 'Este usuario aún no tiene marcaciones registradas.' ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Historial de puntos (opcional) -->
        <?php if (!empty($historial_puntos)): ?>
        <div class="table-card mt-4">
            <div class="table-card-header" style="background: linear-gradient(135deg,#48bb78,#38a169);">
                <div>
                    <i class="bi bi-clock-history me-2"></i>
                    <strong>Últimos movimientos de puntos</strong>
                </div>
            </div>
            <div class="table-responsive">
                <table class="excel-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Puntos</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial_puntos as $h): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($h['fecha'])) ?></td>
                            <td><?= htmlspecialchars($h['concepto'] ?? 'Sin concepto') ?></td>
                            <td>
                                <span style="color:<?= $h['tipo'] === 'ganado' ? '#06d6a0' : '#ef476f' ?>;font-weight:700;">
                                    <?= $h['tipo'] === 'ganado' ? '+' : '-' ?><?= $h['puntos'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $h['tipo'] === 'ganado' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $h['tipo'] === 'ganado' ? 'Ganado' : 'Canjeado' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>