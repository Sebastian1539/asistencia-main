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

// Datos del usuario
$stmt = $conn->prepare("
    SELECT u.id, u.nombre, u.apodo, u.email, u.rol, u.avatar,
           s.nombre AS sede
    FROM usuarios u
    LEFT JOIN sedes s ON s.id = u.sede_id
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

// Puntos totales
$stmt = $conn->prepare("SELECT total FROM puntos WHERE usuario_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$puntosRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$puntos_total = intval($puntosRow['total'] ?? 0);

// Filtros
$filtro_mes    = isset($_GET['mes'])    ? $_GET['mes']    : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Construir query con filtros
$where = "WHERE usuario_id = ?";
$params = [$id];
$types  = "i";

if ($filtro_mes !== '') {
    $where .= " AND DATE_FORMAT(fecha, '%Y-%m') = ?";
    $params[] = $filtro_mes;
    $types   .= "s";
}
if ($filtro_estado !== '') {
    $where .= " AND estado = ?";
    $params[] = $filtro_estado;
    $types   .= "s";
}

$stmt = $conn->prepare("SELECT * FROM asistencias $where ORDER BY fecha DESC");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$asistencias = $stmt->get_result();
$stmt->close();

// Estadísticas del usuario
$stmt = $conn->prepare("
    SELECT
        COUNT(*)                                              AS total,
        SUM(CASE WHEN estado='temprano' THEN 1 ELSE 0 END)  AS temprano,
        SUM(CASE WHEN estado='tarde'    THEN 1 ELSE 0 END)  AS tarde,
        SUM(CASE WHEN estado='falto'    THEN 1 ELSE 0 END)  AS faltas
    FROM asistencias WHERE usuario_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Avatar
$avatar_url = '/asistencia-main/assets/img/' . ($usuario['avatar'] ?: 'default.png');

// Mensaje de éxito tras editar
$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil Usuario | Clínica Gamificación</title>
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

        /* Título */
        .page-title { font-weight: 700; color: #2d3748; position: relative; padding-bottom: .75rem; margin-bottom: 2rem; }
        .page-title:after { content:''; position:absolute; bottom:0; left:0; width:60px; height:4px; background:linear-gradient(135deg,#667eea,#764ba2); border-radius:2px; }

        /* Tarjeta de perfil */
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

        /* Stats cards */
        .stat-card {
            background: white; border-radius: 15px; padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,.06); transition: all .3s; height: 100%;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(102,126,234,.15); }
        .stat-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; margin-bottom:12px; }
        .stat-icon i { font-size:1.4rem; color:white; }
        .stat-value { font-size:1.8rem; font-weight:700; color:#2d3748; }
        .stat-label { color:#718096; font-size:.85rem; }

        /* Tabla tipo Excel */
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
        .excel-table tbody tr:nth-child(even) { background: #fafbff; }
        .excel-table tbody tr:nth-child(even):hover { background: #f0f2ff; }
        .excel-table td { padding: 11px 16px; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
        .excel-table td:first-child { font-weight: 600; color: #2d3748; border-left: 4px solid transparent; }
        .excel-table tr:hover td:first-child { border-left-color: #667eea; }

        /* Badges estado */
        .badge-temprano { background:#d1fae5; color:#065f46; padding:4px 12px; border-radius:20px; font-size:.8rem; font-weight:600; }
        .badge-tarde    { background:#fef3c7; color:#92400e; padding:4px 12px; border-radius:20px; font-size:.8rem; font-weight:600; }
        .badge-falto    { background:#fee2e2; color:#991b1b; padding:4px 12px; border-radius:20px; font-size:.8rem; font-weight:600; }

        /* Puntos ganados */
        .puntos-ganados { font-weight:700; color:#667eea; }

        /* Filtros */
        .filtros-card {
            background: white; border-radius: 15px; padding: 18px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,.06); margin-bottom: 20px;
        }
        .form-control, .form-select {
            border: 2px solid #e2e8f0; border-radius: 10px;
            padding: 9px 14px; font-size: .9rem; transition: all .2s;
        }
        .form-control:focus, .form-select:focus { border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.1); }

        /* Botones */
        .btn-purple {
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: white; border: none; border-radius: 10px;
            padding: 9px 20px; font-weight: 600; font-size:.9rem; transition: all .3s;
        }
        .btn-purple:hover { transform:translateY(-2px); box-shadow:0 5px 15px rgba(102,126,234,.4); color:white; }
        .btn-outline-purple { border:2px solid #667eea; color:#667eea; border-radius:10px; padding:9px 20px; font-weight:600; font-size:.9rem; transition:all .3s; background:transparent; }
        .btn-outline-purple:hover { background:#667eea; color:white; }

        /* Modal editar */
        .modal-content { border-radius:20px; border:none; }
        .modal-header { background:linear-gradient(135deg,#667eea,#764ba2); color:white; border-radius:20px 20px 0 0; border:none; }
        .modal-header .btn-close { filter:brightness(0) invert(1); }

        /* Select inline */
        .select-estado {
            border: 2px solid #e2e8f0; border-radius: 8px;
            padding: 5px 10px; font-size:.85rem; cursor:pointer; transition:border-color .2s;
        }
        .select-estado:focus { border-color:#667eea; outline:none; }

        /* Paginación */
        .pagination .page-link { border-radius:8px; margin:0 2px; border:2px solid #e2e8f0; color:#667eea; }
        .pagination .page-item.active .page-link { background:linear-gradient(135deg,#667eea,#764ba2); border-color:#667eea; }

        .empty-state { text-align:center; padding:50px 20px; color:#a0aec0; }
        .empty-state i { font-size:3rem; display:block; margin-bottom:10px; }
    </style>
</head>
<body>

<main class="main-content">
    <div class="container-fluid">

        <!-- Título -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h1 class="page-title mb-0">
                <i class="bi bi-person-lines-fill me-2" style="color:#667eea;"></i>
                Perfil del Usuario
            </h1>
            <a href="javascript:history.back()" class="btn btn-outline-purple">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
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
                         alt="Avatar"
                         class="profile-avatar-hero"
                         onerror="this.src='https://via.placeholder.com/100/667eea/ffffff?text=<?= urlencode(substr($usuario['nombre'],0,1)) ?>'">
                </div>
                <div class="col">
                    <h2 class="mb-1 fw-700" style="font-size:1.8rem;font-weight:700;">
                        <?= htmlspecialchars($usuario['nombre']) ?>
                    </h2>
                    <?php if (!empty($usuario['apodo'])): ?>
                        <div class="mb-1" style="opacity:.85;font-size:.95rem;">
                            <i class="bi bi-star me-1"></i><?= htmlspecialchars($usuario['apodo']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <span class="profile-badge"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($usuario['email']) ?></span>
                        <span class="profile-badge"><i class="bi bi-building me-1"></i><?= htmlspecialchars($usuario['sede'] ?? 'Sin sede') ?></span>
                        <span class="profile-badge"><i class="bi bi-shield me-1"></i><?= $usuario['rol'] === 'admin' ? 'Administrador' : 'Usuario' ?></span>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div style="background:rgba(255,255,255,.15);border-radius:15px;padding:16px;">
                        <div style="font-size:.85rem;opacity:.9;text-transform:uppercase;letter-spacing:1px;">Puntos totales</div>
                        <div style="font-size:2.5rem;font-weight:700;line-height:1.1;"><?= number_format($puntos_total) ?></div>
                        <div style="font-size:.8rem;opacity:.8;">pts acumulados</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#667eea,#764ba2)">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?= intval($stats['total'] ?? 0) ?></div>
                    <div class="stat-label">Total Asistencias</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#06d6a0,#05b588)">
                        <i class="bi bi-clock-fill"></i>
                    </div>
                    <div class="stat-value" style="color:#06d6a0;"><?= intval($stats['temprano'] ?? 0) ?></div>
                    <div class="stat-label">A tiempo</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#ffd166,#edb83d)">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-value" style="color:#edb83d;"><?= intval($stats['tarde'] ?? 0) ?></div>
                    <div class="stat-label">Tardanzas</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#ef476f,#d64161)">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                    <div class="stat-value" style="color:#ef476f;"><?= intval($stats['faltas'] ?? 0) ?></div>
                    <div class="stat-label">Faltas</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filtros-card">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="col-12 col-sm-auto">
                    <label class="form-label fw-600 mb-1" style="font-weight:600;color:#4a5568;font-size:.85rem;">
                        <i class="bi bi-calendar3 me-1"></i>Mes
                    </label>
                    <input type="month" name="mes" class="form-control"
                           value="<?= htmlspecialchars($filtro_mes) ?>"
                           style="min-width:160px;">
                </div>
                <div class="col-12 col-sm-auto">
                    <label class="form-label fw-600 mb-1" style="font-weight:600;color:#4a5568;font-size:.85rem;">
                        <i class="bi bi-funnel me-1"></i>Estado
                    </label>
                    <select name="estado" class="form-select" style="min-width:150px;">
                        <option value="">Todos</option>
                        <option value="temprano" <?= $filtro_estado==='temprano'?'selected':'' ?>>✅ Temprano</option>
                        <option value="tarde"    <?= $filtro_estado==='tarde'   ?'selected':'' ?>>⏰ Tarde</option>
                        <option value="falto"    <?= $filtro_estado==='falto'   ?'selected':'' ?>>❌ Faltó</option>
                    </select>
                </div>
                <div class="col-12 col-sm-auto d-flex gap-2">
                    <button type="submit" class="btn btn-purple">
                        <i class="bi bi-search me-1"></i>Filtrar
                    </button>
                    <a href="?id=<?= $id ?>" class="btn btn-outline-secondary" style="border-radius:10px;">
                        <i class="bi bi-x-lg me-1"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabla tipo Excel -->
        <div class="table-card">
            <div class="table-card-header">
                <div>
                    <i class="bi bi-table me-2"></i>
                    <strong>Registro de Asistencias</strong>
                    <?php if ($filtro_mes || $filtro_estado): ?>
                        <span class="badge bg-warning text-dark ms-2" style="font-size:.75rem;">Filtrado</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-light text-dark" style="font-size:.8rem;">
                        <?= $asistencias->num_rows ?> registros
                    </span>
                    <!-- Botón exportar (visual) -->
                    <button class="btn btn-sm" style="background:rgba(255,255,255,.2);color:white;border-radius:8px;border:1px solid rgba(255,255,255,.3);"
                            title="Exportar a CSV" onclick="exportarCSV()">
                        <i class="bi bi-download me-1"></i>Exportar
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="excel-table" id="tablaAsistencias">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th><i class="bi bi-calendar3 me-1"></i>Fecha</th>
                            <th><i class="bi bi-calendar-week me-1"></i>Día</th>
                            <th><i class="bi bi-circle-fill me-1"></i>Estado</th>
                            <th><i class="bi bi-star me-1"></i>Puntos</th>
                            <th style="width:200px;"><i class="bi bi-pencil me-1"></i>Editar estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($asistencias->num_rows > 0):
                            $i = 1;
                            while ($a = $asistencias->fetch_assoc()):
                                $diasSemana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                                $diaNombre  = $diasSemana[date('w', strtotime($a['fecha']))] ?? '';
                                $puntosRow  = $a['estado'] === 'temprano' ? 5 : ($a['estado'] === 'tarde' ? 3 : 0);
                        ?>
                        <tr>
                            <td class="text-muted" style="font-size:.8rem;"><?= $i++ ?></td>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($a['fecha'])) ?></strong>
                            </td>
                            <td>
                                <span style="color:#718096;font-size:.85rem;"><?= $diaNombre ?></span>
                            </td>
                            <td>
                                <?php if ($a['estado'] === 'temprano'): ?>
                                    <span class="badge-temprano"><i class="bi bi-check-circle me-1"></i>Temprano</span>
                                <?php elseif ($a['estado'] === 'tarde'): ?>
                                    <span class="badge-tarde"><i class="bi bi-clock me-1"></i>Tarde</span>
                                <?php else: ?>
                                    <span class="badge-falto"><i class="bi bi-x-circle me-1"></i>Faltó</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($puntosRow > 0): ?>
                                    <span class="puntos-ganados">
                                        <i class="bi bi-star-fill me-1" style="color:#fbbf24;font-size:.85rem;"></i>+<?= $puntosRow ?> pts
                                    </span>
                                <?php else: ?>
                                    <span style="color:#cbd5e0;font-size:.85rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- Edición inline con AJAX -->
                                <div class="d-flex align-items-center gap-2">
                                    <select class="select-estado"
                                            data-id="<?= $a['id'] ?>"
                                            onchange="guardarEstado(this)">
                                        <option value="temprano" <?= $a['estado']==='temprano'?'selected':'' ?>>✅ Temprano</option>
                                        <option value="tarde"    <?= $a['estado']==='tarde'   ?'selected':'' ?>>⏰ Tarde</option>
                                        <option value="falto"    <?= $a['estado']==='falto'   ?'selected':'' ?>>❌ Faltó</option>
                                    </select>
                                    <span class="guardado-ok" id="ok-<?= $a['id'] ?>"
                                          style="color:#06d6a0;font-size:.85rem;display:none;">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="bi bi-calendar-x"></i>
                                    No hay registros de asistencia
                                    <?= ($filtro_mes || $filtro_estado) ? 'con los filtros aplicados' : '' ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pie de tabla -->
            <?php if ($asistencias->num_rows > 0): ?>
            <div style="padding:14px 20px;background:#fafbff;border-top:1px solid #edf2f7;display:flex;flex-wrap:wrap;gap:15px;align-items:center;justify-content:space-between;font-size:.85rem;color:#718096;">
                <div class="d-flex gap-3 flex-wrap">
                    <span><span style="color:#065f46;font-weight:700;"><?= $stats['temprano'] ?></span> temprano (<?= $stats['temprano']*5 ?> pts)</span>
                    <span><span style="color:#92400e;font-weight:700;"><?= $stats['tarde'] ?></span> tarde (<?= $stats['tarde']*3 ?> pts)</span>
                    <span><span style="color:#991b1b;font-weight:700;"><?= $stats['faltas'] ?></span> faltas (0 pts)</span>
                </div>
                <div>
                    <strong style="color:#667eea;">Total puntos por asistencia: <?= ($stats['temprano']*5 + $stats['tarde']*3) ?> pts</strong>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /container -->
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Guardado inline vía fetch (sin recargar la página)
function guardarEstado(select) {
    const asistenciaId = select.dataset.id;
    const nuevoEstado  = select.value;
    const okIcon       = document.getElementById('ok-' + asistenciaId);

    select.disabled = true;

    fetch('usuario_editar_asistencia.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${asistenciaId}&estado=${nuevoEstado}&ajax=1`
    })
    .then(r => r.json())
    .then(data => {
        select.disabled = false;
        if (data.ok) {
            // Mostrar ícono de confirmación brevemente
            okIcon.style.display = 'inline';
            setTimeout(() => okIcon.style.display = 'none', 2000);

            // Actualizar visualmente el badge en la fila
            const fila    = select.closest('tr');
            const tdEstado = fila.querySelector('td:nth-child(4)');
            const tdPuntos = fila.querySelector('td:nth-child(5)');

            const badges = {
                temprano: '<span class="badge-temprano"><i class="bi bi-check-circle me-1"></i>Temprano</span>',
                tarde:    '<span class="badge-tarde"><i class="bi bi-clock me-1"></i>Tarde</span>',
                falto:    '<span class="badge-falto"><i class="bi bi-x-circle me-1"></i>Faltó</span>'
            };
            const puntosMap = { temprano: '+5 pts', tarde: '+3 pts', falto: '' };

            tdEstado.innerHTML = badges[nuevoEstado] || nuevoEstado;
            tdPuntos.innerHTML = puntosMap[nuevoEstado]
                ? `<span class="puntos-ganados"><i class="bi bi-star-fill me-1" style="color:#fbbf24;font-size:.85rem;"></i>${puntosMap[nuevoEstado]}</span>`
                : '<span style="color:#cbd5e0;font-size:.85rem;">—</span>';
        } else {
            alert('Error al guardar. Intenta de nuevo.');
        }
    })
    .catch(() => { select.disabled = false; alert('Error de conexión.'); });
}

// Exportar tabla a CSV
function exportarCSV() {
    const tabla = document.getElementById('tablaAsistencias');
    let csv = [];

    // Encabezados
    const headers = [];
    tabla.querySelectorAll('thead th').forEach((th, i) => {
        if (i < 5) headers.push(th.innerText.trim()); // excluir columna "Editar"
    });
    csv.push(headers.join(','));

    // Filas
    tabla.querySelectorAll('tbody tr').forEach(tr => {
        const cols = tr.querySelectorAll('td');
        if (cols.length < 5) return;
        const fila = [];
        for (let i = 0; i < 5; i++) {
            fila.push('"' + cols[i].innerText.trim().replace(/"/g,'""') + '"');
        }
        csv.push(fila.join(','));
    });

    const blob = new Blob(['\uFEFF' + csv.join('\n')], {type:'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'asistencias_<?= urlencode($usuario['nombre']) ?>_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>
</body>
</html>