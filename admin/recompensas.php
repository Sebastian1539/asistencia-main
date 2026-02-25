<?php
session_start();
require_once __DIR__ . "/../config/conexion.php";

// Verificación de admin
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../dashboard/index.php");
    exit();
}

// Procesar acciones POST
$mensaje = '';
$tipo_mensaje = '';
if (isset($_POST['stock_updates'])) {
    foreach ($_POST['stock_updates'] as $id => $nuevoStock) {
        $id = intval($id);
        $nuevoStock = intval($nuevoStock);
        $conn->query("UPDATE recompensas SET stock=$nuevoStock WHERE id=$id");
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'crear':

    $nombre = $conn->real_escape_string($_POST['nombre']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    $costo = intval($_POST['costo']);
    $stock = intval($_POST['stock']);
    $imagen = '';

    if (!empty($_FILES['imagen']['name'])) {

        $carpeta = __DIR__ . "/../uploads/";
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0777, true);
        }

        $nombreArchivo = uniqid() . ".webp";
        $rutaServidor = $carpeta . $nombreArchivo;

        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaServidor)) {
            $imagen = "uploads/" . $nombreArchivo;
        }
    }

    $sql = "INSERT INTO recompensas (nombre, descripcion, costo, stock, imagen)
            VALUES ('$nombre', '$descripcion', $costo, $stock, '$imagen')";

    if ($conn->query($sql)) {
        $mensaje = "Recompensa creada correctamente";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "Error: " . $conn->error;
        $tipo_mensaje = "error";
    }

break;
                
            case 'editar':

    $id = intval($_POST['id']);
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    $costo = intval($_POST['costo']);
    $stock = intval($_POST['stock']);
    $activo = isset($_POST['activo']) ? 1 : 0;

    $imagen = $_POST['imagen_actual'] ?? '';

    if (!empty($_FILES['imagen']['name'])) {

        $carpeta = __DIR__ . "/../uploads/";
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0777, true);
        }

        $nombreArchivo = time() . "_" . $_FILES['imagen']['name'];
        $rutaServidor = $carpeta . $nombreArchivo;

        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaServidor)) {
            $imagen = "uploads/" . $nombreArchivo;
        }
    }

    $sql = "UPDATE recompensas SET 
            nombre='$nombre',
            descripcion='$descripcion',
            costo=$costo,
            stock=$stock,
            activo=$activo,
            imagen='$imagen'
            WHERE id=$id";

    if ($conn->query($sql)) {
        $mensaje = "Recompensa actualizada";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "Error: " . $conn->error;
        $tipo_mensaje = "error";
    }

break;
                
            case 'eliminar':
                $id = intval($_POST['id']);
                $sql = "DELETE FROM recompensas WHERE id=$id";
                if ($conn->query($sql)) {
                    $mensaje = "Recompensa eliminada exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al eliminar recompensa";
                    $tipo_mensaje = "error";
                }
                break;
                
            case 'toggle_estado':
                $id = intval($_POST['id']);
                $sql = "UPDATE recompensas SET activo = NOT activo WHERE id=$id";
                $conn->query($sql);
header("Location: " . $_SERVER['PHP_SELF']);
exit();
                
            case 'ajustar_stock':

    $id = intval($_POST['id']);
    $cantidad = intval($_POST['cantidad']);
    $tipo = $_POST['tipo'];

    if ($tipo === 'add') {
        $conn->query("UPDATE recompensas SET stock = stock + $cantidad WHERE id=$id");
    } else {
        $conn->query("UPDATE recompensas SET stock = GREATEST(0, stock - $cantidad) WHERE id=$id");
    }

    // Obtener nuevo stock actualizado
    $resultado = $conn->query("SELECT stock FROM recompensas WHERE id=$id");
    $nuevoStock = $resultado->fetch_assoc()['stock'];

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stock' => intval($nuevoStock)
    ]);
    exit();
        }
    }
}

// Estadísticas avanzadas
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(activo=1) as activas,
        SUM(activo=0) as inactivas,
        SUM(stock=0) as agotadas,
        SUM(stock <= 5 AND stock > 0) as bajo_stock,
        SUM(stock > 20) as alto_stock,
        AVG(costo) as costo_promedio,
        SUM(stock * costo) as valor_total
    FROM recompensas
")->fetch_assoc();

// Obtener recompensas con filtros
$filtro = $_GET['filtro'] ?? 'todos';
$busqueda = $conn->real_escape_string($_GET['busqueda'] ?? '');

$where = "WHERE 1=1";
if ($filtro === 'activas') $where .= " AND activo=1";
if ($filtro === 'inactivas') $where .= " AND activo=0";
if ($filtro === 'agotadas') $where .= " AND stock=0";
if ($filtro === 'bajo_stock') $where .= " AND stock <= 5 AND stock > 0";
if ($busqueda) $where .= " AND (nombre LIKE '%$busqueda%' OR descripcion LIKE '%$busqueda%')";

