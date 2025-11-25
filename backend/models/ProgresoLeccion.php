<?php
/**
 * ============================================================================
 * MODELO: PROGRESO_LECCION
 * ============================================================================
 * Gestiona el progreso individual de lecciones por usuario
 * Fase 2A - Sistema de Cursos Básico
 * ============================================================================
 */

class ProgresoLeccion {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Marcar una lección como completada
     */
    public function markAsComplete($userId, $leccionId, $tiempoDedicado = 0) {
        try {
            // Obtener información de la lección
            $leccionModel = new Leccion();
            $leccion = $leccionModel->findById($leccionId);
            
            if (!$leccion) {
                return ['success' => false, 'message' => 'Lección no encontrada'];
            }
            
            // Verificar inscripción
            $inscripcionModel = new Inscripcion();
            $inscripcion = $inscripcionModel->getEnrollment($userId, $leccion['id_curso']);
            
            if (!$inscripcion) {
                return ['success' => false, 'message' => 'Usuario no inscrito en este curso'];
            }
            
            // Marcar fecha de inicio del curso si es la primera lección
            if (!$inscripcion['fecha_inicio']) {
                $inscripcionModel->markStarted($userId, $leccion['id_curso']);
            }
            
            // Verificar si ya existe progreso para esta lección
            $progresoExistente = $this->getProgress($userId, $leccionId);
            
            if ($progresoExistente) {
                // Actualizar progreso existente
                $query = "UPDATE progreso_lecciones SET 
                    completada = TRUE,
                    tiempo_dedicado = tiempo_dedicado + ?,
                    fecha_completado = NOW()
                    WHERE id_progreso = ?";
                
                $this->db->query($query, [$tiempoDedicado, $progresoExistente['id_progreso']]);
            } else {
                // Crear nuevo registro de progreso
                $query = "INSERT INTO progreso_lecciones 
                    (id_inscripcion, id_leccion, completada, tiempo_dedicado, fecha_completado)
                    VALUES (?, ?, TRUE, ?, NOW())";
                
                $this->db->query($query, [
                    $inscripcion['id_inscripcion'],
                    $leccionId,
                    $tiempoDedicado
                ]);
            }
            
            // Actualizar tiempo dedicado en la inscripción
            if ($tiempoDedicado > 0) {
                $inscripcionModel->updateTimeSpent($userId, $leccion['id_curso'], $tiempoDedicado);
            }
            
            // Actualizar progreso general del curso
            $progresoGeneral = $inscripcionModel->updateProgress($userId, $leccion['id_curso']);
            
            Logger::activity("Lección completada: $leccionId", $userId);
            
            return [
                'success' => true,
                'message' => 'Lección marcada como completada',
                'progreso_curso' => $progresoGeneral
            ];
        } catch (Exception $e) {
            Logger::error("Error al marcar lección como completada: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Marcar una lección como incompleta
     */
    public function markAsIncomplete($userId, $leccionId) {
        try {
            $leccionModel = new Leccion();
            $leccion = $leccionModel->findById($leccionId);
            
            if (!$leccion) {
                return ['success' => false, 'message' => 'Lección no encontrada'];
            }
            
            $inscripcionModel = new Inscripcion();
            $inscripcion = $inscripcionModel->getEnrollment($userId, $leccion['id_curso']);
            
            if (!$inscripcion) {
                return ['success' => false, 'message' => 'Usuario no inscrito en este curso'];
            }
            
            $query = "UPDATE progreso_lecciones SET 
                completada = FALSE,
                fecha_completado = NULL
                WHERE id_inscripcion = ? AND id_leccion = ?";
            
            $this->db->query($query, [$inscripcion['id_inscripcion'], $leccionId]);
            
            // Actualizar progreso general del curso
            $progresoGeneral = $inscripcionModel->updateProgress($userId, $leccion['id_curso']);
            
            return [
                'success' => true,
                'message' => 'Lección marcada como incompleta',
                'progreso_curso' => $progresoGeneral
            ];
        } catch (Exception $e) {
            Logger::error("Error al marcar lección como incompleta: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener progreso de una lección específica
     */
    public function getProgress($userId, $leccionId) {
        $query = "SELECT pl.* FROM progreso_lecciones pl
            INNER JOIN inscripciones i ON pl.id_inscripcion = i.id_inscripcion
            WHERE i.id_usuario = ? AND pl.id_leccion = ?";
        
        return $this->db->fetchOne($query, [$userId, $leccionId]);
    }
    
    /**
     * Calcular progreso de un curso completo
     */
    public function calculateCourseProgress($userId, $cursoId) {
        $query = "SELECT 
            COUNT(l.id_leccion) as total_lecciones,
            SUM(CASE WHEN pl.completada = 1 THEN 1 ELSE 0 END) as lecciones_completadas,
            SUM(l.duracion_minutos) as duracion_total,
            SUM(CASE WHEN pl.completada = 1 THEN l.duracion_minutos ELSE 0 END) as duracion_completada,
            SUM(COALESCE(pl.tiempo_dedicado, 0)) as tiempo_total_dedicado
        FROM lecciones l
        INNER JOIN modulos m ON l.id_modulo = m.id_modulo
        LEFT JOIN progreso_lecciones pl ON l.id_leccion = pl.id_leccion
        LEFT JOIN inscripciones i ON pl.id_inscripcion = i.id_inscripcion AND i.id_usuario = ?
        WHERE m.id_curso = ?";
        
        $stats = $this->db->fetchOne($query, [$userId, $cursoId]);
        
        $totalLecciones = $stats['total_lecciones'];
        $leccionesCompletadas = $stats['lecciones_completadas'] ?? 0;
        
        return [
            'total_lecciones' => (int) $totalLecciones,
            'lecciones_completadas' => (int) $leccionesCompletadas,
            'porcentaje_avance' => $totalLecciones > 0 ? round(($leccionesCompletadas / $totalLecciones) * 100, 2) : 0,
            'duracion_total_minutos' => (int) ($stats['duracion_total'] ?? 0),
            'duracion_completada_minutos' => (int) ($stats['duracion_completada'] ?? 0),
            'tiempo_dedicado_minutos' => (int) ($stats['tiempo_total_dedicado'] ?? 0)
        ];
    }
    
    /**
     * Obtener lecciones completadas de un curso
     */
    public function getCompletedLessons($userId, $cursoId) {
        $query = "SELECT 
            l.*,
            m.titulo as modulo_titulo,
            pl.fecha_completado,
            pl.tiempo_dedicado
        FROM progreso_lecciones pl
        INNER JOIN inscripciones i ON pl.id_inscripcion = i.id_inscripcion
        INNER JOIN lecciones l ON pl.id_leccion = l.id_leccion
        INNER JOIN modulos m ON l.id_modulo = m.id_modulo
        WHERE i.id_usuario = ? AND m.id_curso = ? AND pl.completada = TRUE
        ORDER BY pl.fecha_completado DESC";
        
        return $this->db->fetchAll($query, [$userId, $cursoId]);
    }
    
    /**
     * Obtener lecciones pendientes de un curso
     */
    public function getPendingLessons($userId, $cursoId) {
        $query = "SELECT 
            l.*,
            m.titulo as modulo_titulo,
            m.orden as modulo_orden
        FROM lecciones l
        INNER JOIN modulos m ON l.id_modulo = m.id_modulo
        LEFT JOIN progreso_lecciones pl ON l.id_leccion = pl.id_leccion
        LEFT JOIN inscripciones i ON pl.id_inscripcion = i.id_inscripcion AND i.id_usuario = ?
        WHERE m.id_curso = ? AND (pl.completada IS NULL OR pl.completada = FALSE)
        ORDER BY m.orden ASC, l.orden ASC";
        
        return $this->db->fetchAll($query, [$userId, $cursoId]);
    }
    
    /**
     * Obtener siguiente lección pendiente
     */
    public function getNextPendingLesson($userId, $cursoId) {
        $query = "SELECT 
            l.*,
            m.titulo as modulo_titulo,
            m.id_curso
        FROM lecciones l
        INNER JOIN modulos m ON l.id_modulo = m.id_modulo
        LEFT JOIN progreso_lecciones pl ON l.id_leccion = pl.id_leccion
        LEFT JOIN inscripciones i ON pl.id_inscripcion = i.id_inscripcion AND i.id_usuario = ?
        WHERE m.id_curso = ? AND (pl.completada IS NULL OR pl.completada = FALSE)
        ORDER BY m.orden ASC, l.orden ASC
        LIMIT 1";
        
        return $this->db->fetchOne($query, [$userId, $cursoId]);
    }
    
    /**
     * Registrar tiempo dedicado sin completar la lección
     */
    public function recordTimeSpent($userId, $leccionId, $minutesSpent) {
        try {
            $leccionModel = new Leccion();
            $leccion = $leccionModel->findById($leccionId);
            
            if (!$leccion) {
                return false;
            }
            
            $inscripcionModel = new Inscripcion();
            $inscripcion = $inscripcionModel->getEnrollment($userId, $leccion['id_curso']);
            
            if (!$inscripcion) {
                return false;
            }
            
            // Verificar si existe progreso
            $progresoExistente = $this->getProgress($userId, $leccionId);
            
            if ($progresoExistente) {
                $query = "UPDATE progreso_lecciones SET 
                    tiempo_dedicado = tiempo_dedicado + ?
                    WHERE id_progreso = ?";
                
                $this->db->query($query, [$minutesSpent, $progresoExistente['id_progreso']]);
            } else {
                $query = "INSERT INTO progreso_lecciones 
                    (id_inscripcion, id_leccion, completada, tiempo_dedicado)
                    VALUES (?, ?, FALSE, ?)";
                
                $this->db->query($query, [
                    $inscripcion['id_inscripcion'],
                    $leccionId,
                    $minutesSpent
                ]);
            }
            
            // Actualizar tiempo en inscripción
            $inscripcionModel->updateTimeSpent($userId, $leccion['id_curso'], $minutesSpent);
            
            return true;
        } catch (Exception $e) {
            Logger::error("Error al registrar tiempo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener progreso detallado por módulos
     */
    public function getProgressByModules($userId, $cursoId) {
        $query = "SELECT 
            m.id_modulo,
            m.titulo as modulo_titulo,
            m.orden as modulo_orden,
            COUNT(l.id_leccion) as total_lecciones,
            SUM(CASE WHEN pl.completada = 1 THEN 1 ELSE 0 END) as lecciones_completadas,
            ROUND(
                (SUM(CASE WHEN pl.completada = 1 THEN 1 ELSE 0 END) / COUNT(l.id_leccion)) * 100,
                2
            ) as porcentaje_modulo
        FROM modulos m
        INNER JOIN lecciones l ON m.id_modulo = l.id_modulo
        LEFT JOIN progreso_lecciones pl ON l.id_leccion = pl.id_leccion
        LEFT JOIN inscripciones i ON pl.id_inscripcion = i.id_inscripcion AND i.id_usuario = ?
        WHERE m.id_curso = ?
        GROUP BY m.id_modulo, m.titulo, m.orden
        ORDER BY m.orden ASC";
        
        return $this->db->fetchAll($query, [$userId, $cursoId]);
    }
    
    /**
     * Resetear progreso de un curso (útil para reiniciar curso)
     */
    public function resetCourseProgress($userId, $cursoId) {
        try {
            $inscripcionModel = new Inscripcion();
            $inscripcion = $inscripcionModel->getEnrollment($userId, $cursoId);
            
            if (!$inscripcion) {
                return ['success' => false, 'message' => 'Inscripción no encontrada'];
            }
            
            $this->db->beginTransaction();
            
            // Eliminar progreso de lecciones
            $query = "DELETE FROM progreso_lecciones WHERE id_inscripcion = ?";
            $this->db->query($query, [$inscripcion['id_inscripcion']]);
            
            // Resetear inscripción
            $query = "UPDATE inscripciones SET 
                porcentaje_avance = 0,
                lecciones_completadas = 0,
                tiempo_dedicado = 0,
                fecha_inicio = NULL,
                fecha_finalizacion = NULL,
                certificado_generado = FALSE,
                fecha_certificado = NULL
                WHERE id_inscripcion = ?";
            
            $this->db->query($query, [$inscripcion['id_inscripcion']]);
            
            $this->db->commit();
            
            Logger::activity("Progreso del curso $cursoId reseteado", $userId);
            
            return ['success' => true, 'message' => 'Progreso reseteado exitosamente'];
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error("Error al resetear progreso: " . $e->getMessage());
            throw $e;
        }
    }
}
