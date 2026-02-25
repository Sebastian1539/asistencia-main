<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ FIX: El cancelar debe estar ANTES de cualquier include o salida HTML
// Si se ejecuta después del HTML ya enviado, header() falla con "headers already sent"
if(isset($_POST['cancelar'])){
    unset($_SESSION['preview_import']);
    header("Location: importar.php");
    exit();
}

include("../dashboard/sidebar.php");
require '../vendor/autoload.php';
require '../config/conexion.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$mensaje = "";
$error = "";

/* =========================
   PREVISUALIZAR ARCHIVO
========================= */
if(isset($_POST['preview']) && isset($_FILES['archivo'])){

    $archivo = $_FILES['archivo']['tmp_name'];
    $extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    
    if(!in_array($extension, ['xlsx', 'xls', 'csv'])){
        $error = "Formato de archivo no válido. Use .xlsx, .xls o .csv";
    } else {
        try{
            $doc = IOFactory::load($archivo);
            $rows = $doc->getActiveSheet()->toArray();
            $preview = [];

            foreach($rows as $i => $fila){
                if($i == 0) continue;

                if(empty($fila[0]) || !is_numeric($fila[0])){
                    continue;
                }

                $codigo = trim($fila[0] ?? '');
                $nombre = trim($fila[1] ?? '');
                $fecha  = trim($fila[2] ?? '');
                $hora   = trim($fila[3] ?? '');
                $tipo   = strtoupper(trim($fila[4] ?? ''));
                $disp   = trim($fila[5] ?? '');

                if($codigo=='' || $fecha=='' || $hora==''){
                    continue;
                }

                if(!in_array(strtolower($tipo), ['entrada', 'salida', 'e', 's'])){
                    $tipo = 'entrada';
                } else {
                    if(strtolower($tipo) == 'e') $tipo = 'entrada';
                    if(strtolower($tipo) == 's') $tipo = 'salida';
                    $tipo = strtolower($tipo);
                }

                try {
                    $fecha = date('Y-m-d', strtotime($fecha));
                    $hora  = date('H:i:s', strtotime($hora));
                } catch(Exception $e) {
                    continue;
                }

                $stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE codigo_biometrico = ?");
                $stmt->bind_param("i", $codigo);
                $stmt->execute();
                $res = $stmt->get_result();
                $usuario = $res->fetch_assoc();
                $valido = $res->num_rows > 0;

                $preview[] = [
                    "codigo"     => $codigo,
                    "nombre"     => $valido ? $usuario['nombre'] : $nombre,
                    "fecha"      => $fecha,
                    "hora"       => $hora,
                    "tipo"       => $tipo,
                    "disp"       => $disp,
                    "valido"     => $valido,
                    "usuario_id" => $valido ? $usuario['id'] : null
                ];
            }

            $_SESSION['preview_import'] = $preview;
            $mensaje = "Archivo cargado correctamente. Se encontraron " . count($preview) . " registros válidos para previsualizar.";

        } catch(Exception $e){
            $error = "Error leyendo archivo: " . $e->getMessage();
        }
    }
}

/* =========================
   CONFIRMAR IMPORTACIÓN
========================= */
if(isset($_POST['confirmar']) && isset($_SESSION['preview_import'])){

    $insertados = 0;
    $duplicados = 0;
    $errores    = 0;

    foreach($_SESSION['preview_import'] as $row){

        if(!$row['valido'] || !$row['usuario_id']) continue;

        $check = $conn->prepare("SELECT id FROM marcaciones WHERE usuario_id = ? AND fecha = ? AND hora = ? AND tipo_evento = ?");
        $check->bind_param("isss", $row['usuario_id'], $row['fecha'], $row['hora'], $row['tipo']);
        $check->execute();
        $result = $check->get_result();

        if($result->num_rows == 0){
            $insert = $conn->prepare("
                INSERT INTO marcaciones (usuario_id, fecha, hora, tipo_evento, dispositivo)
                VALUES (?, ?, ?, ?, ?)
            ");

            if($insert){
                $insert->bind_param("issss", $row['usuario_id'], $row['fecha'], $row['hora'], $row['tipo'], $row['disp']);
                if($insert->execute()){
                    $insertados++;
                } else {
                    $errores++;
                }
                $insert->close();
            } else {
                $errores++;
            }
        } else {
            $duplicados++;
        }
        $check->close();
    }

    unset($_SESSION['preview_import']);
    $mensaje = "✅ Importación completada: $insertados registros insertados, $duplicados duplicados omitidos, $errores errores.";
}

// Estadísticas
$stats = [];
$statsQuery = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN fecha = CURDATE() THEN 1 ELSE 0 END) as hoy,
        SUM(CASE WHEN tipo_evento = 'entrada' THEN 1 ELSE 0 END) as entradas,
        SUM(CASE WHEN tipo_evento = 'salida' THEN 1 ELSE 0 END) as salidas
    FROM marcaciones
