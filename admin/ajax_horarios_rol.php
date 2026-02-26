<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION["user_id"]) || !isset($_GET['rol_id'])) {
    exit('Error: Acceso no autorizado');
}

$rol_id = intval($_GET['rol_id']);

$stmt = $conn->prepare("
    SELECT h.* FROM horarios_por_rol h
    WHERE h.rol_id = ? AND h.activo = 1
    ORDER BY h.hora_entrada
");
$stmt->bind_param("i", $rol_id);
$stmt->execute();
$horarios = $stmt->get_result();

if ($horarios->num_rows > 0):
?>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Horario</th>
                    <th>Entrada</th>
                    <th>Tolerancia</th>
                    <th>Tardanza</th>
                    <th>Salida</th>
                    <th>Días</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($h = $horarios->fetch_assoc()): 
                    $hora_tope = date('H:i', strtotime($h['hora_entrada'] . ' + ' . $h['minutos_tolerancia'] . ' minutes'));
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($h['nombre_config']) ?></strong></td>
                        <td><?= date('H:i', strtotime($h['hora_entrada'])) ?></td>
                        <td><?= $h['minutos_tolerancia'] ?> min</td>
                        <td><?= date('H:i', strtotime($h['hora_inicio_tardanza'])) ?></td>
                        <td><?= date('H:i', strtotime($h['hora_salida'])) ?></td>
                        <td><small><?= htmlspecialchars($h['dias_laborales'] ?? 'L-V') ?></small></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        Este rol no tiene horarios configurados.
        <a href="horarios_por_rol.php" class="alert-link">Configurar horarios</a>
    </div>
<?php endif; ?>