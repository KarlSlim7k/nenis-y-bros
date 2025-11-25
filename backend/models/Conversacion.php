<?php
/**
 * ============================================================================
 * MODELO: Conversacion
 * ============================================================================
 * Gestiona las conversaciones entre alumnos e instructores
 * 
 * @author Nenis y Bros
 * @version 1.0
 * @date 2025-11-18
 * ============================================================================
 */

class Conversacion {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear nueva conversación
     * 
     * @param array $data Datos de la conversación
     * @return int ID de la conversación creada
     */
    public function crear($data) {
        $query = "
            INSERT INTO conversaciones (
                id_curso, id_alumno, id_instructor, tipo_conversacion
            ) VALUES (?, ?, ?, ?)
        ";
        
        $params = [
            $data['id_curso'],
            $data['id_alumno'],
            $data['id_instructor'],
            $data['tipo_conversacion'] ?? 'instructor'
        ];
        
        return $this->db->insert($query, $params);
    }
    
    /**
     * Obtener conversación por ID
     * 
     * @param int $idConversacion
     * @return array|null
     */
    public function getById($idConversacion) {
        $query = "
            SELECT * FROM vista_conversaciones_completas
            WHERE id_conversacion = ?
        ";
        
        return $this->db->fetchOne($query, [$idConversacion]);
    }
    
    /**
     * Obtener o crear conversación entre alumno e instructor
     * 
     * @param int $idCurso
     * @param int $idAlumno
     * @param int $idInstructor
     * @param string $tipo 'instructor' o 'mentoria'
     * @return array
     */
    public function getOrCreate($idCurso, $idAlumno, $idInstructor, $tipo = 'instructor') {
        // Buscar conversación existente
        $query = "
            SELECT * FROM conversaciones
            WHERE id_curso = ?
              AND id_alumno = ?
              AND id_instructor = ?
              AND tipo_conversacion = ?
        ";
        
        $conversacion = $this->db->fetchOne($query, [$idCurso, $idAlumno, $idInstructor, $tipo]);
        
        if ($conversacion) {
            // Reactivar si estaba archivada
            if ($conversacion['estado'] === 'archivada') {
                $this->actualizarEstado($conversacion['id_conversacion'], 'activa');
                $conversacion['estado'] = 'activa';
            }
            
            return $conversacion;
        }
        
        // Crear nueva conversación
        $idConversacion = $this->crear([
            'id_curso' => $idCurso,
            'id_alumno' => $idAlumno,
            'id_instructor' => $idInstructor,
            'tipo_conversacion' => $tipo
        ]);
        
        return $this->getById($idConversacion);
    }
    
