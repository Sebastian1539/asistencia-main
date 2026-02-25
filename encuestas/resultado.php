<?php
include("../config/conexion.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$uid = $_SESSION["user_id"];

$stmt = $conn->prepare("SELECT dominante, influyente, estable, cumplidor FROM resultados_disc WHERE usuario_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
$data = $res->fetch_assoc();
$stmt->close();

if (!$data) {
    $D = $I = $S = $C = 0;
} else {
    $total = array_sum($data);
    if ($total > 0) {
        $D = round(($data['dominante'] ?? 0) * 100 / $total);
        $I = round(($data['influyente'] ?? 0) * 100 / $total);
        $S = round(($data['estable'] ?? 0) * 100 / $total);
        $C = round(($data['cumplidor'] ?? 0) * 100 / $total);
    } else {
        $D = $I = $S = $C = 0;
    }
}

// Perfil dominante
$perfiles = [
    'Dominante' => $D,
    'Influyente' => $I,
    'Estable' => $S,
    'Cumplidor' => $C
];

arsort($perfiles);
$nombre_principal = array_key_first($perfiles);
$perfil_principal = $perfiles[$nombre_principal];
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Resultado DISC</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.page-header {
    margin-bottom: 30px;
}

.disc-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.stat-box {
    border-radius: 18px;
    padding: 25px;
    text-align: center;
    color: white;
    font-weight: 600;
    transition: transform .3s ease;
}

.stat-box:hover {
    transform: translateY(-6px);
}

.bg-dominante { background: linear-gradient(135deg,#ef4444,#dc2626); }
.bg-influyente { background: linear-gradient(135deg,#f59e0b,#d97706); }
.bg-estable { background: linear-gradient(135deg,#10b981,#059669); }
.bg-cumplidor { background: linear-gradient(135deg,#3b82f6,#2563eb); }

.chart-container {
    max-width: 420px;
    margin: 0 auto;
}

.interpretacion-box {
    background: #f8fafc;
    border-radius: 18px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}
</style>
</head>

<body>

<?php include("../dashboard/sidebar.php"); ?>

<main class="main-content">
<div class="container-fluid">

<div class="page-header">
    <h1 class="fw-bold">
        <i class="bi bi-bar-chart-fill text-primary me-2"></i>
        Tu Perfil DISC
    </h1>
    <p class="text-muted">
        Perfil predominante: <strong><?= $nombre_principal ?></strong> (<?= $perfil_principal ?>%)
    </p>
</div>

<div class="row g-4">

    <!-- COLUMNA IZQUIERDA -->
    <div class="col-lg-6">
        <div class="disc-card text-center">
            <h5 class="mb-4 fw-semibold">Distribución de tu Perfil</h5>
            <div class="chart-container">
                <canvas id="discChart"></canvas>
            </div>
        </div>
    </div>

    <!-- COLUMNA DERECHA -->
    <div class="col-lg-6">

        <!-- CUADROS -->
        <div class="row g-3 mb-4">

            <div class="col-6">
                <div class="stat-box bg-dominante">
                    <h6>Dominante</h6>
                    <h3><?= $D ?>%</h3>
                </div>
            </div>

            <div class="col-6">
                <div class="stat-box bg-influyente">
                    <h6>Influyente</h6>
                    <h3><?= $I ?>%</h3>
                </div>
            </div>

            <div class="col-6">
                <div class="stat-box bg-estable">
                    <h6>Estable</h6>
                    <h3><?= $S ?>%</h3>
                </div>
            </div>

            <div class="col-6">
                <div class="stat-box bg-cumplidor">
                    <h6>Cumplidor</h6>
                    <h3><?= $C ?>%</h3>
                </div>
            </div>

        </div>

        <!-- INTERPRETACIÓN -->
        <div class="interpretacion-box">
            <h5 class="fw-bold mb-3">Interpretación</h5>

            <div class="mb-3">
                <strong class="text-danger">Dominante:</strong>
                <p class="mb-2">
                Se enfoca en resultados, liderazgo y acción rápida.
                Toma decisiones firmes y enfrenta desafíos con determinación.
                </p>
            </div>

            <div class="mb-3">
                <strong class="text-warning">Influyente:</strong>
                <p class="mb-2">
                Comunicativo y persuasivo.
                Motiva e inspira a otros con facilidad.
                </p>
            </div>

            <div class="mb-3">
                <strong class="text-success">Estable:</strong>
                <p class="mb-2">
                Colaborador y paciente.
                Busca armonía y estabilidad en su entorno.
                </p>
            </div>

            <div>
                <strong class="text-primary">Cumplidor:</strong>
                <p class="mb-0">
                Analítico y detallista.
                Valora precisión, normas y calidad.
                </p>
            </div>
        </div>

    </div>

</div>

</div>
</main>

<script>
new Chart(document.getElementById('discChart'), {
    type: 'doughnut',
    data: {
        labels: ['Dominante','Influyente','Estable','Cumplidor'],
        datasets: [{
            data: [<?= $D ?>, <?= $I ?>, <?= $S ?>, <?= $C ?>],
            backgroundColor: [
                '#ef4444',
                '#f59e0b',
                '#10b981',
                '#3b82f6'
            ],
            borderWidth: 0
        }]
    },
    options: {
        cutout: '65%',
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

</body>
</html>