$recompensas = $conn->query("
    SELECT r.*, COUNT(c.id) as total_canjes
    FROM recompensas r
    LEFT JOIN canjes c ON c.recompensa_id = r.id
    $where
    GROUP BY r.id
    ORDER BY 
        CASE 
            WHEN r.stock = 0 THEN 0
            WHEN r.stock <= 5 THEN 1
            ELSE 2
        END,
        r.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Historial de canjes recientes
$canjes_recientes = $conn->query("
    SELECT c.*, 
           u.nombre as usuario_nombre,
           r.nombre as recompensa_nombre
    FROM canjes c
    JOIN usuarios u ON c.usuario_id = u.id
    JOIN recompensas r ON c.recompensa_id = r.id
    ORDER BY c.fecha DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<?php include(__DIR__ . "/../dashboard/sidebar.php"); ?>

<main class="main-content">
    <!-- Notificaciones Toast -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Header Premium -->
    <div class="admin-header">
        <div class="header-content">
            <div class="header-title">
                <div class="title-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <div class="title-text">
                    <h1>Gestión de Recompensas</h1>
                    <p>Sistema avanzado de administración de beneficios</p>
                </div>
            </div>
            <button class="btn-premium btn-primary-glow" onclick="abrirModalCrear()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Nueva Recompensa</span>
            </button>
        </div>
    </div>

    <!-- Dashboard Stats Futurista -->
    <div class="stats-grid">
        <div class="stat-card holographic" data-tilt>
            <div class="stat-glow"></div>
            <div class="stat-content">
                <div class="stat-icon total">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="9" y1="21" x2="9" y2="9"></line>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value counter" data-target="<?php echo $stats['total']; ?>">0</span>
                    <span class="stat-label">Total Recompensas</span>
                </div>
            </div>
            <div class="stat-progress">
                <div class="progress-bar" style="width: 100%"></div>
            </div>
        </div>

        <div class="stat-card holographic active-card" data-tilt>
            <div class="stat-glow"></div>
            <div class="stat-content">
                <div class="stat-icon active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value counter" data-target="<?php echo $stats['activas']; ?>">0</span>
                    <span class="stat-label">Activas</span>
                </div>
            </div>
            <div class="stat-trend up">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                    <polyline points="17 6 23 6 23 12"></polyline>
                </svg>
                <span>Disponibles</span>
            </div>
        </div>

        <div class="stat-card holographic warning-card" data-tilt>
            <div class="stat-glow"></div>
            <div class="stat-content">
                <div class="stat-icon warning">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value counter" data-target="<?php echo $stats['bajo_stock']; ?>">0</span>
                    <span class="stat-label">Stock Bajo</span>
                </div>
            </div>
            <div class="stat-alert">
                <span class="pulse-dot"></span>
                Requiere atención
            </div>
        </div>

        <div class="stat-card holographic danger-card" data-tilt>
            <div class="stat-glow"></div>
            <div class="stat-content">
                <div class="stat-icon danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value counter" data-target="<?php echo $stats['agotadas']; ?>">0</span>
                    <span class="stat-label">Agotadas</span>
                </div>
            </div>
            <div class="stat-action" onclick="filtrarAgotadas()">
                Ver todas →
            </div>
        </div>

        <div class="stat-card holographic value-card" data-tilt>
            <div class="stat-glow"></div>
            <div class="stat-content">
                <div class="stat-icon value">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo number_format($stats['valor_total'] ?? 0); ?> pts</span>
                    <span class="stat-label">Valor Total</span>
                </div>
            </div>
            <div class="stat-detail">
                Promedio: <?php echo round($stats['costo_promedio'] ?? 0); ?> pts
            </div>
        </div>
    </div>

    <!-- Control Panel -->
    <div class="control-panel">
        <div class="search-box">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input type="text" id="busqueda" placeholder="Buscar recompensas..." 
                   value="<?php echo htmlspecialchars($busqueda); ?>"
                   onkeyup="debounce(buscar, 300)()">
            <div class="search-glow"></div>
        </div>

        <div class="filter-tabs">
            <button class="filter-tab <?php echo $filtro === 'todos' ? 'active' : ''; ?>" onclick="cambiarFiltro('todos')">
                <span>Todas</span>
                <div class="tab-glow"></div>
            </button>
            <button class="filter-tab <?php echo $filtro === 'activas' ? 'active' : ''; ?>" onclick="cambiarFiltro('activas')">
                <span>Activas</span>
                <div class="tab-indicator success"></div>
            </button>
            <button class="filter-tab <?php echo $filtro === 'bajo_stock' ? 'active' : ''; ?>" onclick="cambiarFiltro('bajo_stock')">
                <span>Stock Bajo</span>
                <div class="tab-indicator warning"></div>
            </button>
            <button class="filter-tab <?php echo $filtro === 'agotadas' ? 'active' : ''; ?>" onclick="cambiarFiltro('agotadas')">
                <span>Agotadas</span>
                <div class="tab-indicator danger"></div>
            </button>
        </div>

        <div class="view-toggle">
            <button class="view-btn active" onclick="cambiarVista('grid')" title="Vista Grid">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
            </button>
            <button class="view-btn" onclick="cambiarVista('list')" title="Vista Lista">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>

    <!-- Grid de Recompensas -->
    <div class="rewards-container grid-view" id="rewardsContainer">
        <?php foreach ($recompensas as $r): 
            $estado_class = $r['stock'] == 0 ? 'agotada' : ($r['stock'] <= 5 ? 'bajo-stock' : 'disponible');
            $estado_text = $r['stock'] == 0 ? 'Agotada' : ($r['stock'] <= 5 ? 'Stock Crítico' : 'Disponible');
            $porcentaje_stock = min(100, ($r['stock'] / 50) * 100);
        ?>
        <div class="reward-card <?php echo $estado_class; ?> <?php echo $r['activo'] ? '' : 'inactiva'; ?>" 
     data-data='<?php echo json_encode($r); ?>'>
            
            <div class="card-shine"></div>
            <div class="card-border"></div>
            
            <div class="reward-image">
                <img loading="lazy" src="../<?php echo $r['imagen']; ?>" 
     onerror="this.src='https://via.placeholder.com/300x200/667eea/ffffff?text=Recompensa'">
                <div class="image-overlay">
                    <div class="quick-actions">
                        <button class="action-btn" onclick="event.stopPropagation(); editarRecompensa(<?php echo $r['id']; ?>)" title="Editar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <button class="action-btn" onclick="event.stopPropagation(); toggleEstado(<?php echo $r['id']; ?>)" title="<?php echo $r['activo'] ? 'Desactivar' : 'Activar'; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <?php if ($r['activo']): ?>
                                <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                                <line x1="12" y1="2" x2="12" y2="12"></line>
                                <?php else: ?>
                                <path d="M1 4v6h6M23 20v-6h-6"></path>
                                <path d="M20.49 9A9 9 0 1 0 5.64 15.36"></path>
                                <?php endif; ?>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="stock-badge <?php echo $estado_class; ?>">
                    <?php echo $r['stock']; ?> unid.
                </div>
            </div>

            <div class="reward-content">
                <div class="reward-header">
                    <h3><?php echo htmlspecialchars($r['nombre']); ?></h3>
                    <div class="status-indicator <?php echo $r['activo'] ? 'active' : 'inactive'; ?>" 
                         title="<?php echo $r['activo'] ? 'Activa' : 'Inactiva'; ?>">
                        <span class="pulse"></span>
                    </div>
                </div>
                
                <p class="reward-desc"><?php echo htmlspecialchars(substr($r['descripcion'], 0, 80)) . '...'; ?></p>
                
                <div class="reward-stats">
                    <div class="stat">
                        <span class="stat-label-card">Costo</span>
                        <span class="stat-value-card"><?php echo number_format($r['costo']); ?> pts</span>
                    </div>
                    <div class="stat">
                        <span class="stat-label-card">Canjes</span>
                        <span class="stat-value-card"><?php echo $r['total_canjes']; ?></span>
                    </div>
                </div>

                <div class="stock-bar-container">
                    <div class="stock-bar-bg">
                        <div class="stock-bar-fill <?php echo $estado_class; ?>" style="width: <?php echo $porcentaje_stock; ?>%"></div>
                    </div>
                    <span class="stock-text"><?php echo $estado_text; ?></span>
                </div>

                <div class="reward-actions">
                    <!-- SUMAR -->
<button type="button"
        class="btn-stock btn-add"
        data-id="<?php echo $r['id']; ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
    </svg>
</button>

<!-- RESTAR -->
<button type="button"
        class="btn-stock btn-remove"
        data-id="<?php echo $r['id']; ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="5" y1="12" x2="19" y2="12"></line>
    </svg>
</button>

<!-- ELIMINAR -->
<button type="button"
        class="btn-delete btn-delete-card"
        data-id="<?php echo $r['id']; ?>"
        data-nombre="<?php echo htmlspecialchars($r['nombre']); ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="3 6 5 6 21 6"></polyline>
        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
    </svg>
</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Vista Lista (Oculta por defecto) -->
    <div class="rewards-list-view" id="listView" style="display: none;">
        <div class="list-header">
            <span>Recompensa</span>
            <span>Estado</span>
            <span>Stock</span>
            <span>Costo</span>
            <span>Canjes</span>
            <span>Acciones</span>
        </div>
        <?php foreach ($recompensas as $r): ?>
        <div class="list-item <?php echo $r['activo'] ? '' : 'inactiva'; ?>">
            <div class="list-info">
                <img src="<?php echo $r['imagen'] ?: 'https://via.placeholder.com/40/667eea/ffffff?text=R'; ?>" alt="">
                <div>
                    <h4><?php echo htmlspecialchars($r['nombre']); ?></h4>
                    <small><?php echo substr(htmlspecialchars($r['descripcion']), 0, 50); ?>...</small>
                </div>
            </div>
            <div class="list-status">
                <span class="badge <?php echo $r['activo'] ? 'success' : 'secondary'; ?>">
                    <?php echo $r['activo'] ? 'Activa' : 'Inactiva'; ?>
                </span>
            </div>
            <div class="list-stock">
                <span class="<?php echo $r['stock'] == 0 ? 'text-danger' : ($r['stock'] <= 5 ? 'text-warning' : 'text-success'); ?>">
                    <?php echo $r['stock']; ?>
                </span>
            </div>
            <div class="list-cost">
                <?php echo number_format($r['costo']); ?> pts
            </div>
            <div class="list-canjes">
                <?php echo $r['total_canjes']; ?>
            </div>
            <div class="list-actions">
                <button onclick="editarRecompensa(<?php echo $r['id']; ?>)" class="btn-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </button>
                <button onclick="confirmarEliminar(<?php echo $r['id']; ?>)" class="btn-icon danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Actividad Reciente -->
    <div class="recent-activity">
        <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            Actividad Reciente
        </h3>
        <div class="activity-list">
            <?php foreach ($canjes_recientes as $canje): ?>
            <div class="activity-item">
                <div class="activity-icon success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <div class="activity-content">
                    <p><strong><?php echo htmlspecialchars($canje['usuario_nombre']); ?></strong> 
                       canjeó <strong><?php echo htmlspecialchars($canje['recompensa_nombre']); ?></strong></p>
                    <span class="activity-time"><?php echo date('d/m/Y H:i', strtotime($canje['fecha'])); ?></span>
                </div>
                <div class="activity-points">-<?php echo number_format($canje['puntos_usados']); ?> pts</div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($canjes_recientes)): ?>
            <div class="activity-empty">
                <p>No hay actividad reciente</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal Crear/Editar -->
<div class="modal" id="modalRecompensa">
    <div class="modal-content futuristic">
        <div class="modal-header">
            <h2 id="modalTitle">Nueva Recompensa</h2>
            <button class="modal-close" onclick="cerrarModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="modal-form" id="formRecompensa">
            <input type="hidden" name="accion" id="formAccion" value="crear">
            <input type="hidden" name="id" id="formId">
            <input type="hidden" name="imagen_actual" id="imagenActual">
            
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Nombre de la Recompensa</label>
                    <input type="text" name="nombre" id="formNombre" required 
                           placeholder="Ej: Gift Card Amazon $50">
                </div>
                
                <div class="form-group full-width">
                    <label>Descripción</label>
                    <textarea name="descripcion" id="formDescripcion" rows="3" 
                              placeholder="Describe los detalles de la recompensa..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Costo en Puntos</label>
                    <div class="input-icon">
                        <input type="number" name="costo" id="formCosto" required min="1" placeholder="1000">
                        <span>pts</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Stock Inicial</label>
                    <div class="input-icon">
                        <input type="number" name="stock" id="formStock" required min="0" placeholder="50">
                        <span>unid.</span>
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label>Subir Imagen</label>
                    <div class="image-input-group">
                        <input type="file" name="imagen" id="formImagen" accept="image/*">
                        
                            
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <div class="image-preview" id="imagePreview"></div>
                </div>
                
                <div class="form-group checkbox-group">
                    <label class="toggle-switch">
                        <input type="checkbox" name="activo" id="formActivo" checked>
                        <span class="toggle-slider"></span>
                        <span class="toggle-label">Activo</span>
                    </label>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn-primary-glow">
                    <span id="btnSubmitText">Crear Recompensa</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </button>
            </div>
        </form>
    </div>
    <div class="modal-backdrop"></div>

</div>

<!-- Modal Confirmar Eliminar -->
<div class="modal" id="modalEliminar">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-small">
        <div class="modal-icon danger">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>
        <h3>¿Eliminar Recompensa?</h3>
        <p id="eliminarTexto">Esta acción no se puede deshacer.</p>
        <form method="POST">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id" id="eliminarId">
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="cerrarModalEliminar()">Cancelar</button>
                <button type="submit" class="btn-danger">Eliminar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ajustar Stock -->
<div class="modal" id="modalStock">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-small">
        <h3>Ajustar Stock</h3>
        <form method="POST" id="formStock">
            <input type="hidden" name="accion" value="ajustar_stock">
            <input type="hidden" name="id" id="stockId">
            <input type="hidden" name="tipo" id="stockTipo">
            
            <div class="form-group">
                <label>Cantidad</label>
                <input type="number" name="cantidad" required min="1" value="1" id="stockCantidad">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="cerrarModalStock()">Cancelar</button>
                <button type="submit" class="btn-primary">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<style>
  html, body {
    background-color: #020617;
}
/* Variables y Base */
:root {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #818cf8;
    --secondary: #ec4899;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #3b82f6;
    --dark: #0f172a;
    --darker: #020617;
    --card-bg: rgba(30, 41, 59, 0.7);
    --glass: rgba(255, 255, 255, 0.05);
    --border: rgba(255, 255, 255, 0.1);
    --text-primary: #f8fafc;
    --text-secondary: #94a3b8;
    --shadow-glow: 0 0 20px rgba(99, 102, 241, 0.3);
    --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.3);
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --gradient-danger: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.main-content {
    padding: 2rem;
    background: var(--darker);
    min-height: 100vh;
    font-family: 'Segoe UI', system-ui, sans-serif;
    color: var(--text-primary);
}

/* Header Premium */
.admin-header {
    margin-bottom: 2rem;
    position: relative;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.title-icon {
    width: 60px;
    height: 60px;
    background: var(--gradient-primary);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-glow);
    position: relative;
    overflow: hidden;
}

.title-icon::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shine 3s infinite;
}

