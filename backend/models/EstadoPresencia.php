<?php
/**
 * ============================================================================
 * MODELO: EstadoPresencia
 * ============================================================================
 * Gestiona el estado de presencia online de los usuarios
 * 
 * @author Nenis y Bros
 * @version 1.0
 * @date 2025-11-18
 * ============================================================================
 */

class EstadoPresencia {
    private $db;
    
    // Estados permitidos
    const ESTADO_EN_LINEA = 'en_linea';
    const ESTADO_AUSENTE = 'ausente';
    const ESTADO_OCUPADO = 'ocupado';
    const ESTADO_DESCONECTADO = 'desconectado';
    
    // Tiempo en minutos para considerar ausente
    const TIMEOUT_AUSENTE = 5;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Actualizar estado de presencia
     * 
     * @param int $idUsuario
     * @param string $estado
     * @param string|null $mensaje Mensaje personalizado
     * @return bool
     */
    public function actualizar($idUsuario, $estado, $mensaje = null) {
        // Validar estado
        $estadosPermitidos = [
            self::ESTADO_EN_LINEA,
            self::ESTADO_AUSENTE,
            self::ESTADO_OCUPADO,
            self::ESTADO_DESCONECTADO
        ];
        
        if (!in_array($estado, $estadosPermitidos)) {
            throw new Exception('Estado de presencia inválido');
        }
        
        $query = "
            INSERT INTO estado_presencia (
                id_usuario, estado, ultima_actividad, mensaje_estado
            ) VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                estado = VALUES(estado),
                ultima_actividad = VALUES(ultima_actividad),
                mensaje_estado = VALUES(mensaje_estado)
        ";
        
        return $this->db->execute($query, [$idUsuario, $estado, $mensaje]);
    }
    
    /**
     * Obtener estado de presencia de un usuario
     * 
     * @param int $idUsuario
     * @return array|null
     */
    public function get($idUsuario) {
        $query = "
            SELECT * FROM estado_presencia
            WHERE id_usuario = ?
        ";
        
        $estado = $this->db->fetchOne($query, [$idUsuario]);
        
        if ($estado) {
            // Calcular minutos desde última actividad
            $estado['minutos_inactivo'] = $this->getMinutosInactivo($estado['ultima_actividad']);
            
            // Auto-determinar si debe estar ausente
            if ($estado['estado'] === self::ESTADO_EN_LINEA && 
                $estado['minutos_inactivo'] >= self::TIMEOUT_AUSENTE) {
                // Marcar como ausente automáticamente
                $this->actualizar($idUsuario, self::ESTADO_AUSENTE, 'Ausente por inactividad');
                $estado['estado'] = self::ESTADO_AUSENTE;
            }
        }
        
        return $estado;
    }
    
    /**
     * Actualizar actividad (heartbeat)
     * 
     * @param int $idUsuario
     * @return bool
     */
    public function actualizarActividad($idUsuario) {
        $query = "
            UPDATE estado_presencia
            SET ultima_actividad = NOW(),
                estado = CASE 
                    WHEN estado = ? THEN ?
                    ELSE estado
                END
            WHERE id_usuario = ?
        ";
        
        return $this->db->execute($query, [
            self::ESTADO_DESCONECTADO,
            self::ESTADO_EN_LINEA,
            $idUsuario
        ]);
    }
    
    /**
     * Marcar usuario como desconectado
     * 
     * @param int $idUsuario
     * @return bool
     */
    public function setDesconectado($idUsuario) {
        return $this->actualizar($idUsuario, self::ESTADO_DESCONECTADO);
    }
    
    /**
     * Marcar usuario como en línea
     * 
     * @param int $idUsuario
     * @param string|null $mensaje
     * @return bool
     */
    public function setEnLinea($idUsuario, $mensaje = null) {
        return $this->actualizar($idUsuario, self::ESTADO_EN_LINEA, $mensaje);
    }
    
    /**
     * Marcar usuario como ausente
     * 
     * @param int $idUsuario
     * @param string|null $mensaje
     * @return bool
     */
    public function setAusente($idUsuario, $mensaje = null) {
        return $this->actualizar($idUsuario, self::ESTADO_AUSENTE, $mensaje ?? 'Volveré pronto');
    }
    
