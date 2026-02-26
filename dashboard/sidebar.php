<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar conexión a la base de datos
include(__DIR__ . "/../config/conexion.php");

// Verificar si la conexión existe
if (!isset($conn) || !$conn) {
    die("Error de conexión a la base de datos en sidebar.php");
}

// Obtener sedes activas con manejo de errores
$sidebar_sedes = $conn->query("SELECT id, nombre FROM sedes WHERE activo = 1 ORDER BY nombre");

// Verificar si la consulta fue exitosa
if (!$sidebar_sedes) {
    $sidebar_sedes = false;
    error_log("Error en sidebar.php al obtener sedes: " . $conn->error);
}

// Obtener información del usuario actual
$usuario_nombre = $_SESSION['nombre'] ?? 'Usuario';
$usuario_rol = $_SESSION['rol'] ?? 'usuario';
$usuario_id = $_SESSION['user_id'] ?? null;

// Obtener avatar del usuario si existe
$avatar = null;
if ($usuario_id) {
    $stmt = $conn->prepare("SELECT avatar FROM usuarios WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $avatar = $row['avatar'];
        }
        $stmt->close();
    }
}

// Determinar la página actual para el menú activo
$current_page = basename($_SERVER['PHP_SELF']);
$current_path = $_SERVER['REQUEST_URI'];
// Normalizar la ruta para detectar correctamente las secciones
$current_path_normalized = str_replace('/asistencia-main/', '', $current_path);
?>

<!-- Bootstrap CSS (solo se carga una vez) -->
<?php if (!defined('BOOTSTRAP_LOADED')): ?>
    <?php define('BOOTSTRAP_LOADED', true); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<?php endif; ?>

<style>
/* ====== VARIABLES GLOBALES ====== */
:root {
    --sidebar-width: 260px;
    --sidebar-width-collapsed: 70px;
    --sidebar-bg: #1a1f2b;
    --sidebar-hover: #2d3340;
    --sidebar-text: #a6b0cf;
    --sidebar-text-hover: #ffffff;
    --sidebar-accent: #5e72e4;
    --sidebar-border: #2d3340;
    --header-height: 60px;
    --transition-speed: 0.3s;
}

/* ====== LAYOUT PRINCIPAL ====== */
.app-layout {
    display: flex;
    min-height: 100vh;
    width: 100%;
    background-color: #f4f6f9;
    position: relative;
}

/* ====== SIDEBAR ====== */
.sidebar-custom {
    width: var(--sidebar-width);
    background: linear-gradient(180deg, var(--sidebar-bg) 0%, #232837 100%);
    color: var(--sidebar-text);
    flex-shrink: 0;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    transition: all var(--transition-speed) ease;
    z-index: 1050;
    display: flex;
    flex-direction: column;
}

/* Sidebar collapsed (para escritorio) */
.sidebar-custom.collapsed {
    width: var(--sidebar-width-collapsed);
}

.sidebar-custom.collapsed .sidebar-header span,
.sidebar-custom.collapsed .user-info,
.sidebar-custom.collapsed .nav-link span:not(.nav-badge),
.sidebar-custom.collapsed .menu-title span,
.sidebar-custom.collapsed .sidebar-footer span {
    display: none;
}

.sidebar-custom.collapsed .nav-link {
    padding: 0.75rem;
    justify-content: center;
}

.sidebar-custom.collapsed .nav-link i {
    margin-right: 0;
    font-size: 1.4rem;
}

.sidebar-custom.collapsed .menu-title {
    text-align: center;
    padding: 1rem 0.5rem;
}

.sidebar-custom.collapsed .user-avatar {
    width: 40px;
    height: 40px;
    font-size: 1.2rem;
}

/* Botón de toggle para móvil */
.sidebar-toggle {
    display: none;
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 1060;
    background: linear-gradient(135deg, var(--sidebar-accent) 0%, #825ee4 100%);
    color: white;
    border: none;
    border-radius: 10px;
    width: 45px;
    height: 45px;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(94, 114, 228, 0.3);
    transition: all 0.3s ease;
}

.sidebar-toggle:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(94, 114, 228, 0.4);
}

/* Overlay para móvil */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1040;
    opacity: 0;
    transition: opacity var(--transition-speed) ease;
    pointer-events: none;
}

.sidebar-overlay.active {
    opacity: 1;
    pointer-events: all;
}