.title-icon svg {
    width: 30px;
    height: 30px;
    color: white;
}

.title-text h1 {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(to right, #fff, #a5b4fc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.25rem;
}

.title-text p {
    color: var(--text-secondary);
    font-size: 0.95rem;
}

/* Botones Premium */
.btn-premium {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-primary-glow {
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-primary-glow:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
}

.btn-primary-glow svg {
    width: 20px;
    height: 20px;
}

/* Stats Grid Futurista */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.stat-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.1), transparent 70%);
    opacity: 0;
    transition: opacity 0.3s;
}

.stat-card:hover::before {
    opacity: 1;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: var(--shadow-card);
}

.holographic {
    position: relative;
}



.stat-card:hover .stat-glow {
    opacity: 0.1;
}

.stat-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    z-index: 1;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(99, 102, 241, 0.1);
}

.stat-icon svg {
    width: 24px;
    height: 24px;
}

.stat-icon.total { color: var(--primary); }
.stat-icon.active { color: var(--success); background: rgba(16, 185, 129, 0.1); }
.stat-icon.warning { color: var(--warning); background: rgba(245, 158, 11, 0.1); }
.stat-icon.danger { color: var(--danger); background: rgba(239, 68, 68, 0.1); }
.stat-icon.value { color: var(--secondary); background: rgba(236, 72, 153, 0.1); }

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.stat-progress {
    margin-top: 1rem;
    height: 4px;
    background: rgba(255,255,255,0.05);
    border-radius: 2px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: var(--gradient-primary);
    border-radius: 2px;
    transition: width 1s ease;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.75rem;
    font-size: 0.875rem;
    color: var(--success);
}

