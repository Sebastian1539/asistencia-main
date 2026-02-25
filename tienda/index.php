<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$uid = intval($_SESSION["user_id"]);

/* ===============================
   OBTENER PUNTOS (Prepared)
================================= */
$stmt = $conn->prepare("SELECT total FROM puntos WHERE usuario_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$puntos = $row['total'] ?? 0;
$stmt->close();

/* ===============================
   OBTENER RECOMPENSAS ACTIVAS
================================= */
$stmt = $conn->prepare("SELECT * FROM recompensas WHERE activo = 1 AND stock > 0 ORDER BY costo ASC");
$stmt->execute();
$recompensas = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tienda de Recompensas</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
* { font-family: 'Inter', sans-serif; }

body {
    background: linear-gradient(135deg,#f4f6f9 0%, #eef2f7 100%);
}

/* HEADER */
.store-header {
    background: white;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

.points-card {
    background: linear-gradient(135deg,#667eea,#764ba2);
    color: white;
    border-radius: 20px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 15px 40px rgba(102,126,234,0.4);
}

.points-card h2 {
    font-weight: 700;
    font-size: 2.5rem;
    margin: 0;
}

.progress-custom {
    height: 10px;
    border-radius: 20px;
}

/* GRID */
.rewards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 25px;
}

/* CARD */
.reward-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.reward-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(0,0,0,0.1);
}

.reward-img {
    height: 180px;
    object-fit: cover;
    width: 100%;
}

.reward-body {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.reward-title {
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 8px;
}

.reward-desc {
    font-size: 0.9rem;
    color: #6b7280;
    flex-grow: 1;
}

.reward-footer {
    margin-top: 15px;
}

.price-badge {
    background: #f3f4f6;
    padding: 8px 14px;
    border-radius: 20px;
    font-weight: 600;
}

.stock-badge {
    font-size: 0.75rem;
    padding: 5px 10px;
    border-radius: 20px;
}

/* BUTTON */
.btn-redeem {
    border-radius: 12px;
    padding: 10px;
    font-weight: 600;
    transition: 0.3s;
}

.btn-redeem:not(:disabled):hover {
    transform: translateY(-3px);
}

.btn-disabled {
    background: #d1d5db !important;
    cursor: not-allowed;
}
</style>
</head>

<body>

<?php include("../dashboard/sidebar.php"); ?>

<main class="main-content">
<div class="container-fluid">

<!-- HEADER -->
<div class="store-header row align-items-center">
    <div class="col-md-8">
        <h1 class="fw-bold">
            <i class="bi bi-cart-fill text-primary me-2"></i>
            Tienda de Recompensas
        </h1>
        <p class="text-muted">
            Canjea tus puntos acumulados por beneficios exclusivos.
        </p>
    </div>

    <div class="col-md-4">
        <div class="points-card">
            <small>Tus puntos disponibles</small>
            <h2><?= $puntos ?></h2>
            <div class="progress mt-3 progress-custom">
                <div class="progress-bar bg-warning"
                     style="width: <?= min($puntos,100) ?>%"></div>
            </div>
        </div>
    </div>
</div>

<!-- GRID DE RECOMPENSAS -->
<div class="rewards-grid">

<?php if ($recompensas->num_rows > 0): ?>
<?php while ($r = $recompensas->fetch_assoc()): 
    $puedeCanjear = $puntos >= $r['costo'];
?>
    <div class="reward-card">

        <?php if (!empty($r['imagen'])): ?>
            <img src="../assets/img/<?= htmlspecialchars($r['imagen']) ?>" 
                 class="reward-img"
                 alt="Recompensa">
        <?php else: ?>
            <img src="../assets/img/default.png" 
                 class="reward-img"
                 alt="Recompensa">
        <?php endif; ?>

        <div class="reward-body">
            <div class="reward-title">
                <?= htmlspecialchars($r['nombre']) ?>
            </div>

            <div class="reward-desc">
                <?= htmlspecialchars($r['descripcion']) ?>
            </div>

            <div class="reward-footer">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="price-badge">
                        <i class="bi bi-star-fill text-warning"></i>
                        <?= $r['costo'] ?> pts
                    </span>

                    <span class="stock-badge bg-success text-white">
                        Stock: <?= $r['stock'] ?>
                    </span>
                </div>

                <form method="POST" action="canjear.php">
                    <input type="hidden" name="recompensa_id" value="<?= $r['id'] ?>">
                    <button type="submit"
                            class="btn btn-primary w-100 btn-redeem <?= !$puedeCanjear ? 'btn-disabled' : '' ?>"
                            <?= !$puedeCanjear ? 'disabled' : '' ?>>
                        <?php if ($puedeCanjear): ?>
                            <i class="bi bi-bag-check me-2"></i>Canjear ahora
                        <?php else: ?>
                            <i class="bi bi-lock-fill me-2"></i>Puntos insuficientes
                        <?php endif; ?>
                    </button>
                </form>

            </div>
        </div>
    </div>

<?php endwhile; ?>
<?php else: ?>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    No hay recompensas disponibles actualmente.
</div>

<?php endif; ?>

</div>

</div>
</main>

</body>
</html>