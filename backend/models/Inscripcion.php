<?php
/**
 * ============================================================================
 * MODELO: INSCRIPCION
 * ============================================================================
 * Gestiona las inscripciones de usuarios a cursos y su progreso
 * Fase 2A - Sistema de Cursos Básico
 * ============================================================================
 */

class Inscripcion {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Inscribir un usuario a un curso
     */
    public function enroll($userId, $cursoId) {
        // Verificar si ya está inscrito
        if ($this->isEnrolled($userId, $cursoId)) {
            return [
                'success' => false,
                'message' => 'El usuario ya está inscrito en este curso'
            ];
        }
        
        $query = "INSERT INTO inscripciones (id_usuario, id_curso, fecha_ultima_actividad) 
                  VALUES (?, ?, NOW())";
        
        try {
            $inscripcionId = $this->db->insert($query, [$userId, $cursoId]);
            if ($inscripcionId) {
                Logger::activity($userId, "Usuario inscrito en curso ID: $cursoId");
                
                return [
                    'success' => true,
                    'message' => 'Inscripción exitosa',
                    'inscripcion_id' => $inscripcionId
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Error al procesar la inscripción'
            ];
        } catch (Exception $e) {
            Logger::error("Error al inscribir usuario: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Desinscribir un usuario de un curso
     */
    public function unenroll($userId, $cursoId) {
        try {
            $query = "DELETE FROM inscripciones WHERE id_usuario = ? AND id_curso = ?";
            $result = $this->db->query($query, [$userId, $cursoId]);
            
            if ($result) {
                Logger::activity("Usuario desinscrito del curso ID: $cursoId", $userId);
                return [
                    'success' => true,
                    'message' => 'Desinscripción exitosa'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'No se pudo completar la desinscripción'
            ];
        } catch (Exception $e) {
            Logger::error("Error al desinscribir usuario: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener inscripción de un usuario en un curso
     */
    public function getEnrollment($userId, $cursoId) {
        $query = "SELECT 
            i.*,
            c.titulo as curso_titulo,
            c.slug as curso_slug,
            c.imagen_portada,
            c.nivel,
            cat.nombre as categoria_nombre,
            (SELECT COUNT(*) FROM lecciones l 
             INNER JOIN modulos m ON l.id_modulo = m.id_modulo 
             WHERE m.id_curso = c.id_curso) as total_lecciones
        FROM inscripciones i
        INNER JOIN cursos c ON i.id_curso = c.id_curso
        LEFT JOIN categorias_cursos cat ON c.id_categoria = cat.id_categoria
        WHERE i.id_usuario = ? AND i.id_curso = ?";
        
        return $this->db->fetchOne($query, [$userId, $cursoId]);
    }
    
    /**
     * Obtener todos los cursos de un usuario
     */
    public function getUserCourses($userId, $filters = [], $page = 1, $limit = 10) {
        $conditions = ["i.id_usuario = ?"];
        $params = [$userId];
        
        // Filtrar por estado de finalización
        if (isset($filters['completado'])) {
            if ($filters['completado']) {
                $conditions[] = "i.fecha_finalizacion IS NOT NULL";
            } else {
                $conditions[] = "i.fecha_finalizacion IS NULL";
            }
        }
        
        // Filtrar por categoría
        if (!empty($filters['id_categoria'])) {
            $conditions[] = "c.id_categoria = ?";
            $params[] = $filters['id_categoria'];
        }
        
        $where = implode(" AND ", $conditions);
        
        // Conteo total
        $countQuery = "SELECT COUNT(*) as total FROM inscripciones i
                       INNER JOIN cursos c ON i.id_curso = c.id_curso
                       WHERE $where";
        $totalResult = $this->db->fetchOne($countQuery, $params);
        $total = $totalResult['total'];
        
        // Query principal
        $offset = ($page - 1) * $limit;
        
        $orderBy = "i.fecha_ultima_actividad DESC";
        if (!empty($filters['order_by'])) {
            switch ($filters['order_by']) {
                case 'progress':
                    $orderBy = "i.porcentaje_avance DESC";
                    break;
                case 'recent':
                    $orderBy = "i.fecha_inscripcion DESC";
                    break;
                case 'title':
                    $orderBy = "c.titulo ASC";
                    break;
            }
        }
        
        $query = "SELECT 
            i.*,
            c.titulo as curso_titulo,
            c.slug as curso_slug,
            c.imagen_portada,
            c.nivel,
            c.duracion_estimada,
            cat.nombre as categoria_nombre,
            cat.color as categoria_color,
            u.nombre as instructor_nombre,
            u.apellido as instructor_apellido,
            (SELECT COUNT(*) FROM lecciones l 
             INNER JOIN modulos m ON l.id_modulo = m.id_modulo 
             WHERE m.id_curso = c.id_curso) as total_lecciones
        FROM inscripciones i
        INNER JOIN cursos c ON i.id_curso = c.id_curso
        LEFT JOIN categorias_cursos cat ON c.id_categoria = cat.id_categoria
        LEFT JOIN usuarios u ON c.id_instructor = u.id_usuario
        WHERE $where
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $cursos = $this->db->fetchAll($query, $params);
        
        return [
            'data' => $cursos,
            'pagination' => [
                'total' => (int) $total,
                'per_page' => (int) $limit,
                'current_page' => (int) $page,
                'last_page' => ceil($total / $limit),
                'from' => $offset + 1,
                'to' => min($offset + $limit, $total)
            ]
        ];
    }
    
    /**
     * Actualizar progreso de inscripción
     */
    public function updateProgress($userId, $cursoId) {
        try {
            // Obtener inscripción
            $inscripcion = $this->getEnrollment($userId, $cursoId);
            if (!$inscripcion) {
                return false;
            }
            
            // Calcular progreso
            $query = "SELECT 
                COUNT(*) as total_lecciones,
                SUM(CASE WHEN pl.completada = 1 THEN 1 ELSE 0 END) as lecciones_completadas
            FROM lecciones l
            INNER JOIN modulos m ON l.id_modulo = m.id_modulo
            LEFT JOIN progreso_lecciones pl ON l.id_leccion = pl.id_leccion 
                AND pl.id_inscripcion = ?
            WHERE m.id_curso = ?";
            
            $progreso = $this->db->fetchOne($query, [$inscripcion['id_inscripcion'], $cursoId]);
            
            $totalLecciones = $progreso['total_lecciones'];
            $leccionesCompletadas = $progreso['lecciones_completadas'] ?? 0;
            $porcentajeAvance = $totalLecciones > 0 ? ($leccionesCompletadas / $totalLecciones) * 100 : 0;
            
            // Verificar si el curso está completo
            $fechaFinalizacion = null;
            if ($porcentajeAvance >= 100) {
                $fechaFinalizacion = date('Y-m-d H:i:s');
            }
            
            // Actualizar inscripción
            $updateQuery = "UPDATE inscripciones SET 
                porcentaje_avance = ?,
                lecciones_completadas = ?,
                fecha_finalizacion = ?,
                fecha_ultima_actividad = NOW()
                WHERE id_inscripcion = ?";
            
            $this->db->query($updateQuery, [
                round($porcentajeAvance, 2),
                $leccionesCompletadas,
                $fechaFinalizacion,
                $inscripcion['id_inscripcion']
            ]);
            
            // Si se completó el curso, registrar log
            if ($fechaFinalizacion && !$inscripcion['fecha_finalizacion']) {
                Logger::activity("Curso completado: $cursoId", $userId);
            }
            
            return [
                'porcentaje_avance' => round($porcentajeAvance, 2),
                'lecciones_completadas' => $leccionesCompletadas,
                'total_lecciones' => $totalLecciones,
                'completado' => $porcentajeAvance >= 100
            ];
        } catch (Exception $e) {
            Logger::error("Error al actualizar progreso: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verificar si un usuario está inscrito en un curso
     */
    public function isEnrolled($userId, $cursoId) {
        $query = "SELECT COUNT(*) as count FROM inscripciones WHERE id_usuario = ? AND id_curso = ?";
        $result = $this->db->fetchOne($query, [$userId, $cursoId]);
        return $result['count'] > 0;
    }
    
    /**
     * Obtener estadísticas de inscripciones de un usuario
     */
    public function getUserStats($userId) {
        $query = "SELECT 
            COUNT(*) as total_cursos,
            SUM(CASE WHEN fecha_finalizacion IS NOT NULL THEN 1 ELSE 0 END) as cursos_completados,
            SUM(CASE WHEN fecha_finalizacion IS NULL THEN 1 ELSE 0 END) as cursos_en_progreso,
            ROUND(AVG(porcentaje_avance), 2) as promedio_avance,
            SUM(tiempo_dedicado) as tiempo_total_minutos
        FROM inscripciones
        WHERE id_usuario = ?";
        
        return $this->db->fetchOne($query, [$userId]);
    }
    
    /**
     * Actualizar tiempo dedicado
     */
    public function updateTimeSpent($userId, $cursoId, $minutesSpent) {
        $inscripcion = $this->getEnrollment($userId, $cursoId);
        if (!$inscripcion) {
            return false;
        }
        
        $query = "UPDATE inscripciones SET 
            tiempo_dedicado = tiempo_dedicado + ?,
            fecha_ultima_actividad = NOW()
            WHERE id_inscripcion = ?";
        
        return $this->db->query($query, [$minutesSpent, $inscripcion['id_inscripcion']]);
    }
    
    /**
     * Marcar fecha de inicio (primera lección vista)
     */
    public function markStarted($userId, $cursoId) {
        $inscripcion = $this->getEnrollment($userId, $cursoId);
        if (!$inscripcion || $inscripcion['fecha_inicio']) {
            return false;
        }
        
        $query = "UPDATE inscripciones SET fecha_inicio = NOW() WHERE id_inscripcion = ?";
        return $this->db->query($query, [$inscripcion['id_inscripcion']]);
    }
    
    /**
     * Generar certificado
     */
    public function generateCertificate($userId, $cursoId) {
        $inscripcion = $this->getEnrollment($userId, $cursoId);
        
        if (!$inscripcion) {
            return ['success' => false, 'message' => 'Inscripción no encontrada'];
        }
        
        if ($inscripcion['porcentaje_avance'] < 100) {
            return ['success' => false, 'message' => 'Curso no completado'];
        }
        
        if ($inscripcion['certificado_generado']) {
            return [
                'success' => true,
                'message' => 'Certificado ya generado',
                'fecha_certificado' => $inscripcion['fecha_certificado']
            ];
        }
        
        $query = "UPDATE inscripciones SET 
            certificado_generado = TRUE,
            fecha_certificado = NOW()
            WHERE id_inscripcion = ?";
        
        try {
            $this->db->query($query, [$inscripcion['id_inscripcion']]);
            Logger::activity("Certificado generado para curso ID: $cursoId", $userId);
            
            return [
                'success' => true,
                'message' => 'Certificado generado exitosamente',
                'fecha_certificado' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            Logger::error("Error al generar certificado: " . $e->getMessage());
            throw $e;
        }
    }
}
