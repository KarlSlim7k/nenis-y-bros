<?php
/**
 * ============================================================================
 * MODELO: Mensaje
 * ============================================================================
 * Gestiona los mensajes dentro de las conversaciones
 * 
 * @author Nenis y Bros
 * @version 1.0
 * @date 2025-11-18
 * ============================================================================
 */

class Mensaje {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear nuevo mensaje
     * 
     * @param array $data Datos del mensaje
     * @return int ID del mensaje creado
     */
    public function crear($data) {
        $query = "
            INSERT INTO mensajes (
                id_conversacion, id_remitente, remitente_tipo, 
                contenido, tipo_mensaje, metadata
            ) VALUES (?, ?, ?, ?, ?, ?)
        ";
        
        $params = [
            $data['id_conversacion'],
            $data['id_remitente'],
            $data['remitente_tipo'],
            $data['contenido'],
            $data['tipo_mensaje'] ?? 'texto',
            isset($data['metadata']) ? json_encode($data['metadata']) : null
        ];
        
        $idMensaje = $this->db->insert($query, $params);
        
        // El trigger after_mensaje_insert ya actualiza conversaciones.ultimo_mensaje_fecha
        
        return $idMensaje;
    }
    
    /**
     * Obtener mensajes de una conversación (paginados)
     * 
     * @param int $idConversacion
     * @param int $pagina Número de página (empieza en 1)
     * @param int $limite Mensajes por página
     * @return array
     */
    public function getPorConversacion($idConversacion, $pagina = 1, $limite = 50) {
        $offset = ($pagina - 1) * $limite;
        
        $query = "
            SELECT 
                m.*,
                u.nombre as remitente_nombre,
                u.foto_perfil as remitente_foto
            FROM mensajes m
            LEFT JOIN usuarios u ON m.id_remitente = u.id_usuario
            WHERE m.id_conversacion = ?
            ORDER BY m.fecha_envio ASC
            LIMIT ? OFFSET ?
        ";
        
        return $this->db->fetchAll($query, [$idConversacion, $limite, $offset]);
    }
    
    /**
     * Obtener mensajes recientes de una conversación
     * 
     * @param int $idConversacion
     * @param int $limite Mensajes a retornar
     * @return array
     */
    public function getRecientes($idConversacion, $limite = 50) {
        $query = "
            SELECT 
                m.*,
                u.nombre as remitente_nombre,
                u.foto_perfil as remitente_foto
            FROM mensajes m
            LEFT JOIN usuarios u ON m.id_remitente = u.id_usuario
            WHERE m.id_conversacion = ?
            ORDER BY m.fecha_envio DESC
            LIMIT ?
        ";
        
        $mensajes = $this->db->fetchAll($query, [$idConversacion, $limite]);
        
        // Invertir orden para mostrar del más antiguo al más reciente
        return array_reverse($mensajes);
    }
    
    /**
     * Obtener mensajes nuevos desde una fecha
     * 
     * @param int $idConversacion
     * @param string $desdeFecha Formato: Y-m-d H:i:s
     * @return array
     */
    public function getDesde($idConversacion, $desdeFecha) {
        $query = "
            SELECT 
                m.*,
                u.nombre as remitente_nombre,
                u.foto_perfil as remitente_foto
            FROM mensajes m
            LEFT JOIN usuarios u ON m.id_remitente = u.id_usuario
            WHERE m.id_conversacion = ?
              AND m.fecha_envio > ?
            ORDER BY m.fecha_envio ASC
        ";
        
        return $this->db->fetchAll($query, [$idConversacion, $desdeFecha]);
    }
    
    /**
     * Marcar mensaje como leído
     * 
     * @param int $idMensaje
     * @return bool
     */
    public function marcarLeido($idMensaje) {
        $query = "
            UPDATE mensajes
            SET leido = TRUE,
                fecha_leido = NOW()
            WHERE id_mensaje = ?
        ";
        
        return $this->db->execute($query, [$idMensaje]);
    }
    
    /**
     * Marcar todos los mensajes como leídos
     * 
     * @param int $idConversacion
     * @param int $idUsuario Usuario que marca como leído
     * @param string $rol 'alumno' o 'instructor'
     * @return bool
     */
    public function marcarTodosLeidos($idConversacion, $idUsuario, $rol) {
        // Usar el stored procedure
        $query = "CALL sp_marcar_mensajes_leidos(?, ?, ?)";
        
        return $this->db->execute($query, [$idConversacion, $idUsuario, $rol]);
    }
    