/* Scrollbar personalizada */
.sidebar-custom::-webkit-scrollbar {
    width: 5px;
}

.sidebar-custom::-webkit-scrollbar-track {
    background: var(--sidebar-bg);
}

.sidebar-custom::-webkit-scrollbar-thumb {
    background: var(--sidebar-accent);
    border-radius: 10px;
}

.sidebar-custom a {
    text-decoration: none !important;
}

/* Perfil de usuario */
.user-profile {
    padding: 1.5rem 1rem;
    border-bottom: 1px solid var(--sidebar-border);
    margin-bottom: 1rem;
    text-align: center;
    transition: all var(--transition-speed) ease;
}

.user-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--sidebar-accent) 0%, #825ee4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    color: white;
    font-size: 2rem;
    font-weight: bold;
    border: 3px solid rgba(255, 255, 255, 0.2);
    transition: all var(--transition-speed) ease;
    overflow: hidden;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.user-info {
    color: white;
    transition: all var(--transition-speed) ease;
}

.user-name {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.25rem;
    color: white;
    word-break: break-word;
}

.user-role {
    font-size: 0.8rem;
    background: rgba(94, 114, 228, 0.3);
    display: inline-block;
    padding: 0.25rem 1rem;
    border-radius: 20px;
    color: var(--sidebar-text);
}

/* Navegación */
.sidebar-custom .nav {
    flex-wrap: nowrap;
}

.sidebar-custom .nav-link {
    color: var(--sidebar-text);
    padding: 0.75rem 1.5rem;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
    margin: 0.25rem 0;
    white-space: nowrap;
    position: relative;
}

.sidebar-custom .nav-link:hover {
    color: var(--sidebar-text-hover);
    background: var(--sidebar-hover);
    border-left-color: var(--sidebar-accent);
    transform: translateX(5px);
}

.sidebar-custom .nav-link.active {
    color: white;
    background: var(--sidebar-hover);
    border-left-color: var(--sidebar-accent);
    font-weight: 500;
}

.sidebar-custom .nav-link i {
    margin-right: 12px;
    font-size: 1.2rem;
    width: 24px;
    text-align: center;
    color: var(--sidebar-accent);
    transition: all 0.2s ease;
}

.sidebar-custom .nav-link:hover i {
    transform: scale(1.1);
    color: white;
}

/* Títulos de menú */
.menu-title {
    padding: 1rem 1.5rem 0.5rem;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #62748c;
    font-weight: 700;
    white-space: nowrap;
    transition: all var(--transition-speed) ease;
}

/* Logo */
.sidebar-header {
    padding: 1.5rem 1.5rem 0.5rem;
    border-bottom: 1px solid var(--sidebar-border);
    margin-bottom: 0.5rem;
    text-align: center;
    transition: all var(--transition-speed) ease;
}

.sidebar-header img {
    max-height: 45px;
    transition: all var(--transition-speed) ease;
}

.sidebar-custom.collapsed .sidebar-header img {
    max-height: 35px;
}

/* Footer del sidebar */
.sidebar-footer {
    border-top: 1px solid var(--sidebar-border);
    padding: 1rem;
    margin-top: auto;
}

.sidebar-footer .nav-link {
    color: #ef476f;
    padding: 0.5rem 1rem;
}

.sidebar-footer .nav-link:hover {
    background: rgba(239, 71, 111, 0.1);
    border-left-color: #ef476f;
}

.sidebar-footer .nav-link i {
    color: #ef476f;
}

/* Badges */
.nav-badge {
    margin-left: auto;
    background: var(--sidebar-accent);
    color: white;
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 20px;
    transition: all var(--transition-speed) ease;
}

/* ====== CONTENIDO PRINCIPAL ====== */
.main-content {
    flex: 1;
    background: #f4f6f9;
    padding: 30px;
    min-width: 0;
    transition: all var(--transition-speed) ease;
    margin-left: var(--sidebar-width);
}

/* Tooltips personalizados */
[data-tooltip] {
    position: relative;
    cursor: pointer;
}

[data-tooltip]:before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 0.5rem;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    font-size: 0.8rem;
    border-radius: 5px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    z-index: 1000;
    pointer-events: none;
}

[data-tooltip]:hover:before {
    opacity: 1;
    visibility: visible;
    bottom: 120%;
}