.stat-trend svg {
    width: 16px;
    height: 16px;
}

.stat-alert {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.75rem;
    font-size: 0.875rem;
    color: var(--warning);
}

.pulse-dot {
    width: 8px;
    height: 8px;
    background: var(--warning);
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.stat-action {
    margin-top: 0.75rem;
    font-size: 0.875rem;
    color: var(--primary-light);
    cursor: pointer;
    transition: color 0.2s;
}

.stat-action:hover {
    color: var(--primary);
    text-decoration: underline;
}

.stat-detail {
    margin-top: 0.75rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

/* Control Panel */
.control-panel {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    flex: 1;
    min-width: 300px;
}

.search-box input {
    width: 100%;
    padding: 0.875rem 1rem 0.875rem 3rem;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 0.95rem;
    transition: all 0.3s;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    color: var(--text-secondary);
}

.search-glow {
    position: absolute;
    inset: -2px;
    background: var(--gradient-primary);
    border-radius: 14px;
    opacity: 0;
    z-index: -1;
    filter: blur(8px);
    transition: opacity 0.3s;
}

.search-box input:focus ~ .search-glow {
    opacity: 0.3;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    background: var(--card-bg);
    padding: 0.375rem;
    border-radius: 12px;
    border: 1px solid var(--border);
}

.filter-tab {
    position: relative;
    padding: 0.625rem 1.25rem;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s;
    overflow: hidden;
}

.filter-tab:hover {
    color: var(--text-primary);
}

.filter-tab.active {
    color: var(--text-primary);
    background: rgba(99, 102, 241, 0.2);
}

.tab-glow {
    position: absolute;
    inset: 0;
    background: var(--gradient-primary);
    opacity: 0;
    transition: opacity 0.3s;
}

.filter-tab.active .tab-glow {
    opacity: 0.2;
}

.tab-indicator {
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    border-radius: 50%;
    opacity: 0;
}

.filter-tab.active .tab-indicator {
    opacity: 1;
}

.tab-indicator.success { background: var(--success); }
.tab-indicator.warning { background: var(--warning); }
.tab-indicator.danger { background: var(--danger); }

.view-toggle {
    display: flex;
    gap: 0.5rem;
    background: var(--card-bg);
    padding: 0.375rem;
    border-radius: 10px;
    border: 1px solid var(--border);
}

.view-btn {
    padding: 0.5rem;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s;
}

.view-btn svg {
    width: 18px;
    height: 18px;
}

.view-btn.active {
    background: var(--primary);
    color: white;
}

/* Rewards Grid */
.rewards-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.rewards-container.list-view {
    display: none;
}

.reward-card {
    background: rgba(30, 41, 59, 0.85);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 16px;
    overflow: hidden;
    position: relative;
    cursor: pointer;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    
}

.reward-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), transparent);
    opacity: 0;
    transition: opacity 0.3s;
}