    /**
     * Contar mensajes no leídos por rol
     * 
     * @param int $idConversacion
     * @param string $rol 'alumno' o 'instructor'
     * @return int
     */
    public function contarNoLeidos($idConversacion, $rol) {
        // Rol contrario: si es alumno, contar mensajes del instructor no leídos
        $remitenteContrario = ($rol === 'alumno') ? 'instructor' : 'alumno';
        
        $query = "
            SELECT COUNT(*) as count
            FROM mensajes
            WHERE id_conversacion = ?
              AND remitente_tipo = ?
              AND leido = FALSE
        ";
        
        $result = $this->db->fetchOne($query, [$idConversacion, $remitenteContrario]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Contar todos los mensajes no leídos del usuario
     * 
     * @param int $idUsuario
     * @param string $rol 'alumno' o 'instructor'
     * @return int
     */
    public function contarNoLeidosTotal($idUsuario, $rol) {
        $campoConversacion = ($rol === 'alumno') ? 'c.id_alumno' : 'c.id_instructor';
        $remitenteContrario = ($rol === 'alumno') ? 'instructor' : 'alumno';
        
        $query = "
            SELECT COUNT(*) as count
            FROM mensajes m
            INNER JOIN conversaciones c ON m.id_conversacion = c.id_conversacion
            WHERE $campoConversacion = ?
              AND c.estado = 'activa'
              AND m.remitente_tipo = ?
              AND m.leido = FALSE
        ";
        
        $result = $this->db->fetchOne($query, [$idUsuario, $remitenteContrario]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Obtener último mensaje de una conversación
     * 
     * @param int $idConversacion
     * @return array|null
     */
    public function getUltimo($idConversacion) {
        $query = "
            SELECT 
                m.*,
                u.nombre as remitente_nombre
            FROM mensajes m
            LEFT JOIN usuarios u ON m.id_remitente = u.id_usuario
            WHERE m.id_conversacion = ?
            ORDER BY m.fecha_envio DESC
            LIMIT 1
        ";
        
        return $this->db->fetchOne($query, [$idConversacion]);
    }
    
    /**
     * Buscar mensajes por contenido
     * 
     * @param int $idConversacion
     * @param string $termino
     * @return array
     */
    public function buscar($idConversacion, $termino) {
        $query = "
            SELECT 
                m.*,
                u.nombre as remitente_nombre,
                u.foto_perfil as remitente_foto
            FROM mensajes m
            LEFT JOIN usuarios u ON m.id_remitente = u.id_usuario
            WHERE m.id_conversacion = ?
              AND m.contenido LIKE ?
            ORDER BY m.fecha_envio DESC
            LIMIT 50
        ";
        
        return $this->db->fetchAll($query, [$idConversacion, '%' . $termino . '%']);
    }
    
    /**
     * Eliminar mensajes antiguos (limpieza automática)
     * 
     * @param int $diasAntiguedad
     * @return int Número de mensajes eliminados
     */
    public function limpiarAntiguos($diasAntiguedad = 365) {
        // Usar el stored procedure
        $query = "CALL sp_limpiar_mensajes_antiguos(?)";
        
        return $this->db->execute($query, [$diasAntiguedad]);
    }
    
    /**
     * Obtener estadísticas de mensajes
     * 
     * @param int $idConversacion
     * @return array
     */
    public function getEstadisticas($idConversacion) {
        $query = "
            SELECT 
                COUNT(*) as total_mensajes,
                SUM(CASE WHEN remitente_tipo = 'alumno' THEN 1 ELSE 0 END) as mensajes_alumno,
                SUM(CASE WHEN remitente_tipo = 'instructor' THEN 1 ELSE 0 END) as mensajes_instructor,
                SUM(CASE WHEN remitente_tipo = 'mentoria' THEN 1 ELSE 0 END) as mensajes_mentoria,
                SUM(CASE WHEN leido = TRUE THEN 1 ELSE 0 END) as mensajes_leidos,
                SUM(CASE WHEN leido = FALSE THEN 1 ELSE 0 END) as mensajes_no_leidos,
                MIN(fecha_envio) as primer_mensaje,
                MAX(fecha_envio) as ultimo_mensaje
            FROM mensajes
            WHERE id_conversacion = ?
        ";
        
        return $this->db->fetchOne($query, [$idConversacion]);
    }
}