    /**
     * Marcar usuario como ocupado
     * 
     * @param int $idUsuario
     * @param string|null $mensaje
     * @return bool
     */
    public function setOcupado($idUsuario, $mensaje = null) {
        return $this->actualizar($idUsuario, self::ESTADO_OCUPADO, $mensaje ?? 'No molestar');
    }
    
    /**
     * Obtener instructores en línea
     * 
     * @return array
     */
    public function getInstructoresEnLinea() {
        $query = "
            SELECT 
                ep.*,
                u.nombre,
                u.email,
                u.foto_perfil
            FROM estado_presencia ep
            INNER JOIN usuarios u ON ep.id_usuario = u.id_usuario
            WHERE u.rol = 'instructor'
              AND ep.estado IN (?, ?)
              AND ep.ultima_actividad >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY ep.ultima_actividad DESC
        ";
        
        return $this->db->fetchAll($query, [
            self::ESTADO_EN_LINEA,
            self::ESTADO_AUSENTE,
            self::TIMEOUT_AUSENTE * 2
        ]);
    }
    
    /**
     * Obtener estados de múltiples usuarios
     * 
     * @param array $idsUsuarios
     * @return array Array asociativo [id_usuario => estado]
     */
    public function getMultiples($idsUsuarios) {
        if (empty($idsUsuarios)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($idsUsuarios), '?'));
        
        $query = "
            SELECT 
                id_usuario,
                estado,
                ultima_actividad,
                mensaje_estado
            FROM estado_presencia
            WHERE id_usuario IN ($placeholders)
        ";
        
        $estados = $this->db->fetchAll($query, $idsUsuarios);
        
        // Convertir a array asociativo
        $resultado = [];
        foreach ($estados as $estado) {
            $estado['minutos_inactivo'] = $this->getMinutosInactivo($estado['ultima_actividad']);
            $resultado[$estado['id_usuario']] = $estado;
        }
        
        return $resultado;
    }
    
    /**
     * Limpiar estados inactivos (marcar como desconectados)
     * 
     * @param int $minutosInactividad
     * @return int Número de usuarios marcados como desconectados
     */
    public function limpiarInactivos($minutosInactividad = 30) {
        $query = "
            UPDATE estado_presencia
            SET estado = ?,
                mensaje_estado = NULL
            WHERE estado != ?
              AND ultima_actividad < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ";
        
        return $this->db->execute($query, [
            self::ESTADO_DESCONECTADO,
            self::ESTADO_DESCONECTADO,
            $minutosInactividad
        ]);
    }
    
    /**
     * Calcular minutos desde última actividad
     * 
     * @param string $ultimaActividad Timestamp
     * @return int
     */
    private function getMinutosInactivo($ultimaActividad) {
        $ultima = strtotime($ultimaActividad);
        $ahora = time();
        return round(($ahora - $ultima) / 60);
    }
    
    /**
     * Verificar si el usuario está disponible para chat
     * 
     * @param int $idUsuario
     * @return bool
     */
    public function estaDisponible($idUsuario) {
        $estado = $this->get($idUsuario);
        
        if (!$estado) {
            return false;
        }
        
        return in_array($estado['estado'], [
            self::ESTADO_EN_LINEA,
            self::ESTADO_AUSENTE
        ]) && $estado['minutos_inactivo'] < 30;
    }
    
    /**
     * Obtener estadísticas de presencia
     * 
     * @return array
     */
    public function getEstadisticas() {
        $query = "
            SELECT 
                estado,
                COUNT(*) as total
            FROM estado_presencia
            GROUP BY estado
        ";
        
        $resultados = $this->db->fetchAll($query);
        
        $estadisticas = [
            'en_linea' => 0,
            'ausente' => 0,
            'ocupado' => 0,
            'desconectado' => 0,
            'total' => 0
        ];
        
        foreach ($resultados as $row) {
            $estadisticas[$row['estado']] = (int)$row['total'];
            $estadisticas['total'] += (int)$row['total'];
        }
        
        return $estadisticas;
    }
}