.reward-card:hover::before {
    opacity: 1;
}

.card-shine {
    position: absolute;
    top: 0;
    left: -100%;
    width: 50%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.5s;
    z-index: 2;
    pointer-events: none;
}

.reward-card:hover .card-shine {
    left: 150%;
}

.card-border {
    position: absolute;
    inset: 0;
    border-radius: 20px;
    padding: 1px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.5), transparent, rgba(236, 72, 153, 0.3));
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    opacity: 0;
    transition: opacity 0.3s;
}

.reward-card:hover .card-border {
    opacity: 1;
}


.reward-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.35);
}
.reward-card.inactiva {
    opacity: 0.6;
}

.reward-card.agotada {
    border-color: rgba(239, 68, 68, 0.3);
}

.reward-card.bajo-stock {
    border-color: rgba(245, 158, 11, 0.3);
}

.reward-image {
    position: relative;
    height: 180px;
    overflow: hidden;
}

.reward-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
    pointer-events: none;

}

.reward-card:hover .reward-image img {
    transform: scale(1.1);
}

.image-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
}

.reward-card:hover .image-overlay {
    opacity: 1;
}

.quick-actions {
    display: flex;
    gap: 1rem;
    pointer-events: auto;
}

.action-btn {
    pointer-events: auto;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.action-btn:hover {
    background: var(--primary);
    transform: scale(1.1);
}

.action-btn svg {
    width: 20px;
    height: 20px;
}

.stock-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.375rem 0.875rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.stock-badge.disponible {
    background: rgba(16, 185, 129, 0.2);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.stock-badge.bajo-stock {
    background: rgba(245, 158, 11, 0.2);
    color: var(--warning);
    border: 1px solid rgba(245, 158, 11, 0.3);
    animation: pulse 2s infinite;
}

.stock-badge.agotada {
    background: rgba(239, 68, 68, 0.2);
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.reward-content {
    padding: 1.5rem;
}

.reward-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.reward-header h3 {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.3;
    flex: 1;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    position: relative;
    margin-left: 0.5rem;
}

.status-indicator.active {
    background: var(--success);
    box-shadow: 0 0 10px var(--success);
}

.status-indicator.inactive {
    background: var(--text-secondary);
}

.status-indicator .pulse {
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid var(--success);
    animation: pulse-ring 2s infinite;
}

.reward-desc {
    color: var(--text-secondary);
    font-size: 0.875rem;
    line-height: 1.5;
    margin-bottom: 1rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.reward-stats {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.stat {
    display: flex;
    flex-direction: column;
}

.stat-label-card {
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value-card {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.stock-bar-container {
    margin-bottom: 1rem;
}

.stock-bar-bg {
    height: 6px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.stock-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease;
}

.stock-bar-fill.disponible { background: var(--gradient-success); }
.stock-bar-fill.bajo-stock { background: var(--gradient-warning); }
.stock-bar-fill.agotada { background: var(--gradient-danger); }

.stock-text {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.reward-actions {
    position: relative;
    z-index: 10;
    display: flex;
    gap: 0.5rem;
}

.btn-stock, .btn-delete {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid var(--border);
    background: rgba(255, 255, 255, 0.03);
    border-radius: 8px;
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    position: relative;
    z-index: 20;
}

.btn-stock:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.btn-delete:hover {
    background: var(--danger);
    border-color: var(--danger);
    color: white;
}

.btn-stock svg, .btn-delete svg {
    width: 18px;
    height: 18px;
}

/* Vista Lista */
.rewards-list-view {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

.list-header {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr 120px;
    padding: 1rem 1.5rem;
    background: rgba(0, 0, 0, 0.2);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
    font-weight: 600;
}

.list-item {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr 120px;
    padding: 1rem 1.5rem;
    align-items: center;
    border-top: 1px solid var(--border);
    transition: background 0.2s;
}

.list-item:hover {
    background: rgba(99, 102, 241, 0.05);
}

.list-item.inactiva {
    opacity: 0.6;
}

.list-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.list-info img {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    object-fit: cover;
}

.list-info h4 {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.list-info small {
    color: var(--text-secondary);
    font-size: 0.8rem;
}

.badge {
    display: inline-flex;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge.success {
    background: rgba(16, 185, 129, 0.2);
    color: var(--success);
}

.badge.secondary {
    background: rgba(148, 163, 184, 0.2);
    color: var(--text-secondary);
}

.text-success { color: var(--success); }
.text-warning { color: var(--warning); }
.text-danger { color: var(--danger); }

.list-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border: none;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.btn-icon:hover {
    background: var(--primary);
    color: white;
}

.btn-icon.danger:hover {
    background: var(--danger);
}

.btn-icon svg {
    width: 16px;
    height: 16px;
}

/* Actividad Reciente */
.recent-activity {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 1.5rem;
}

.recent-activity h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    color: var(--text-primary);
}

.recent-activity h3 svg {
    width: 24px;
    height: 24px;
    color: var(--primary);
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    transition: all 0.2s;
}

.activity-item:hover {
    background: rgba(99, 102, 241, 0.1);
    transform: translateX(5px);
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.activity-icon.success {
    background: rgba(16, 185, 129, 0.2);
    color: var(--success);
}

.activity-icon svg {
    width: 20px;
    height: 20px;
}

.activity-content {
    flex: 1;
}

.activity-content p {
    color: var(--text-primary);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.activity-content strong {
    color: var(--primary-light);
}

.activity-time {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.activity-points {
    font-weight: 700;
    color: var(--danger);
    font-size: 0.95rem;
}

.activity-empty {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

/* Modales Futuristas */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal.active {
    display: flex;
}

.modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.8);
        z-index: 0;

}

.modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 24px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    z-index: 1;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    animation: modalIn 0.3s ease;
}

.modal-content.futuristic::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 24px;
    padding: 1px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.5), rgba(236, 72, 153, 0.3));
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

.modal-small {
    max-width: 400px;
    text-align: center;
    padding: 2rem;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.modal-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
}

.modal-close {
    width: 36px;
    height: 36px;
    border: none;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--danger);
    color: white;
}

.modal-close svg {
    width: 20px;
    height: 20px;
}

.modal-form {
    padding: 1.5rem;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-secondary);
}

.form-group input,
.form-group textarea,
.form-group select {
    padding: 0.75rem 1rem;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 0.95rem;
    transition: all 0.2s;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.input-icon {
    position: relative;
}

.input-icon input {
    padding-right: 3rem;
}

.input-icon span {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.image-input-group {
    display: flex;
    gap: 0.5rem;
}

.image-input-group input {
    flex: 1;
}

.btn-preview {
    padding: 0.75rem;
    background: rgba(99, 102, 241, 0.2);
    border: 1px solid var(--primary);
    border-radius: 10px;
    color: var(--primary);
    cursor: pointer;
    transition: all 0.2s;
}

.btn-preview:hover {
    background: var(--primary);
    color: white;
}

.btn-preview svg {
    width: 20px;
    height: 20px;
}

.image-preview {
    margin-top: 1rem;
    border-radius: 10px;
    overflow: hidden;
    display: none;
}

.image-preview.active {
    display: block;
}

.image-preview img {
    width: 100%;
    height: 150px;
    object-fit: cover;
}

.checkbox-group {
    flex-direction: row;
    align-items: center;
}

.toggle-switch {
    position: relative;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    gap: 0.75rem;
}

.toggle-switch input {
    display: none;
}

.toggle-slider {
    width: 50px;
    height: 26px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 13px;
    position: relative;
    transition: background 0.3s;
}

.toggle-slider::after {
    content: '';
    position: absolute;
    width: 22px;
    height: 22px;
    background: white;
    border-radius: 50%;
    top: 2px;
    left: 2px;
    transition: transform 0.3s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.toggle-switch input:checked + .toggle-slider {
    background: var(--primary);
}

.toggle-switch input:checked + .toggle-slider::after {
    transform: translateX(24px);
}

.toggle-label {
    color: var(--text-primary);
    font-weight: 500;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

.btn-secondary {
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-secondary);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.btn-danger {
    padding: 0.75rem 1.5rem;
    background: var(--danger);
    border: none;
    border-radius: 10px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.btn-primary {
    padding: 0.75rem 1.5rem;
    background: var(--primary);
    border: none;
    border-radius: 10px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.modal-icon {
    width: 64px;
    height: 64px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
}

.modal-icon.danger {
    background: rgba(239, 68, 68, 0.2);
    color: var(--danger);
}

.modal-icon svg {
    width: 32px;
    height: 32px;
}

.modal-small h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.modal-small p {
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 2rem;
    right: 2rem;
    z-index: 2000;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.toast {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    animation: slideIn 0.3s ease;
    min-width: 300px;
}

.toast.success {
    border-left: 4px solid var(--success);
}

.toast.error {
    border-left: 4px solid var(--danger);
}

.toast-icon {
    width: 24px;
    height: 24px;
    flex-shrink: 0;
}

.toast.success .toast-icon { color: var(--success); }
.toast.error .toast-icon { color: var(--danger); }

/* Animaciones */
@keyframes shine {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

@keyframes rotate {
    100% { transform: rotate(360deg); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@keyframes pulse-ring {
    0% { transform: scale(0.8); opacity: 1; }
    100% { transform: scale(2); opacity: 0; }
}

@keyframes modalIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .main-content {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .control-panel {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-box {
        min-width: 100%;
    }
    
    .filter-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
    }
    
    .rewards-container {
        grid-template-columns: 1fr;
    }
    
    .list-header,
    .list-item {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    
    .list-header {
        display: none;
    }
    
    .list-item > * {
        justify-content: flex-start !important;
    }
}

/* Scrollbar personalizada */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--darker);
}

::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-light);
}
.tab-paused * {
    animation: none !important;
    transition: none !important;
    transform: none !important;
}
</style>

<script>
  let stockTemp = {};
 const container = document.getElementById('rewardsContainer');
document.addEventListener('DOMContentLoaded', function() {

    const container = document.getElementById('rewardsContainer');

    container.addEventListener('click', function (e) {

        const addBtn = e.target.closest('.btn-add');
        if (addBtn) {
            e.stopPropagation();
            ajustarStock(addBtn.dataset.id, 'add');
            return;
        }

        const removeBtn = e.target.closest('.btn-remove');
        if (removeBtn) {
            e.stopPropagation();
            ajustarStock(removeBtn.dataset.id, 'remove');
            return;
        }

        const deleteBtn = e.target.closest('.btn-delete-card');
        if (deleteBtn) {
            e.stopPropagation();
            confirmarEliminar(deleteBtn.dataset.id, deleteBtn.dataset.nombre);
            return;
        }

    });

});
  document.addEventListener("visibilitychange", function () {
    if (document.hidden) {
        document.body.classList.add("tab-hidden");
    } else {
        document.body.classList.remove("tab-hidden");
    }
    document.addEventListener("visibilitychange", function () {
    if (document.hidden) {
        document.body.classList.add("tab-paused");
    } else {
        document.body.classList.remove("tab-paused");
    }
});
});
  if (document.querySelectorAll('.reward-card').length > 30) {
    document.querySelectorAll('[data-tilt]').forEach(el => el.removeAttribute('data-tilt'));
}
  
// Datos de recompensas para edición
const recompensasData = <?php echo json_encode($recompensas); ?>;
document.getElementById("formImagen").addEventListener("change", function(e) {
    const preview = document.getElementById("imagePreview");
    const file = e.target.files[0];

    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(event) {
        preview.innerHTML = `<img src="${event.target.result}" style="width:100%;height:150px;object-fit:cover;">`;
        preview.classList.add("active");
    };
    reader.readAsDataURL(file);
});
// Contadores animados
document.addEventListener('DOMContentLoaded', () => {
    const counters = document.querySelectorAll('.counter');
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;
        
        const updateCounter = () => {
            current += step;
            if (current < target) {
                counter.textContent = Math.floor(current);
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target;
            }
        };
        
        updateCounter();
    });
    
    // Efecto tilt en cards
    
    
    // Mostrar mensaje si existe
    <?php if ($mensaje): ?>
    mostrarToast('<?php echo $mensaje; ?>', '<?php echo $tipo_mensaje; ?>');
    <?php endif; ?>
});

// Funciones de utilidad
let debounceTimer;
function debounce(func, wait) {
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(debounceTimer);
            func(...args);
        };
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(later, wait);
    };
}

function mostrarToast(mensaje, tipo = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${tipo}`;
    toast.innerHTML = `
        <svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            ${tipo === 'success' 
                ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>'
                : '<circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>'
            }
        </svg>
        <span>${mensaje}</span>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
document.getElementById('formRecompensa').addEventListener('submit', function () {

    for (let id in stockTemp) {

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'stock_updates[' + id + ']';
        input.value = stockTemp[id];

        this.appendChild(input);
    }
});
// Gestión de modales
function abrirModalCrear() {
    document.getElementById('modalTitle').textContent = 'Nueva Recompensa';
    document.getElementById('formAccion').value = 'crear';
    document.getElementById('formId').value = '';
    document.getElementById('formNombre').value = '';
    document.getElementById('formDescripcion').value = '';
    document.getElementById('formCosto').value = '';
    document.getElementById('formStock').value = '';
    document.getElementById('formImagen').value = '';
    document.getElementById('formActivo').checked = true;
    document.getElementById('btnSubmitText').textContent = 'Crear Recompensa';
    document.getElementById('imagePreview').innerHTML = '';
    document.getElementById('imagePreview').classList.remove('active');
    
    document.getElementById('modalRecompensa').classList.add('active');
}

function editarRecompensa(id) {
    const r = recompensasData.find(item => item.id == id);
    if (!r) return;

    document.getElementById('modalTitle').textContent = 'Editar Recompensa';
    document.getElementById('formAccion').value = 'editar';
    document.getElementById('formId').value = r.id;
    document.getElementById('formNombre').value = r.nombre;
    document.getElementById('formDescripcion').value = r.descripcion;
    document.getElementById('formCosto').value = r.costo;
    document.getElementById('formStock').value = r.stock;

    // ⚠️ NO TOCAR formImagen.value

    document.getElementById('formActivo').checked = r.activo == 1;
    document.getElementById('btnSubmitText').textContent = 'Guardar Cambios';

    document.getElementById('imagenActual').value = r.imagen || '';

if (r.imagen) {
    document.getElementById('imagePreview').innerHTML =
        `<img src="../${r.imagen}" alt="Preview">`;
    document.getElementById('imagePreview').classList.add('active');
} else {
    document.getElementById('imagePreview').innerHTML = '';
    document.getElementById('imagePreview').classList.remove('active');
}

    document.getElementById('modalRecompensa').classList.add('active');
}

function cerrarModal() {
    document.getElementById('modalRecompensa').classList.remove('active');
}

function previewImageLocal(event) {
    const preview = document.getElementById('imagePreview');
    const file = event.target.files[0];

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            preview.classList.add('active');
        }
        reader.readAsDataURL(file);
    }
}

function confirmarEliminar(id, nombre) {
    document.getElementById('eliminarId').value = id;
    document.getElementById('eliminarTexto').textContent = `¿Estás seguro de eliminar "${nombre}"? Esta acción no se puede deshacer.`;
    document.getElementById('modalEliminar').classList.add('active');
}

function cerrarModalEliminar() {
    document.getElementById('modalEliminar').classList.remove('active');
}

function ajustarStock(id, tipo) {

    const formData = new FormData();
    formData.append('accion', 'ajustar_stock');
    formData.append('id', id);
    formData.append('tipo', tipo);
    formData.append('cantidad', 1);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {

        if (!data.success) return;

        const nuevoStock = data.stock;

        // 🔥 ACTUALIZAR ARRAY GLOBAL (CLAVE)
        const recompensa = recompensasData.find(r => r.id == id);
        if (recompensa) {
            recompensa.stock = nuevoStock;
        }

        // 🔎 Buscar tarjeta
        const btn = document.querySelector(`.btn-add[data-id="${id}"], .btn-remove[data-id="${id}"]`);
        if (!btn) return;

        const card = btn.closest('.reward-card');
        if (!card) return;

        const badge = card.querySelector('.stock-badge');
        const stockText = card.querySelector('.stock-text');
        const stockBar = card.querySelector('.stock-bar-fill');

        // ✅ Actualizar número
        badge.textContent = nuevoStock + " unid.";

        // ✅ Actualizar estado
        if (nuevoStock === 0) {
            stockText.textContent = "Agotada";
        } else if (nuevoStock <= 5) {
            stockText.textContent = "Stock Crítico";
        } else {
            stockText.textContent = "Disponible";
        }

        // ✅ Actualizar barra
        const porcentaje = Math.min(100, (nuevoStock / 50) * 100);
        stockBar.style.width = porcentaje + "%";

        // 🔥 Si modal está abierto actualizar input
        const modal = document.getElementById('modalRecompensa');
        const formId = document.getElementById('formId').value;

        if (modal.classList.contains('active') && formId == id) {
            document.getElementById('formStock').value = nuevoStock;
        }

    })
    .catch(error => console.error("Error:", error));
}
document.getElementById('formRecompensa')
.addEventListener('submit', function() {
    formChanged = false;
});
function cerrarModalStock() {
    document.getElementById('modalStock').classList.remove('active');
}

function toggleEstado(id) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="accion" value="toggle_estado">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Filtros y búsqueda
function cambiarFiltro(filtro) {
    window.location.href = `?filtro=${filtro}&busqueda=${document.getElementById('busqueda').value}`;
}

function filtrarAgotadas() {
    cambiarFiltro('agotadas');
}

function buscar() {
    const valor = document.getElementById('busqueda').value;
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('busqueda', valor);
    window.location.search = urlParams.toString();
}

function cambiarVista(vista) {
    const grid = document.getElementById('rewardsContainer');
    const list = document.getElementById('listView');
    const buttons = document.querySelectorAll('.view-btn');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');
    
    if (vista === 'grid') {
        grid.style.display = 'grid';
        list.style.display = 'none';
    } else {
        grid.style.display = 'none';
        list.style.display = 'block';
    }
}



// Cerrar modales al hacer click fuera
document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', function() {
        this.parentElement.classList.remove('active');
    });
});

// Prevenir cierre accidental con cambios sin guardar
let formChanged = false;
document.querySelectorAll('#formRecompensa input, #formRecompensa textarea').forEach(input => {
    input.addEventListener('change', () => formChanged = true);
});

window.addEventListener('beforeunload', (e) => {
    if (formChanged && document.getElementById('modalRecompensa').classList.contains('active')) {
        e.preventDefault();
        e.returnValue = '';
    }
});
document.addEventListener("visibilitychange", function () {
    if (document.hidden) {
        document.querySelectorAll('.stat-glow').forEach(el => {
            el.style.animation = 'none';
        });
    }
});
</script>
</body>
</html>