<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

require '../config/conexion.php';
include(__DIR__ . "/sidebar.php");

$uid = intval($_SESSION["user_id"]);

$stmt = $conn->prepare("
    SELECT u.id, u.nombre, u.apodo, u.email, u.avatar, u.rol, u.sede_id, 
           s.nombre as sede_nombre, p.total as puntos 
    FROM usuarios u
    LEFT JOIN puntos p ON u.id = p.usuario_id 
    LEFT JOIN sedes s ON u.sede_id = s.id 
    WHERE u.id = ?
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();
$stmt->close();

if (!$usuario) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($usuario['puntos']) || $usuario['puntos'] === null) {
    $stmt = $conn->prepare("INSERT INTO puntos (usuario_id, total) VALUES (?, 0)");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->close();
    $usuario['puntos'] = 0;
}

$puntos = intval($usuario['puntos'] ?? 0);

// Obtener nivel actual desde BD (trae TODOS los campos)
$stmt = $conn->prepare("
    SELECT * FROM niveles 
    WHERE puntos_minimos <= ? 
    ORDER BY puntos_minimos DESC 
    LIMIT 1
");
$stmt->bind_param("i", $puntos);
$stmt->execute();
$nivel_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$nivel_data) {
    $nivel_data = [
        'nivel'          => 1,
        'nombre'         => 'Principiante',
        'imagen'         => 'nivel1.jpg',
        'puntos_minimos' => 0,
        'puntos_maximos' => 99,
        'descripcion'    => 'Comenzando en el sistema'
    ];
}

$nivel        = $nivel_data['nivel'];
$nombre_nivel = $nivel_data['nombre'];
$imagen_nivel = $nivel_data['imagen'];

// Siguiente nivel
$stmt = $conn->prepare("
    SELECT puntos_minimos as siguiente 
    FROM niveles 
    WHERE puntos_minimos > ? 
    ORDER BY puntos_minimos ASC 
    LIMIT 1
");
$stmt->bind_param("i", $puntos);
$stmt->execute();
$siguiente_nivel = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($siguiente_nivel) {
    $puntos_siguiente_nivel = $siguiente_nivel['siguiente'] - $puntos;
    $puntos_para_siguiente  = $siguiente_nivel['siguiente'] - $nivel_data['puntos_minimos'];
    $progreso = round(($puntos - $nivel_data['puntos_minimos']) / $puntos_para_siguiente * 100);
} else {
    $puntos_siguiente_nivel = 0;
    $progreso = 100;
}

function getAvatarPersonal($usuario) {
    if (empty($usuario['avatar'])) return null;
    $base_path = $_SERVER['DOCUMENT_ROOT'] . '/asistencia-main/assets/img/';
    $base_url  = '/asistencia-main/assets/img/';
    if (file_exists($base_path . $usuario['avatar'])) return $base_url . $usuario['avatar'];
    return null;
}

function getImagenNivel($imagen_nivel, $nivel) {
    $base_path = $_SERVER['DOCUMENT_ROOT'] . '/asistencia-main/assets/img/';
    $base_url  = '/asistencia-main/assets/img/';
    if (!empty($imagen_nivel) && file_exists($base_path . $imagen_nivel)) {
        return $base_url . $imagen_nivel;
    }
    foreach (["nivel$nivel","nivel-$nivel","nivel_$nivel","Nivel$nivel"] as $nombre) {
        foreach (['jpg','jpeg','png','gif','webp'] as $ext) {
            if (file_exists($base_path . "$nombre.$ext")) return $base_url . "$nombre.$ext";
        }
    }
    return null;
}

// Avatar personal del usuario
$avatar_url = getAvatarPersonal($usuario)
           ?? getImagenNivel($imagen_nivel, $nivel)
           ?? '/asistencia-main/assets/img/default.png';

if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $avatar_url)) {
    $avatar_url = '/asistencia-main/assets/img/default.png';
}

// ✅ Imagen del nivel (separada del avatar)
$nivel_img_url = getImagenNivel($imagen_nivel, $nivel)
              ?? '/asistencia-main/assets/img/default.png';

