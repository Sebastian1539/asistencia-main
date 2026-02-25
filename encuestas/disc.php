<?php
session_start(); // Asegurar que la sesión está iniciada
include("../config/conexion.php");

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$uid = intval($_SESSION["user_id"]);

// Verificar si el usuario ya completó la encuesta
$checkStmt = $conn->prepare("SELECT id FROM resultados_disc WHERE usuario_id = ?");
$checkStmt->bind_param("i", $uid);
$checkStmt->execute();
$yaCompleto = $checkStmt->get_result()->num_rows > 0;
$checkStmt->close();

if ($yaCompleto) {
    header("Location: resultado.php");
    exit();
}

// Obtener encuesta activa con prepared statement
$stmt = $conn->prepare("SELECT * FROM encuestas WHERE activo = 1 LIMIT 1");
$stmt->execute();
$encuestaRes = $stmt->get_result();
$encuesta = $encuestaRes->fetch_assoc();
$stmt->close();

if (!$encuesta) {
    die("
        <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
            <h2>📋 No hay encuestas activas</h2>
            <p>En este momento no hay ninguna encuesta disponible.</p>
            <a href='../dashboard/index.php' class='btn'>Volver al Dashboard</a>
        </div>
    ");
}

// Obtener preguntas con prepared statement
$stmt = $conn->prepare("SELECT * FROM preguntas WHERE encuesta_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $encuesta['id']);
$stmt->execute();
$preguntas = $stmt->get_result();
$totalPreguntas = $preguntas->num_rows;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encuesta DISC | Clínica Gamificación</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
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
        
        .encuesta-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .encuesta-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .encuesta-header::before {
            content: '📊';
            position: absolute;
            right: 20px;
            bottom: 20px;
            font-size: 80px;
            opacity: 0.1;
            transform: rotate(10deg);
        }
        
        .encuesta-titulo {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .encuesta-descripcion {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .progress-container {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            height: 10px;
            margin: 20px 0;
        }
        
        .progress-bar-custom {
            background: white;
            height: 10px;
            border-radius: 10px;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .pregunta-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            animation: fadeInUp 0.5s ease;
        }
        
        .pregunta-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.15);
            border-color: #667eea;
        }
        
        .pregunta-numero {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-right: 10px;
        }
        
        .pregunta-texto {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }
        
        .tipo-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 10px;
        }
        
        .tipo-D { background: #ef476f; color: white; }
        .tipo-I { background: #ffd166; color: #2d3748; }
        .tipo-S { background: #06d6a0; color: white; }
        .tipo-C { background: #118ab2; color: white; }
        
        .opciones-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }
        
        .opcion-item {
            flex: 1;
            text-align: center;
        }
        
        .opcion-label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            color: #718096;
            font-weight: 500;
        }
        
        .opcion-input {
            width: 40px;
            height: 40px;
            border: 2px solid #e2e8f0;
            border-radius: 50%;
            text-align: center;
            font-weight: 600;
            color: #2d3748;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 0 auto;
        }
        
        .opcion-input:checked {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.5);
        }
        
        .opcion-input:hover {
            border-color: #667eea;
            transform: scale(1.05);
        }
        
        .opcion-input[type="radio"] {
            appearance: none;
            -webkit-appearance: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .opcion-input[type="radio"]:checked::before {
            content: "✓";
            color: white;
            font-size: 1.2rem;
            line-height: 36px;
        }
        
        .escala-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            color: #718096;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .escala-min { color: #ef476f; }
        .escala-max { color: #06d6a0; }
        
        .btn-enviar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px 40px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            width: 100%;
            margin-top: 20px;
        }
        
        .btn-enviar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-enviar:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .info-box i {
            color: #2196f3;
            margin-right: 10px;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .disc-info {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .disc-info-item {
            flex: 1;
            min-width: 120px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
        }
        
        .disc-info-item small {
            display: block;
            font-size: 0.7rem;
            opacity: 0.8;
        }
        
        .disc-info-item span {
            font-size: 1.2rem;
            font-weight: 700;
        }
    </style>
</head>
<body>


    <?php include("../dashboard/sidebar.php"); ?>

    <main class="main-content">
        <div class="encuesta-container">
            <!-- Título -->
            <h1 class="page-title">
                <i class="bi bi-clipboard-data me-2" style="color: #667eea;"></i>
                Encuesta DISC
            </h1>

            <!-- Header de la encuesta -->
            <div class="encuesta-header animate__animated animate__fadeInDown">
                <div class="encuesta-titulo">
                    <i class="bi bi-question-circle me-2"></i>
                    <?= htmlspecialchars($encuesta['titulo']) ?>
                </div>
                <div class="encuesta-descripcion">
                    <?= htmlspecialchars($encuesta['descripcion']) ?>
                </div>
                
                <!-- Información DISC -->
                <div class="disc-info">
                    <div class="disc-info-item">
                        <small>Dominante</small>
                        <span style="color: #ef476f;">D</span>
                    </div>
                    <div class="disc-info-item">
                        <small>Influyente</small>
                        <span style="color: #ffd166;">I</span>
                    </div>
                    <div class="disc-info-item">
                        <small>Estable</small>
                        <span style="color: #06d6a0;">S</span>
                    </div>
                    <div class="disc-info-item">
                        <small>Cumplidor</small>
                        <span style="color: #118ab2;">C</span>
                    </div>
                </div>
                
                <!-- Barra de progreso -->
                <div class="progress-container">
                    <div class="progress-bar-custom" id="progressBar" style="width: 0%;"></div>
                </div>
                <small class="text-white-50">
                    <i class="bi bi-info-circle me-1"></i>
                    Responde todas las preguntas para ver tu perfil DISC
                </small>
            </div>

            <!-- Formulario -->
            <form method="POST" action="procesar.php" id="encuestaForm">
                <?php 
                $i = 1; 
                $preguntas->data_seek(0); // Reiniciar el puntero
                while ($p = $preguntas->fetch_assoc()): 
                ?>
                    <div class="pregunta-card" data-pregunta="<?= $i ?>">
                        <div class="pregunta-texto">
                            <span class="pregunta-numero"><?= $i ?></span>
                            <?= htmlspecialchars($p['pregunta']) ?>
                            <span class="tipo-badge tipo-<?= htmlspecialchars($p['tipo']) ?>">
                                Tipo <?= htmlspecialchars($p['tipo']) ?>
                            </span>
                        </div>
                        
                        <div class="opciones-container">
                            <?php for ($v = 1; $v <= 5; $v++): ?>
                                <div class="opcion-item">
                                    <span class="opcion-label">
                                        <?php 
                                        switch($v) {
                                            case 1: echo "Muy en<br>desacuerdo"; break;
                                            case 2: echo "En<br>desacuerdo"; break;
                                            case 3: echo "Neutral"; break;
                                            case 4: echo "De<br>acuerdo"; break;
                                            case 5: echo "Muy de<br>acuerdo"; break;
                                        }
                                        ?>
                                    </span>
                                    <input type="radio" 
                                           class="opcion-input" 
                                           name="respuestas[<?= $p['id'] ?>]" 
                                           value="<?= $v ?>" 
                                           required
                                           onchange="actualizarProgreso()">
                                </div>
                            <?php endfor; ?>
                        </div>
                        
                        <!-- Escala visual -->
                        <div class="escala-labels">
                            <span class="escala-min">◀ Menos</span>
                            <span class="escala-max">Más ▶</span>
                        </div>
                    </div>
                <?php 
                    $i++; 
                endwhile; 
                ?>
                
                <!-- Información adicional -->
                <div class="info-box">
                    <i class="bi bi-lightbulb"></i>
                    <strong>Consejo:</strong> Responde con honestidad. No hay respuestas correctas o incorrectas.
                    <br>
                    <small class="text-muted">
                        <i class="bi bi-check-circle me-1"></i>
                        <?= $totalPreguntas ?> preguntas en total
                    </small>
                </div>

                <!-- Botón de envío -->
                <button type="submit" class="btn-enviar" id="btnEnviar">
                    <i class="bi bi-send-check me-2"></i>
                    Enviar Encuesta
                </button>
                
                <!-- Botón de cancelar -->
                <div class="text-center mt-3">
                    <a href="../dashboard/index.php" class="text-decoration-none text-muted">
                        <i class="bi bi-arrow-left me-1"></i>
                        Cancelar y volver al dashboard
                    </a>
                </div>
            </form>
        </div>
    </main>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script personalizado -->
<script>
    // Actualizar barra de progreso
    function actualizarProgreso() {
        const totalPreguntas = <?= $totalPreguntas ?>;
        const preguntasRespondidas = document.querySelectorAll('input[type="radio"]:checked').length;
        const porcentaje = (preguntasRespondidas / totalPreguntas) * 100;
        
        document.getElementById('progressBar').style.width = porcentaje + '%';
        
        // Cambiar color del botón según el progreso
        const btnEnviar = document.getElementById('btnEnviar');
        if (preguntasRespondidas === totalPreguntas) {
            btnEnviar.style.background = 'linear-gradient(135deg, #06d6a0 0%, #05b588 100%)';
            btnEnviar.disabled = false;
        } else {
            btnEnviar.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        }
    }

    // Validar antes de enviar
    document.getElementById('encuestaForm').addEventListener('submit', function(e) {
        const totalPreguntas = <?= $totalPreguntas ?>;
        const preguntasRespondidas = document.querySelectorAll('input[type="radio"]:checked').length;
        
        if (preguntasRespondidas < totalPreguntas) {
            e.preventDefault();
            
            // Mostrar alerta personalizada
            const alerta = document.createElement('div');
            alerta.className = 'alert alert-warning alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
            alerta.style.zIndex = '9999';
            alerta.innerHTML = `
                <i class="bi bi-exclamation-triangle me-2"></i>
                Te faltan ${totalPreguntas - preguntasRespondidas} preguntas por responder.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alerta);
            
            // Auto-cerrar después de 5 segundos
            setTimeout(() => {
                alerta.remove();
            }, 5000);
            
            // Resaltar preguntas sin responder
            document.querySelectorAll('.pregunta-card').forEach(card => {
                const tieneRespuesta = card.querySelector('input[type="radio"]:checked');
                if (!tieneRespuesta) {
                    card.style.borderColor = '#ef476f';
                    card.style.animation = 'shake 0.5s ease';
                    
                    // Remover la animación después de un tiempo
                    setTimeout(() => {
                        card.style.animation = '';
                    }, 500);
                } else {
                    card.style.borderColor = '#06d6a0';
                }
            });
        } else {
            // Confirmar envío
            if (!confirm('¿Estás seguro de enviar la encuesta? No podrás modificarla después.')) {
                e.preventDefault();
            }
        }
    });

    // Animación shake
    const style = document.createElement('style');
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
    `;
    document.head.appendChild(style);

    // Inicializar progreso
    document.addEventListener('DOMContentLoaded', function() {
        actualizarProgreso();
    });

    // Guardar respuestas en localStorage (opcional)
    function guardarRespuestas() {
        const respuestas = {};
        document.querySelectorAll('input[type="radio"]:checked').forEach(input => {
            const name = input.name;
            const value = input.value;
            respuestas[name] = value;
        });
        localStorage.setItem('encuestaDISC_temp', JSON.stringify(respuestas));
    }

    // Cargar respuestas guardadas (opcional)
    function cargarRespuestas() {
        const guardadas = localStorage.getItem('encuestaDISC_temp');
        if (guardadas) {
            const respuestas = JSON.parse(guardadas);
            Object.keys(respuestas).forEach(name => {
                const input = document.querySelector(`input[name="${name}"][value="${respuestas[name]}"]`);
                if (input) {
                    input.checked = true;
                }
            });
            actualizarProgreso();
        }
    }

    // Preguntar antes de salir de la página
    window.addEventListener('beforeunload', function(e) {
        const preguntasRespondidas = document.querySelectorAll('input[type="radio"]:checked').length;
        if (preguntasRespondidas > 0 && preguntasRespondidas < <?= $totalPreguntas ?>) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Cargar respuestas guardadas al iniciar
    cargarRespuestas();
    
    // Guardar respuestas cada vez que se selecciona una
    document.querySelectorAll('input[type="radio"]').forEach(input => {
        input.addEventListener('change', guardarRespuestas);
    });
</script>

</body>
</html>