    /**
     * Listar conversaciones del usuario
     * 
     * @param int $idUsuario
     * @param string $rol 'alumno' o 'instructor'
     * @param string $estado 'activa', 'archivada' o null para todas
     * @return array
     */
    public function listarPorUsuario($idUsuario, $rol, $estado = 'activa') {
        $campo = ($rol === 'alumno') ? 'id_alumno' : 'id_instructor';
        
        $query = "
            SELECT * FROM vista_conversaciones_completas
            WHERE $campo = ?
        ";
        
        $params = [$idUsuario];
        
        if ($estado) {
            $query .= " AND estado = ?";
            $params[] = $estado;
        }
        
        $query .= " ORDER BY ultimo_mensaje_fecha DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Actualizar estado de conversación
     * 
     * @param int $idConversacion
     * @param string $estado 'activa' o 'archivada'
     * @return bool
     */
    public function actualizarEstado($idConversacion, $estado) {
        $query = "
            UPDATE conversaciones
            SET estado = ?
            WHERE id_conversacion = ?
        ";
        
        return $this->db->execute($query, [$estado, $idConversacion]);
    }
    
    /**
     * Archivar conversación
     * 
     * @param int $idConversacion
     * @param int $idUsuario ID del usuario que archiva
     * @return bool
     */
    public function archivar($idConversacion, $idUsuario) {
        // Verificar que el usuario pertenece a la conversación
        if (!$this->perteneceAlUsuario($idConversacion, $idUsuario)) {
            return false;
        }
        
        return $this->actualizarEstado($idConversacion, 'archivada');
    }
    
    /**
     * Verificar si un usuario pertenece a una conversación
     * 
     * @param int $idConversacion
     * @param int $idUsuario
     * @return bool
     */
    public function perteneceAlUsuario($idConversacion, $idUsuario) {
        $query = "
            SELECT COUNT(*) as count
            FROM conversaciones
            WHERE id_conversacion = ?
              AND (id_alumno = ? OR id_instructor = ?)
        ";
        
        $result = $this->db->fetchOne($query, [$idConversacion, $idUsuario, $idUsuario]);
        return $result['count'] > 0;
    }
    
    /**
     * Obtener estadísticas de conversaciones del instructor
     * 
     * @param int $idInstructor
     * @return array
     */
    public function getEstadisticasInstructor($idInstructor) {
        $query = "
            SELECT 
                COUNT(DISTINCT c.id_conversacion) as conversaciones_activas,
                SUM(CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM mensajes m 
                        WHERE m.id_conversacion = c.id_conversacion 
                        AND m.remitente_tipo = 'alumno' 
                        AND m.leido = FALSE
                    ) THEN 1 ELSE 0 
                END) as mensajes_pendientes,
                COUNT(DISTINCT c.id_alumno) as alumnos_unicos,
                AVG(TIMESTAMPDIFF(MINUTE, 
                    (SELECT MIN(m1.fecha_envio) 
                     FROM mensajes m1 
                     WHERE m1.id_conversacion = c.id_conversacion 
                     AND m1.remitente_tipo = 'alumno' 
                     AND m1.leido = TRUE),
                    (SELECT MIN(m2.fecha_envio) 
                     FROM mensajes m2 
                     WHERE m2.id_conversacion = c.id_conversacion 
                     AND m2.remitente_tipo = 'instructor' 
                     AND m2.fecha_envio > (
                         SELECT MAX(m3.fecha_envio) 
                         FROM mensajes m3 
                         WHERE m3.id_conversacion = c.id_conversacion 
                         AND m3.remitente_tipo = 'alumno'
                     ))
                )) as tiempo_respuesta_promedio_min
            FROM conversaciones c
            WHERE c.id_instructor = ?
              AND c.estado = 'activa'
              AND c.tipo_conversacion = 'instructor'
        ";
        
        $result = $this->db->fetchOne($query, [$idInstructor]);
        
        // Formatear tiempo de respuesta
        if ($result['tiempo_respuesta_promedio_min']) {
            $minutos = round($result['tiempo_respuesta_promedio_min']);
            if ($minutos < 60) {
                $result['tiempo_respuesta_promedio'] = "$minutos minutos";
            } else {
                $horas = floor($minutos / 60);
                $mins = $minutos % 60;
                $result['tiempo_respuesta_promedio'] = "{$horas}h {$mins}min";
            }
        } else {
            $result['tiempo_respuesta_promedio'] = 'N/A';
        }
        
        // Total mensajes del mes
        $queryMes = "
            SELECT COUNT(*) as total_mensajes_mes
            FROM mensajes m
            INNER JOIN conversaciones c ON m.id_conversacion = c.id_conversacion
            WHERE c.id_instructor = ?
              AND m.remitente_tipo = 'instructor'
              AND MONTH(m.fecha_envio) = MONTH(CURRENT_DATE)
              AND YEAR(m.fecha_envio) = YEAR(CURRENT_DATE)
        ";
        
        $resMes = $this->db->fetchOne($queryMes, [$idInstructor]);
        $result['total_mensajes_mes'] = $resMes['total_mensajes_mes'] ?? 0;
        
        return $result;
    }
    
    /**
     * Verificar si alumno está inscrito en el curso
     * 
     * @param int $idAlumno
     * @param int $idCurso
     * @return bool
     */
    public function verificarInscripcion($idAlumno, $idCurso) {
        $query = "
            SELECT COUNT(*) as count
            FROM inscripciones
            WHERE id_usuario = ?
              AND id_curso = ?
        ";
        
        $result = $this->db->fetchOne($query, [$idAlumno, $idCurso]);
        return $result['count'] > 0;
    }
    
    /**
     * Obtener instructor de un curso
     * 
     * @param int $idCurso
     * @return int|null
     */
    public function getInstructorCurso($idCurso) {
        $query = "
            SELECT id_instructor
            FROM cursos
            WHERE id_curso = ?
        ";
        
        $result = $this->db->fetchOne($query, [$idCurso]);
        return $result ? $result['id_instructor'] : null;
    }
    
    /**
     * Limpiar conversaciones antiguas archivadas
     * 
     * @param int $diasAntiguedad
     * @return int Número de conversaciones eliminadas
     */
    public function limpiarAntiguasArchivadas($diasAntiguedad = 365) {
        $query = "
            DELETE FROM conversaciones
            WHERE estado = 'archivada'
              AND ultimo_mensaje_fecha < DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        
        return $this->db->execute($query, [$diasAntiguedad]);
    }
}
