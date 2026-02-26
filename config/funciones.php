<?php
/**
 * Determina el estado de asistencia según el horario del rol del usuario
 * 
 * @param int $usuario_id ID del usuario
 * @param string $hora_llegada Hora en formato H:i:s
 * @param string $fecha Fecha en formato Y-m-d
 * @return string 'temprano', 'tarde' o 'falto'
 */
function determinarEstadoAsistenciaPorRol($usuario_id, $hora_llegada, $fecha) {
    global $conn;
    
    // Obtener el rol del usuario
    $stmt = $conn->prepare("
        SELECT u.rol_id, u.rol 
        FROM usuarios u 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Si tiene rol personalizado, buscar su horario
    if (!empty($usuario['rol_id'])) {
        // Obtener día de la semana
        $dia_semana = date('l', strtotime($fecha));
        $dias_espanol = [
            'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'
        ];
        $dia = $dias_espanol[$dia_semana];
        
        // Buscar horario que aplique para este día
        $stmt = $conn->prepare("
            SELECT * FROM horarios_por_rol 
            WHERE rol_id = ? AND activo = 1 
            AND (dias_laborales LIKE ? OR dias_laborales LIKE '%Todos%')
            LIMIT 1
        ");
        $like_dia = "%$dia%";
        $stmt->bind_param("is", $usuario['rol_id'], $like_dia);
        $stmt->execute();
        $horario = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    
    // Si no tiene horario por rol, usar configuración general
    if (empty($horario)) {
        $config = $conn->query("SELECT * FROM configuracion_horarios WHERE activo = 1 LIMIT 1")->fetch_assoc();
        if (!$config) {
            return 'temprano'; // Valor por defecto
        }
        
        $hora_entrada = $config['hora_entrada'];
        $minutos_tolerancia = $config['minutos_tolerancia'];
        $hora_inicio_tardanza = $config['hora_inicio_tardanza'];
    } else {
        $hora_entrada = $horario['hora_entrada'];
        $minutos_tolerancia = $horario['minutos_tolerancia'];
        $hora_inicio_tardanza = $horario['hora_inicio_tardanza'];
    }
    
    $hora_llegada_ts = strtotime($hora_llegada);
    $hora_tope_temprano_ts = strtotime($hora_entrada . ' + ' . $minutos_tolerancia . ' minutes');
    $hora_inicio_tardanza_ts = strtotime($hora_inicio_tardanza);
    
    if ($hora_llegada_ts <= $hora_tope_temprano_ts) {
        return 'temprano';
    } else {
        return 'tarde';
    }
}
?>