// ✅ Datos completos del nivel para el modal
$nivel_modal = [
    'numero'      => $nivel_data['nivel'],
    'nombre'      => $nivel_data['nombre'],
    'descripcion' => $nivel_data['descripcion'] ?? '',
    'min'         => $nivel_data['puntos_minimos'],
    'max'         => $nivel_data['puntos_maximos'],
    'imagen'      => $nivel_img_url,
];
$nivel_modal_json = json_encode($nivel_modal, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

// Estadísticas
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_asistencias,
        SUM(CASE WHEN estado = 'temprano' THEN 1 ELSE 0 END) as temprano,
        SUM(CASE WHEN estado = 'tarde'    THEN 1 ELSE 0 END) as tarde,
        SUM(CASE WHEN estado = 'falto'    THEN 1 ELSE 0 END) as faltas
    FROM asistencias WHERE usuario_id = ?
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$stats['asistencias'] = $stmt->get_result()->fetch_assoc();
$stmt->close();

$check_table = $conn->query("SHOW TABLES LIKE 'historial_puntos'");
$stats['puntos_ganados']   = 0;
$stats['puntos_canjeados'] = 0;

if ($check_table && $check_table->num_rows > 0) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(puntos),0) as total FROM historial_puntos WHERE usuario_id=? AND tipo='ganado'");
    $stmt->bind_param("i", $uid); $stmt->execute();
    $stats['puntos_ganados'] = $stmt->get_result()->fetch_assoc()['total']; $stmt->close();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(puntos),0) as total FROM historial_puntos WHERE usuario_id=? AND tipo='canjeado'");
    $stmt->bind_param("i", $uid); $stmt->execute();
    $stats['puntos_canjeados'] = $stmt->get_result()->fetch_assoc()['total']; $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(r.costo),0) as total FROM canjes c JOIN recompensas r ON c.recompensa_id=r.id WHERE c.usuario_id=? AND c.estado='entregado'");
    $stmt->bind_param("i", $uid); $stmt->execute();
    $stats['puntos_canjeados'] = $stmt->get_result()->fetch_assoc()['total']; $stmt->close();
}

$stmt = $conn->prepare("SELECT fecha, hora, tipo_evento FROM marcaciones WHERE usuario_id=? ORDER BY fecha DESC, hora DESC LIMIT 5");
$stmt->bind_param("i", $uid); $stmt->execute();
$ultimas_marcaciones = $stmt->get_result(); $stmt->close();