");
if($statsQuery) {
    $stats = $statsQuery->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Marcaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/asistencia/assets/css/layout.css">
    
    <style>
        .main-content {
            margin-left: 260px;
            padding: 30px;
            background-color: #f8f9fc;
            min-height: 100vh;
        }
        .card-header { font-weight: 600; }
        .table th { background-color: #f8f9fa; font-weight: 600; font-size: 0.9rem; }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .stats-number { font-size: 2rem; font-weight: 700; }
        .file-info {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="container-fluid px-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">
                <i class="bi bi-file-excel me-2" style="color: #28a745;"></i>
                Importar Marcaciones Biométricas
            </h3>
            <a href="../marcaciones/lista.php" class="btn btn-outline-primary">
                <i class="bi bi-list me-2"></i>Ver Marcaciones
            </a>
        </div>

        <?php if(!empty($stats)): ?>
        <div class="row stats-card mx-0 mb-4">
            <div class="col-md-3">
                <div class="text-center">
                    <i class="bi bi-clock-history fs-1"></i>
                    <div class="stats-number"><?= $stats['total'] ?? 0 ?></div>
                    <div>Total Marcaciones</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <i class="bi bi-calendar-day fs-1"></i>
                    <div class="stats-number"><?= $stats['hoy'] ?? 0 ?></div>
                    <div>Marcaciones Hoy</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <i class="bi bi-box-arrow-in-right fs-1"></i>
                    <div class="stats-number"><?= $stats['entradas'] ?? 0 ?></div>
                    <div>Entradas</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <i class="bi bi-box-arrow-left fs-1"></i>
                    <div class="stats-number"><?= $stats['salidas'] ?? 0 ?></div>
                    <div>Salidas</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($mensaje != ""): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($error != ""): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="file-info">
            <h6 class="fw-bold"><i class="bi bi-info-circle me-2"></i>Formato esperado del archivo:</h6>
            <p class="mb-0 small">
                Columnas en orden: 
                <span class="badge bg-secondary">Código</span> 
                <span class="badge bg-secondary">Nombre</span> 
                <span class="badge bg-secondary">Fecha</span> 
                <span class="badge bg-secondary">Hora</span> 
                <span class="badge bg-secondary">Tipo (entrada/salida)</span> 
                <span class="badge bg-secondary">Dispositivo</span>
            </p>
            <p class="mt-2 mb-0 small text-muted">
                <i class="bi bi-lightbulb me-1"></i>
                Acepta .xlsx, .xls y .csv. La primera fila debe ser el encabezado.
            </p>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">
                                <i class="bi bi-file-earmark-arrow-up me-2"></i>
                                Seleccionar archivo del reloj biométrico
                            </label>
                            <input type="file" name="archivo" class="form-control" accept=".xlsx,.xls,.csv" required>
                            <small class="text-muted">Tamaño máximo recomendado: 5MB</small>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="preview" class="btn btn-primary w-100">
                                <i class="bi bi-eye me-2"></i>Previsualizar Archivo
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if(isset($_SESSION['preview_import']) && count($_SESSION['preview_import']) > 0): ?>
            <div class="card shadow">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-table me-2"></i>Previsualización de Registros</span>
                    <span class="badge bg-light text-dark"><?= count($_SESSION['preview_import']) ?> registros encontrados</span>
                </div>
                
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-hover table-sm mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Tipo</th>
                                <th>Dispositivo</th>
                                <th>Validación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $validos = 0;
                            foreach($_SESSION['preview_import'] as $r): 
                                if($r['valido']) $validos++;
                            ?>
                                <tr class="<?= $r['valido'] ? '' : 'table-danger' ?>">
                                    <td><?= htmlspecialchars($r['codigo']) ?></td>
                                    <td><?= htmlspecialchars($r['nombre']) ?></td>
                                    <td><?= htmlspecialchars($r['fecha']) ?></td>
                                    <td><?= htmlspecialchars($r['hora']) ?></td>
                                    <td>
                                        <?php if($r['tipo'] == 'entrada'): ?>
                                            <span class="badge bg-success">ENTRADA</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">SALIDA</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($r['disp'] ?: 'N/A') ?></td>
                                    <td class="text-center">
                                        <?php if($r['valido']): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>Usuario existe
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-exclamation-triangle me-1"></i>No existe usuario
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="card-footer bg-white">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex gap-3">
                                <span class="text-success">
                                    <i class="bi bi-check-circle-fill me-1"></i>Válidos: <?= $validos ?>
                                </span>
                                <span class="text-danger">
                                    <i class="bi bi-x-circle-fill me-1"></i>Inválidos: <?= count($_SESSION['preview_import']) - $validos ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <form method="POST" class="d-inline">
                                <button type="submit" name="confirmar" class="btn btn-success"
                                    <?= $validos == 0 ? 'disabled' : '' ?>>
                                    <i class="bi bi-check2-circle me-2"></i>
                                    Confirmar Importación (<?= $validos ?> registros)
                                </button>
                            </form>
                            <form method="POST" class="d-inline ms-2">
                                <button type="submit" name="cancelar" class="btn btn-outline-secondary"
                                    onclick="return confirm('¿Cancelar importación?')">
                                    <i class="bi bi-x-circle me-2"></i>Cancelar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif(isset($_SESSION['preview_import'])): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                No se encontraron registros válidos en el archivo.
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>