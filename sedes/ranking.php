<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$uid = intval($_SESSION["user_id"]);
$sede_id = isset($_GET['sede']) ? intval($_GET['sede']) : 0;

if ($sede_id <= 0) {
    header("Location: ../dashboard/index.php");
    exit();
}

/* ============================
   OBTENER NOMBRE SEDE
============================ */
$stmt = $conn->prepare("SELECT nombre FROM sedes WHERE id = ? AND activo = 1");
$stmt->bind_param("i", $sede_id);
$stmt->execute();
$res = $stmt->get_result();
$sede = $res->fetch_assoc();
$stmt->close();

if (!$sede) {
    die("Sede no encontrada");
}

/* ============================
   RANKING CALCULADO
============================ */
$stmt = $conn->prepare("
SELECT 
    u.id,
    u.nombre,
    u.apodo,
    COALESCE(SUM(
        CASE 
            WHEN a.estado = 'temprano' THEN 5
            WHEN a.estado = 'tarde' THEN 3
            ELSE 0
        END
    ),0) AS puntos
FROM usuarios u
LEFT JOIN asistencias a ON u.id = a.usuario_id
WHERE u.sede_id = ?
GROUP BY u.id
ORDER BY puntos DESC
");
$stmt->bind_param("i", $sede_id);
$stmt->execute();
$result = $stmt->get_result();

$ranking = [];
while ($row = $result->fetch_assoc()) {
    $ranking[] = $row;
}
$stmt->close();

/* ============================
   POSICIÓN DEL USUARIO
============================ */
$mi_pos = null;
foreach ($ranking as $i => $r) {
    if ($r['id'] == $uid) {
        $mi_pos = $i + 1;
        break;
    }
}

$totalUsuarios = count($ranking);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ranking - <?= htmlspecialchars($sede['nombre']) ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
* { font-family: 'Inter', sans-serif; }

body {
    background: linear-gradient(135deg,#f3f6fb 0%, #e9eef5 100%);
}

/* HEADER */
.rank-header {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg,#667eea,#764ba2);
    color: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 15px 30px rgba(102,126,234,0.3);
}

.stat-card h2 {
    margin: 0;
    font-weight: 800;
}

/* PODIO */
.podium {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    gap: 30px;
    margin: 50px 0;
}

.podium-card {
    width: 160px;
    border-radius: 20px 20px 10px 10px;
    text-align: center;
    color: white;
    padding: 20px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.1);
    transition: 0.3s;
}

.podium-card:hover {
    transform: translateY(-10px);
}

.first { background: linear-gradient(135deg,#fbbf24,#f59e0b); height: 220px; }
.second { background: linear-gradient(135deg,#9ca3af,#6b7280); height: 180px; }
.third { background: linear-gradient(135deg,#f97316,#ea580c); height: 160px; }

.podium-name {
    font-weight: 600;
    margin-top: 10px;
}

.podium-points {
    font-size: 1.2rem;
    font-weight: 700;
}

/* TABLA */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.05);
}

.table thead {
    background: #f8fafc;
}

.table th {
    font-weight: 600;
    color: #374151;
}

.highlight-me {
    background: #e0f2fe !important;
    font-weight: 600;
}

.badge-me {
    background: #2563eb;
}
</style>
</head>

<body>

<?php include("../dashboard/sidebar.php"); ?>

<main class="main-content">
<div class="container-fluid">

<!-- HEADER -->
<div class="rank-header row align-items-center">
    <div class="col-md-8">
        <h1 class="fw-bold">
            <i class="bi bi-trophy-fill text-warning me-2"></i>
            Ranking – <?= htmlspecialchars($sede['nombre']) ?>
        </h1>
        <p class="text-muted">
            Clasificación general basada en puntualidad y asistencia.
        </p>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <small>Tu posición</small>
            <h2><?= $mi_pos ? "#$mi_pos" : "-" ?></h2>
            <small>de <?= $totalUsuarios ?> usuarios</small>
        </div>
    </div>
</div>

<!-- PODIO -->
<div class="podium">
<?php if (isset($ranking[1])): ?>
    <div class="podium-card second">
        <h3>🥈</h3>
        <div class="podium-name"><?= htmlspecialchars($ranking[1]['apodo'] ?: $ranking[1]['nombre']) ?></div>
        <div class="podium-points"><?= $ranking[1]['puntos'] ?> pts</div>
    </div>
<?php endif; ?>

<?php if (isset($ranking[0])): ?>
    <div class="podium-card first">
        <h3>🥇</h3>
        <div class="podium-name"><?= htmlspecialchars($ranking[0]['apodo'] ?: $ranking[0]['nombre']) ?></div>
        <div class="podium-points"><?= $ranking[0]['puntos'] ?> pts</div>
    </div>
<?php endif; ?>

<?php if (isset($ranking[2])): ?>
    <div class="podium-card third">
        <h3>🥉</h3>
        <div class="podium-name"><?= htmlspecialchars($ranking[2]['apodo'] ?: $ranking[2]['nombre']) ?></div>
        <div class="podium-points"><?= $ranking[2]['puntos'] ?> pts</div>
    </div>
<?php endif; ?>
</div>

<!-- TABLA -->
<div class="table-container">
<h4 class="fw-bold mb-4">Clasificación completa</h4>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
<tr>
    <th>#</th>
    <th>Nombre</th>
    <th>Puntos</th>
</tr>
</thead>
<tbody>

<?php foreach ($ranking as $i => $r): ?>
<tr class="<?= ($r['id'] == $uid) ? 'highlight-me' : '' ?>">
    <td>
        <?= $i+1 ?>
        <?php if ($r['id'] == $uid): ?>
            <span class="badge badge-me ms-2">Tú</span>
        <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($r['apodo'] ?: $r['nombre']) ?></td>
    <td><strong><?= $r['puntos'] ?></strong></td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

</div>

</div>
</main>

</body>
</html>