$recompensas = $conn->query("SELECT * FROM recompensas WHERE activo=1 AND stock>0 ORDER BY costo ASC LIMIT 3");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Clínica Gamificación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f8f9fc; }
        .main-content { margin-left: 260px; padding: 30px; min-height: 100vh; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 20px; } }
        .page-title { font-weight: 700; color: #2d3748; position: relative; padding-bottom: .75rem; margin-bottom: 2rem; }
        .page-title:after { content: ''; position: absolute; bottom: 0; left: 0; width: 60px; height: 4px; background: linear-gradient(135deg,#667eea,#764ba2); border-radius: 2px; }
        .profile-card { background: linear-gradient(135deg,#667eea,#764ba2); border-radius: 20px; padding: 30px; color: white; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(102,126,234,.3); position: relative; overflow: hidden; }
        .profile-card::before { content: '🏆'; position: absolute; right: 20px; bottom: 20px; font-size: 100px; opacity: .1; transform: rotate(10deg); }
        .profile-avatar { width: 120px; height: 120px; border-radius: 50%; border: 4px solid white; box-shadow: 0 5px 20px rgba(0,0,0,.2); object-fit: cover; cursor: pointer; transition: transform .3s; }
        .profile-avatar:hover { transform: scale(1.05); }
        .profile-name { font-size: 2rem; font-weight: 700; margin-bottom: 5px; }
        .profile-role { display: inline-block; padding: 5px 15px; background: rgba(255,255,255,.2); border-radius: 20px; font-size: .9rem; margin-bottom: 15px; }
        .profile-sede { font-size: 1rem; opacity: .9; }
        .points-card { background: rgba(255,255,255,.15); backdrop-filter: blur(10px); border-radius: 15px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,.1); transition: all .3s; }
        .points-card:hover { transform: translateY(-5px); background: rgba(255,255,255,.25); }
        .points-title { font-size: .9rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; opacity: .9; }
        .points-value { font-size: 3rem; font-weight: 700; line-height: 1; }
        .level-container { background: white; border-radius: 20px; padding: 20px; margin: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,.05); cursor: pointer; transition: all .3s; }
        .level-container:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(102,126,234,.2); }
        .level-info { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .level-badge { background: linear-gradient(135deg,#667eea,#764ba2); color: white; padding: 5px 15px; border-radius: 20px; font-weight: 600; font-size: .9rem; }
        .level-number { font-size: 2rem; font-weight: 700; color: #667eea; }
        .level-name { font-size: 1.2rem; color: #4a5568; margin-left: 10px; }
        .progress { height: 15px; border-radius: 10px; background-color: #e2e8f0; margin: 10px 0; }
        .progress-bar { background: linear-gradient(135deg,#667eea,#764ba2); border-radius: 10px; transition: width .5s; }
        .next-level { text-align: right; color: #718096; font-size: .9rem; }
        .stat-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,.05); transition: all .3s; height: 100%; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(102,126,234,.15); }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; background: linear-gradient(135deg,#667eea,#764ba2); display: flex; align-items: center; justify-content: center; margin-bottom: 15px; }
        .stat-icon i { font-size: 1.5rem; color: white; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: #2d3748; margin-bottom: 5px; }
        .stat-label { color: #718096; font-size: .9rem; margin-bottom: 10px; }
        .stat-detail { color: #a0aec0; font-size: .8rem; border-top: 1px solid #e2e8f0; padding-top: 10px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,.05); }
        .card-header { background: white; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.5rem; }
        .table th { border-top: none; color: #718096; font-weight: 600; font-size: .85rem; text-transform: uppercase; letter-spacing: .5px; }
        .badge-entrada { background: #06d6a0; color: white; padding: 5px 12px; border-radius: 20px; font-size: .8rem; font-weight: 500; }
        .badge-salida  { background: #ef476f; color: white; padding: 5px 12px; border-radius: 20px; font-size: .8rem; font-weight: 500; }
        .reward-card { text-align: center; padding: 15px; border-bottom: 1px solid #e2e8f0; }
        .reward-card:last-child { border-bottom: none; }
        .reward-image { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin: 0 auto 10px; border: 3px solid #667eea; padding: 3px; background: white; cursor: pointer; }
        .reward-name { font-weight: 600; color: #2d3748; margin-bottom: 5px; }
        .reward-points { color: #667eea; font-weight: 700; margin-bottom: 10px; }
        .reward-points i { color: #fbbf24; margin-right: 5px; }
        .welcome-message { background: white; border-radius: 12px; padding: 20px; margin-bottom: 25px; border-left: 4px solid #667eea; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
        /* Modales */
        .modal-content { border-radius: 20px; border: none; }
        .modal-header { background: linear-gradient(135deg,#667eea,#764ba2); color: white; border-radius: 20px 20px 0 0; border: none; }
        .modal-header .btn-close { filter: brightness(0) invert(1); }
        .modal-body { padding: 25px; }
        .modal-footer { border-top: 1px solid #e2e8f0; padding: 1rem; }
        /* Modal de nivel */
        .nivel-modal-img {
            width: 160px; height: 160px; border-radius: 50%; object-fit: cover;
            border: 5px solid #667eea; box-shadow: 0 8px 25px rgba(102,126,234,.35);
            margin: 0 auto 20px; display: block;
        }
        .nivel-numero-badge {
            background: linear-gradient(135deg,#667eea,#764ba2); color: white;
            font-size: 1rem; font-weight: 700; padding: 6px 20px;
            border-radius: 30px; display: inline-block; margin-bottom: 10px;
        }
        .nivel-nombre { font-size: 1.6rem; font-weight: 700; color: #2d3748; margin-bottom: 8px; }
        .nivel-descripcion { color: #718096; font-size: 1rem; margin-bottom: 18px; }
        .nivel-puntos-rango {
            background: #f7f9fc; border-radius: 12px; padding: 14px 20px;
            display: inline-flex; gap: 30px; border: 1px solid #e2e8f0;
        }
        .rango-item { text-align: center; }
        .rango-valor { font-size: 1.3rem; font-weight: 700; color: #667eea; }
        .rango-label { font-size: .75rem; color: #a0aec0; text-transform: uppercase; letter-spacing: .5px; }
        .nivel-progreso-label { font-size: .9rem; color: #718096; margin-top: 15px; }
    </style>
</head>
<body>

<?php include(__DIR__ . "/sidebar.php"); ?>

<main class="main-content">
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="bi bi-house-door me-2" style="color:#667eea;"></i>Dashboard
        </h1>

        <div class="welcome-message">
            <div class="d-flex align-items-center">
                <i class="bi bi-sun fs-1 me-3" style="color:#fbbf24;"></i>
                <div>
                    <h5 class="mb-1">¡Bienvenido de nuevo, <?= htmlspecialchars($usuario['nombre']) ?>!</h5>
                    <p class="text-muted mb-0">
                        <?php $hora = date('H'); echo $hora<12 ? "Buenos días. " : ($hora<18 ? "Buenas tardes. " : "Buenas noches. "); ?>
                        Tienes <?= number_format($puntos) ?> puntos acumulados.
                    </p>
                </div>
            </div>
        </div>

        <div class="profile-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <img src="<?= $avatar_url ?>" alt="Avatar" class="profile-avatar me-4"
                             onclick="verImagenAmpliada('<?= $avatar_url ?>', '<?= htmlspecialchars($usuario['nombre']) ?>')"
                             title="Ver imagen ampliada"
                             onerror="this.src='/asistencia-main/assets/img/default.png'; this.onerror=null;">
                        <div>
                            <h2 class="profile-name"><?= htmlspecialchars($usuario['nombre']) ?></h2>
                            <span class="profile-role">
                                <i class="bi bi-shield me-1"></i>
                                <?= $usuario['rol'] === 'admin' ? 'Administrador' : 'Usuario' ?>
                            </span>
                            <p class="profile-sede">
                                <i class="bi bi-geo-alt me-1"></i>
                                <?= htmlspecialchars($usuario['sede_nombre'] ?? 'Sin sede asignada') ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="points-card">
                        <div class="points-title">TUS PUNTOS</div>
                        <div class="points-value"><?= number_format($puntos) ?></div>
                        <div class="points-label mt-2">puntos acumulados</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ✅ Al hacer clic abre modal con imagen + datos del nivel desde BD -->
        <div class="level-container" onclick='verNivel(<?= $nivel_modal_json ?>)'>
            <div class="level-info">
                <div>
                    <span class="level-badge">
                        <i class="bi bi-trophy-fill me-1"></i>NIVEL <?= $nivel ?>
                    </span>
                    <span class="level-name"><?= htmlspecialchars($nombre_nivel) ?></span>
                </div>
                <div class="level-number"><?= number_format($puntos) ?> pts</div>
            </div>
            <div class="progress">
                <div class="progress-bar" style="width:<?= $progreso ?>%;"></div>
            </div>
            <div class="next-level">
                <?php if ($puntos_siguiente_nivel > 0): ?>
                    <i class="bi bi-arrow-up-circle me-1"></i>
                    <?= $puntos_siguiente_nivel ?> puntos para el siguiente nivel
                <?php else: ?>
                    <i class="bi bi-star-fill me-1" style="color:#fbbf24;"></i>¡Nivel máximo alcanzado!
                <?php endif; ?>
                <span class="ms-2 text-primary"><i class="bi bi-zoom-in"></i> Ver detalles del nivel</span>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                    <div class="stat-value"><?= intval($stats['asistencias']['total_asistencias'] ?? 0) ?></div>
                    <div class="stat-label">Total Asistencias</div>
                    <div class="stat-detail">
                        <span class="text-success">✓ <?= intval($stats['asistencias']['temprano'] ?? 0) ?> temprano</span><br>
                        <span class="text-warning">⏰ <?= intval($stats['asistencias']['tarde'] ?? 0) ?> tarde</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#06d6a0,#05b588)"><i class="bi bi-star-fill"></i></div>
                    <div class="stat-value"><?= number_format($stats['puntos_ganados'] ?? 0) ?></div>
                    <div class="stat-label">Puntos Ganados</div>
                    <div class="stat-detail">Desde que empezaste</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#ef476f,#d64161)"><i class="bi bi-gift-fill"></i></div>
                    <div class="stat-value"><?= number_format($stats['puntos_canjeados'] ?? 0) ?></div>
                    <div class="stat-label">Puntos Canjeados</div>
                    <div class="stat-detail">En recompensas</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#ffd166,#edb83d)"><i class="bi bi-arrow-repeat"></i></div>
                    <div class="stat-value"><?= number_format($puntos - ($stats['puntos_canjeados'] ?? 0)) ?></div>
                    <div class="stat-label">Puntos Disponibles</div>
                    <div class="stat-detail">Para canjear</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2" style="color:#667eea;"></i>Últimas Marcaciones</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Fecha</th><th>Hora</th><th>Tipo</th></tr></thead>
                            <tbody>
                                <?php if ($ultimas_marcaciones && $ultimas_marcaciones->num_rows > 0): ?>
                                    <?php while ($m = $ultimas_marcaciones->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                                            <td><?= date('H:i',   strtotime($m['hora']))  ?></td>
                                            <td><span class="badge-<?= $m['tipo_evento'] ?>"><?= strtoupper($m['tipo_evento']) ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-2"></i>No hay marcaciones registradas</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-gift me-2" style="color:#667eea;"></i>Recompensas Destacadas</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($recompensas && $recompensas->num_rows > 0): ?>
                            <?php while ($r = $recompensas->fetch_assoc()): ?>
                                <?php 
                                    // Para cada recompensa, construir la ruta correcta de la imagen
                                    if (!empty($r['imagen'])) {
                                        // Si la imagen ya tiene la carpeta 'uploads/', la quitamos
                                        $nombre_imagen = str_replace('uploads/', '', $r['imagen']);
                                        $ri = '/asistencia-main/assets/img/recompensas/' . $nombre_imagen;
                                    } else {
                                        $ri = '/asistencia-main/assets/img/default.png';
                                    }
                                    ?>
                            
                                <div class="reward-card">
                                    <img src="<?= $ri ?>" alt="<?= htmlspecialchars($r['nombre']) ?>" class="reward-image"
                                         onclick="verImagenAmpliada('<?= $ri ?>','<?= htmlspecialchars($r['nombre']) ?>')"
                                         onerror="this.src='/asistencia-main/assets/img/default.png'; this.onerror=null;">
                                    <h6 class="reward-name"><?= htmlspecialchars($r['nombre']) ?></h6>
                                    <div class="reward-points"><i class="bi bi-star-fill"></i><?= number_format($r['costo']) ?> pts</div>
                                    <a href="../tienda/index.php" class="btn btn-sm btn-outline-primary">Canjear</a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-emoji-frown fs-1 text-muted d-block mb-2"></i>
                                <p class="text-muted mb-0">No hay recompensas disponibles</p>
                            </div>
                        <?php endif; ?>
                        <div class="text-center p-3 border-top">
                            <a href="../tienda/index.php" class="text-decoration-none">Ver todas las recompensas <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- ✅ MODAL DEL NIVEL con imagen + datos completos de la BD -->
<div class="modal fade" id="nivelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-trophy-fill me-2"></i>
                    <span id="nivelModalTitulo">Detalle del Nivel</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <!-- Imagen del nivel desde BD -->
                <img id="nivelModalImg" src="" alt="Imagen del nivel" class="nivel-modal-img"
                     onerror="this.src='/asistencia-main/assets/img/default.png'; this.onerror=null;">

                <div id="nivelModalNumero" class="nivel-numero-badge mb-2"></div>
                <div id="nivelModalNombre" class="nivel-nombre"></div>
                <div id="nivelModalDesc" class="nivel-descripcion"></div>

                <!-- Rango de puntos -->
                <div class="nivel-puntos-rango">
                    <div class="rango-item">
                        <div class="rango-valor" id="nivelModalMin"></div>
                        <div class="rango-label">Puntos mín.</div>
                    </div>
                    <div class="rango-item">
                        <div class="rango-valor" id="nivelModalMax"></div>
                        <div class="rango-label">Puntos máx.</div>
                    </div>
                    <div class="rango-item">
                        <div class="rango-valor" style="color:#2d3748"><?= number_format($puntos) ?></div>
                        <div class="rango-label">Tus puntos</div>
                    </div>
                </div>

                <p class="nivel-progreso-label" id="nivelModalProgreso"></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal genérico para imágenes (avatar, recompensas) -->
<div class="modal fade" id="imagenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-image me-2"></i><span id="modalTitulo">Imagen</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" alt="Imagen ampliada" id="modalImagen"
                     style="max-width:100%;max-height:70vh;border-radius:15px;box-shadow:0 10px 30px rgba(0,0,0,.2)">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ✅ Abre el modal del nivel con todos los datos de la BD
function verNivel(data) {
    document.getElementById('nivelModalTitulo').textContent = 'Nivel ' + data.numero + ': ' + data.nombre;
    document.getElementById('nivelModalImg').src            = data.imagen;
    document.getElementById('nivelModalNumero').textContent = 'NIVEL ' + data.numero;
    document.getElementById('nivelModalNombre').textContent = data.nombre;
    document.getElementById('nivelModalDesc').textContent   = data.descripcion || 'Sin descripción';
    document.getElementById('nivelModalMin').textContent    = Number(data.min).toLocaleString();
    document.getElementById('nivelModalMax').textContent    = data.max !== null ? Number(data.max).toLocaleString() : '∞';

    const missingPts = (data.max !== null) ? (data.max - <?= $puntos ?> + 1) : 0;
    document.getElementById('nivelModalProgreso').textContent =
        missingPts > 0
            ? '⭐ Te faltan ' + missingPts.toLocaleString() + ' puntos para el siguiente nivel'
            : '🏆 ¡Nivel máximo alcanzado!';

    new bootstrap.Modal(document.getElementById('nivelModal')).show();
}

// Modal genérico para imágenes
function verImagenAmpliada(url, titulo) {
    document.getElementById('modalImagen').src         = url;
    document.getElementById('modalTitulo').textContent = titulo;
    new bootstrap.Modal(document.getElementById('imagenModal')).show();
}
</script>
</body>
</html>