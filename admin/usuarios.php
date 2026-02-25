<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

// Verificar si es admin
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "admin") {
    header("Location: /asistencia/index.php");
    exit();
}

// Filtros
$sede_id = isset($_GET['sede']) ? intval($_GET['sede']) : '';
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

// Obtener sedes activas
$sedesRes = $conn->query("SELECT id, nombre FROM sedes WHERE activo = 1 ORDER BY nombre");

// Verificar si la consulta de sedes fue exitosa
if (!$sedesRes) {
    die("Error al cargar sedes: " . $conn->error);
}

// Construir query de usuarios con prepared statements
$sql = "
    SELECT u.id, u.nombre, u.email, u.rol, u.avatar, u.fecha_registro,
           s.nombre AS sede, s.id as sede_id,
           COALESCE(p.total, 0) as puntos
    FROM usuarios u
    LEFT JOIN sedes s ON s.id = u.sede_id
    LEFT JOIN puntos p ON p.usuario_id = u.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($sede_id !== '') {
    $sql .= " AND u.sede_id = ?";
    $params[] = $sede_id;
    $types .= "i";
}

if ($busqueda !== '') {
    $sql .= " AND (u.nombre LIKE ? OR u.email LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $types .= "ss";
}

$sql .= " ORDER BY u.id DESC";

// Preparar y ejecutar la consulta
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$usuarios = $stmt->get_result();

// Contar total de usuarios
$total_usuarios = $conn->query("SELECT COUNT(*) as total FROM usuarios")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios | Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: #f8f9fc;
        }
        
        .layout {
            display: flex;
            min-height: 100vh;
        }
        
        .contenido {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            background: linear-gradient(135deg, #f5f7fa 0%, #f8f9fc 100%);
        }
        
        @media (max-width: 768px) {
            .contenido {
                margin-left: 0;
                padding: 20px;
            }
        }
        
        .page-title {
            font-weight: 700;
            color: #2d3748;
            position: relative;
            padding-bottom: 0.75rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
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
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stats-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .stats-info h3 {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }
        
        .stats-info p {
            color: #718096;
            margin: 0;
        }
        
        .filters-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        .filters-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filters-form .form-control,
        .filters-form .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            min-width: 200px;
        }
        
        .filters-form .form-control:focus,
        .filters-form .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(72, 187, 120, 0.4);
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        .table {
            margin: 0;
        }
        
        .table thead th {
            background: #f8fafc;
            color: #4a5568;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
            padding: 15px 10px;
        }
        
        .table tbody td {
            padding: 15px 10px;
            vertical-align: middle;
            color: #2d3748;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .table tbody tr:hover {
            background-color: #f7fafc;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
        }
        
        .badge-role {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-admin {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
        }
        
        .badge-user {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }
        
        .points-badge {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .action-btn {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .action-btn.view {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .action-btn.view:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .action-btn.edit {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .action-btn.edit:hover {
            background: #48bb78;
            color: white;
            transform: translateY(-2px);
        }
        
        .action-btn.delete {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .action-btn.delete:hover {
            background: #f56565;
            color: white;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 50px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #cbd5e0;
        }
        
        .empty-state h4 {
            color: #4a5568;
            margin: 20px 0;
        }
        
        /* DataTables custom */
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 12px;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 10px;
            padding: 8px 12px;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white !important;
        }
    </style>
</head>
<body>

    <?php include(__DIR__ . "/../dashboard/sidebar.php"); ?>

    <main class="main-content">
        <div class="container-fluid">
            <!-- Título y botón nuevo -->
            <div class="page-title">
                <h1 class="mb-0">
                    <i class="bi bi-people-fill me-2" style="color: #667eea;"></i>
                    Gestión de Usuarios
                </h1>
                <a href="usuario_crear.php" class="btn btn-success">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Usuario
                </a>
            </div>

            <!-- Tarjeta de estadísticas -->
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stats-info">
                    <h3><?= number_format($total_usuarios) ?></h3>
                    <p>Total de usuarios registrados</p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-card">
                <form class="filters-form" method="GET">
                    <div class="flex-grow-1">
                        <input type="text" 
                               name="q" 
                               class="form-control" 
                               placeholder="Buscar por nombre o email..."
                               value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    
                    <div>
                        <select name="sede" class="form-select">
                            <option value="">Todas las sedes</option>
                            <?php 
                            $sedesRes->data_seek(0);
                            while($s = $sedesRes->fetch_assoc()): 
                            ?>
                                <option value="<?= $s['id'] ?>" <?= $sede_id == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['nombre']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-2"></i>Filtrar
                    </button>

                    <a href="usuarios.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-repeat me-2"></i>Limpiar
                    </a>
                </form>
            </div>

            <!-- Tabla de usuarios -->
            <div class="table-container">
                <table class="table" id="tablaUsuarios">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Avatar</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Sede</th>
                            <th>Rol</th>
                            <th>Puntos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($usuarios && $usuarios->num_rows > 0): ?>
                            <?php while($u = $usuarios->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="badge bg-secondary">#<?= $u['id'] ?></span></td>
                                    <td>
                                        <img src="<?= !empty($u['avatar']) ? '../assets/img/' . $u['avatar'] : '../assets/img/default.png' ?>" 
                                             alt="Avatar" 
                                             class="user-avatar"
                                             onerror="this.src='../assets/img/default.png'">
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($u['nombre']) ?></strong>
                                    </td>
                                    <td>
                                        <i class="bi bi-envelope me-1 text-muted"></i>
                                        <?= htmlspecialchars($u['email']) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['sede'])): ?>
                                            <span class="badge bg-info">
                                                <i class="bi bi-building me-1"></i>
                                                <?= htmlspecialchars($u['sede']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-role <?= $u['rol'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                                            <i class="bi <?= $u['rol'] === 'admin' ? 'bi-shield-lock' : 'bi-person' ?> me-1"></i>
                                            <?= $u['rol'] === 'admin' ? 'Admin' : 'Usuario' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="points-badge">
                                            <i class="bi bi-star-fill me-1"></i>
                                            <?= number_format($u['puntos']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="usuario_ver.php?id=<?= $u['id'] ?>" class="action-btn view" title="Ver detalles">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="usuario_editar.php?id=<?= $u['id'] ?>" class="action-btn edit" title="Editar usuario">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="usuario_eliminar.php?id=<?= $u['id'] ?>" 
                                           class="action-btn delete" 
                                           title="Eliminar usuario"
                                           onclick="return confirm('¿Estás seguro de eliminar este usuario? Esta acción no se puede deshacer.')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="bi bi-people"></i>
                                        <h4>No se encontraron usuarios</h4>
                                        <p class="text-muted">Prueba con otros filtros o crea un nuevo usuario</p>
                                        <a href="usuario_crear.php" class="btn btn-primary mt-3">
                                            <i class="bi bi-plus-circle me-2"></i>Crear Usuario
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Inicializar DataTable
    $('#tablaUsuarios').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        pageLength: 10,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: [1, 7] } // No ordenar columnas de avatar y acciones
        ]
    });
});
</script>

</body>
</html>