/* ====== RESPONSIVE ====== */
/* Tablets y móviles grandes */
@media (max-width: 992px) {
    .sidebar-custom {
        transform: translateX(-100%);
        box-shadow: none;
    }
    
    .sidebar-custom.mobile-open {
        transform: translateX(0);
        box-shadow: 2px 0 20px rgba(0, 0, 0, 0.2);
    }
    
    .sidebar-toggle {
        display: block;
    }
    
    .main-content {
        margin-left: 0;
        padding-top: 70px;
    }
    
    .sidebar-overlay.active {
        display: block;
    }
}

/* Móviles pequeños */
@media (max-width: 576px) {
    .main-content {
        padding: 15px;
        padding-top: 70px;
    }
    
    .sidebar-custom {
        width: 100% !important;
    }
    
    .sidebar-custom.collapsed {
        width: var(--sidebar-width-collapsed) !important;
    }
    
    .sidebar-header img {
        max-height: 35px;
    }
    
    .user-avatar {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .nav-link {
        padding: 0.6rem 1rem;
    }
    
    .menu-title {
        padding: 0.75rem 1rem 0.25rem;
    }
}

/* Pantallas muy grandes */
@media (min-width: 1400px) {
    .main-content {
        padding: 40px;
    }
}

/* Animaciones */
@keyframes slideIn {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.mobile-open {
    animation: slideIn 0.3s ease forwards;
}

.sidebar-overlay.active {
    animation: fadeIn 0.3s ease forwards;
}

/* Contenedor del menú con scroll */
.sidebar-menu {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Scroll moderno opcional */
.sidebar-menu::-webkit-scrollbar {
    width: 5px;
}

.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
}
</style>

<!-- Botón de toggle para móvil -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
    <i class="bi bi-list"></i>
</button>

<!-- Overlay para móvil -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- CONTENEDOR GLOBAL -->
<div class="app-layout">

<!-- SIDEBAR -->
<aside class="sidebar-custom d-flex flex-column shadow" id="sidebar">

    <!-- LOGO -->
    <div class="sidebar-header d-flex justify-content-center align-items-center">
        <a href="/asistencia-main/dashboard/index.php" class="d-block text-center">
            <img src="../assets/img/logo_principal.png"
                 alt="Logo Clínica"
                 class="img-fluid"
                 style="max-height: 50px; filter: brightness(0) invert(1);"
                 onerror="this.onerror=null; this.src='https://via.placeholder.com/150x50/1a1f2b/ffffff?text=CLINICA'">
        </a>
    </div>

    <!-- PERFIL DE USUARIO -->
    <div class="user-profile">
        <div class="user-avatar">
            <?php if ($avatar && file_exists(__DIR__ . "/../uploads/avatars/" . $avatar)): ?>
                <img src="../uploads/avatars/<?= htmlspecialchars($avatar) ?>" alt="Avatar">
            <?php else: ?>
                <?= strtoupper(substr($usuario_nombre, 0, 1)) ?>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($usuario_nombre) ?></div>
            <div class="user-role">
                <?php if ($usuario_rol === 'admin'): ?>
                    <i class="bi bi-shield-fill-check me-1"></i> Administrador
                <?php else: ?>
                    <i class="bi bi-person-fill me-1"></i> Usuario
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MENÚ DE NAVEGACIÓN -->
    <div class="sidebar-menu">
        <!-- Navegación principal -->
        <div class="menu-title">
            <i class="bi bi-compass me-2"></i>
            <span>Navegación</span>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="/asistencia-main/dashboard/index.php" 
                   class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>"
                   data-tooltip="Dashboard">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>

        <!-- Sedes -->
        <div class="menu-title">
            <i class="bi bi-building me-2"></i>
            <span>Sedes</span>
            <?php if ($sidebar_sedes && $sidebar_sedes->num_rows > 0): ?>
                <span class="nav-badge"><?= $sidebar_sedes->num_rows ?></span>
            <?php endif; ?>
        </div>
        <ul class="nav flex-column">
            <?php if ($sidebar_sedes && $sidebar_sedes->num_rows > 0): ?>
                <?php $sidebar_sedes->data_seek(0); ?>
                <?php while ($s = $sidebar_sedes->fetch_assoc()): ?>
                    <li class="nav-item">
                        <a href="/asistencia-main/sedes/ranking.php?sede=<?= $s['id'] ?>" 
                           class="nav-link <?= (strpos($current_path_normalized, 'sede=' . $s['id']) !== false) ? 'active' : '' ?>"
                           data-tooltip="Ver ranking de <?= htmlspecialchars($s['nombre']) ?>">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span><?= htmlspecialchars($s['nombre']) ?></span>
                        </a>
                    </li>
                <?php endwhile; ?>
            <?php else: ?>
                <li class="nav-item">
                    <span class="nav-link text-muted" style="cursor: default;">
                        <i class="bi bi-exclamation-circle"></i>
                        <span>No hay sedes activas</span>
                    </span>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Menú principal -->
        <div class="menu-title">
            <i class="bi bi-menu-button-wide me-2"></i>
            <span>Principal</span>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="/asistencia-main/tienda/index.php" 
                   class="nav-link <?= (strpos($current_path_normalized, 'tienda') !== false) ? 'active' : '' ?>"
                   data-tooltip="Tienda de Recompensas">
                    <i class="bi bi-cart-fill"></i>
                    <span>Tienda</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/asistencia-main/encuestas/disc.php" 
                   class="nav-link <?= (strpos($current_path_normalized, 'encuestas') !== false) ? 'active' : '' ?>"
                   data-tooltip="Encuesta DISC">
                    <i class="bi bi-clipboard-data-fill"></i>
                    <span>Encuesta DISC</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/asistencia-main/perfil/perfil.php" 
                   class="nav-link <?= (strpos($current_path_normalized, 'perfil') !== false) ? 'active' : '' ?>"
                   data-tooltip="Mi Perfil">
                    <i class="bi bi-person-badge-fill"></i>
                    <span>Mi Perfil</span>
                </a>
            </li>
        </ul>

        <!-- Panel de Administración (solo para admin) -->
        <?php if (!empty($_SESSION["rol"]) && $_SESSION["rol"] === "admin"): ?>
            <div class="menu-title text-info">
                <i class="bi bi-shield-lock-fill me-2"></i>
                <span>Administración</span>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="/asistencia-main/admin/usuarios.php" 
                       class="nav-link <?= (strpos($current_path_normalized, 'admin/usuarios') !== false) ? 'active' : '' ?>"
                       data-tooltip="Gestionar Usuarios">
                        <i class="bi bi-people-fill"></i>
                        <span>Usuarios</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/asistencia-main/admin/sedes.php" 
                       class="nav-link <?= (strpos($current_path_normalized, 'admin/sedes') !== false) ? 'active' : '' ?>"
                       data-tooltip="Gestionar Sedes">
                        <i class="bi bi-buildings-fill"></i>
                        <span>Sedes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/asistencia-main/admin/recompensas.php" 
                       class="nav-link <?= (strpos($current_path_normalized, 'admin/recompensas') !== false) ? 'active' : '' ?>"
                       data-tooltip="Gestionar Recompensas">
                        <i class="bi bi-gift-fill"></i>
                        <span>Recompensas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/asistencia-main/admin/roles.php" 
                       class="nav-link <?= (strpos($current_path_normalized, 'admin/roles') !== false) ? 'active' : '' ?>"
                       data-tooltip="Gestionar Roles">
                        <i class="bi bi-person-badge"></i>
                        <span>Roles</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/asistencia-main/admin/asistencias.php" 
                       class="nav-link <?= (strpos($current_path_normalized, 'admin/asistencias') !== false) ? 'active' : '' ?>"
                       data-tooltip="Ver Asistencias">
                        <i class="bi bi-calendar-check-fill"></i>
                        <span>Asistencias</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/asistencia-main/admin/horarios_por_rol.php" 
                       class="nav-link <?= (strpos($current_path_normalized, 'horarios_por_rol') !== false) ? 'active' : '' ?>"
                       data-tooltip="Configurar Horarios por Rol">
                        <i class="bi bi-clock-history"></i>
                        <span>Horarios por Rol</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/asistencia-main/excel/importar.php" 
                       class="nav-link <?= (strpos($current_path_normalized, 'excel/importar') !== false) ? 'active' : '' ?>"
                       data-tooltip="Importar Excel">
                        <i class="bi bi-upload"></i>
                        <span>Importar Excel</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/asistencia-main/admin/niveles.php" 
                       class="nav-link <?= (strpos($current_path_normalized, 'admin/niveles') !== false) ? 'active' : '' ?>"
                       data-tooltip="Administrar Niveles">
                        <i class="bi bi-trophy-fill"></i>
                        <span>Niveles</span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </div>

    <!-- FOOTER DEL SIDEBAR -->
    <div class="sidebar-footer">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="/asistencia-main/auth/logout.php" 
                   class="nav-link" 
                   onclick="return confirm('¿Estás seguro de que deseas cerrar sesión?')"
                   data-tooltip="Cerrar Sesión">
                    <i class="bi bi-door-open-fill"></i>
                    <span>Cerrar sesión</span>
                </a>
            </li>
        </ul>
        
        <!-- Versión del sistema -->
        <div class="text-center mt-3">
            <small class="text-muted" style="font-size: 0.7rem;">
                <i class="bi bi-cpu"></i> v2.0.0
            </small>
        </div>
    </div>

</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    
    // Función para determinar si es móvil
    function isMobile() {
        return window.innerWidth <= 992;
    }
    
    // Función para cerrar sidebar en móvil
    function closeSidebar() {
        if (isMobile()) {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
            document.body.style.overflow = ''; // Restaurar scroll
        }
    }
    
    // Función para abrir sidebar en móvil
    function openSidebar() {
        if (isMobile()) {
            sidebar.classList.add('mobile-open');
            overlay.classList.add('active');
            toggleBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
            document.body.style.overflow = 'hidden'; // Prevenir scroll del body
        }
    }
    
    // Toggle sidebar
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            
            if (isMobile()) {
                // Modo móvil: abrir/cerrar
                if (sidebar.classList.contains('mobile-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            } else {
                // Modo desktop: colapsar/expandir
                sidebar.classList.toggle('collapsed');
                // Guardar preferencia en localStorage
                try {
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                } catch(e) {
                    console.log('localStorage no disponible');
                }
            }
        });
    }
    
    // Cerrar al hacer clic en overlay
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Cerrar al hacer clic en un enlace (móvil)
    document.querySelectorAll('.sidebar-custom .nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            if (isMobile() && !this.hasAttribute('onclick')) {
                // Pequeño retraso para permitir la navegación
                setTimeout(closeSidebar, 150);
            }
        });
    });
    
    // Manejar redimensionamiento de ventana
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (!isMobile()) {
                // En desktop, restaurar estado guardado
                sidebar.classList.remove('mobile-open');
                if (overlay) overlay.classList.remove('active');
                if (toggleBtn) toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
                document.body.style.overflow = '';
                
                // Restaurar estado colapsado de localStorage
                try {
                    const collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                    if (collapsed) {
                        sidebar.classList.add('collapsed');
                    } else {
                        sidebar.classList.remove('collapsed');
                    }
                } catch(e) {
                    console.log('localStorage no disponible');
                }
            } else {
                // En móvil, asegurar que sidebar está cerrado
                closeSidebar();
            }
        }, 250);
    });
    
    // Cargar preferencia de sidebar colapsado en desktop
    if (!isMobile()) {
        try {
            const collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (collapsed) {
                sidebar.classList.add('collapsed');
            }
        } catch(e) {
            console.log('localStorage no disponible');
        }
    }
    
    // Marcar enlace activo basado en la URL actual
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.sidebar-custom .nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href !== '#' && href !== '') {
            // Si la URL actual contiene el href y no es el dashboard principal
            if (currentPath.includes(href) && href !== '/asistencia-main/dashboard/index.php') {
                link.classList.add('active');
            }
        }
    });
    
    // Manejo especial para el dashboard
    const dashboardLink = document.querySelector('a[href="/asistencia-main/dashboard/index.php"]');
    if (dashboardLink) {
        const isDashboard = currentPath === '/asistencia-main/dashboard/index.php' || 
                           currentPath === '/asistencia-main/dashboard/' ||
                           currentPath.includes('/dashboard/index.php');
        if (isDashboard) {
            dashboardLink.classList.add('active');
        } else {
            dashboardLink.classList.remove('active');
        }
    }
});

// Prevenir que el sidebar se cierre al hacer clic dentro
document.querySelector('.sidebar-custom')?.addEventListener('click', function(e) {
    e.stopPropagation();
});

// Manejar errores de localStorage
window.addEventListener('error', function(e) {
    if (e.message && e.message.includes('localStorage')) {
        console.log('localStorage no disponible, ignorando...');
    }
});
</script>