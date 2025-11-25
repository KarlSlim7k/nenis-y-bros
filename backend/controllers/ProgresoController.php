<?php
/**
 * ============================================================================
 * CONTROLADOR: PROGRESO
 * ============================================================================
 * Maneja el progreso y seguimiento de cursos
 * Fase 2A - Sistema de Cursos Básico
 * ============================================================================
 */

class ProgresoController {
    
    private $progresoModel;
    private $inscripcionModel;
    private $leccionModel;
    
    public function __construct() {
        $this->progresoModel = new ProgresoLeccion();
        $this->inscripcionModel = new Inscripcion();
        $this->leccionModel = new Leccion();
    }
    
    /**
     * Marcar lección como completada
     * POST /lessons/{id}/complete
     * Requiere autenticación
     */
    public function completeLesson($id) {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $leccion = $this->leccionModel->findById($id);
            if (!$leccion) {
                Response::notFound('Lección no encontrada');
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $tiempoDedicado = isset($data['tiempo_dedicado']) ? (int)$data['tiempo_dedicado'] : 0;
            
            $result = $this->progresoModel->markAsComplete($user['id_usuario'], $id, $tiempoDedicado);
            
            if ($result['success']) {
                Response::success($result['message'], $result['progreso_curso']);
            } else {
                Response::badRequest($result['message']);
            }
        } catch (Exception $e) {
            Logger::error('Error al completar lección: ' . $e->getMessage());
            Response::serverError('Error al completar lección');
        }
    }
    
    /**
     * Marcar lección como incompleta
     * DELETE /lessons/{id}/complete
     * Requiere autenticación
     */
    public function uncompleteLesson($id) {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $result = $this->progresoModel->markAsIncomplete($user['id_usuario'], $id);
            
            if ($result['success']) {
                Response::success($result['message'], $result['progreso_curso']);
            } else {
                Response::badRequest($result['message']);
            }
        } catch (Exception $e) {
            Logger::error('Error al marcar lección como incompleta: ' . $e->getMessage());
            Response::serverError('Error al marcar lección como incompleta');
        }
    }
    
    /**
     * Obtener progreso de un curso
     * GET /courses/{id}/progress
     * Requiere autenticación
     */
    public function getCourseProgress($cursoId) {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $progreso = $this->progresoModel->calculateCourseProgress($user['id_usuario'], $cursoId);
            $progresoModulos = $this->progresoModel->getProgressByModules($user['id_usuario'], $cursoId);
            
            $result = [
                'general' => $progreso,
                'modulos' => $progresoModulos
            ];
            
            Response::success('Progreso obtenido exitosamente', $result);
        } catch (Exception $e) {
            Logger::error('Error al obtener progreso: ' . $e->getMessage());
            Response::serverError('Error al obtener progreso');
        }
    }
    
    /**
     * Obtener lecciones completadas de un curso
     * GET /courses/{id}/completed-lessons
     * Requiere autenticación
     */
    public function getCompletedLessons($cursoId) {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $lecciones = $this->progresoModel->getCompletedLessons($user['id_usuario'], $cursoId);
            
            Response::success('Lecciones completadas obtenidas exitosamente', $lecciones);
        } catch (Exception $e) {
            Logger::error('Error al obtener lecciones completadas: ' . $e->getMessage());
            Response::serverError('Error al obtener lecciones completadas');
        }
    }
    
    /**
     * Obtener lecciones pendientes de un curso
     * GET /courses/{id}/pending-lessons
     * Requiere autenticación
     */
    public function getPendingLessons($cursoId) {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $lecciones = $this->progresoModel->getPendingLessons($user['id_usuario'], $cursoId);
            
            Response::success('Lecciones pendientes obtenidas exitosamente', $lecciones);
        } catch (Exception $e) {
            Logger::error('Error al obtener lecciones pendientes: ' . $e->getMessage());
            Response::serverError('Error al obtener lecciones pendientes');
        }
    }
    
    /**
     * Obtener siguiente lección pendiente
     * GET /courses/{id}/next-lesson
     * Requiere autenticación
     */
    public function getNextLesson($cursoId) {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $leccion = $this->progresoModel->getNextPendingLesson($user['id_usuario'], $cursoId);
            
            if ($leccion) {
                Response::success('Siguiente lección obtenida exitosamente', $leccion);
            } else {
                Response::success('No hay más lecciones pendientes', null);
            }
        } catch (Exception $e) {
            Logger::error('Error al obtener siguiente lección: ' . $e->getMessage());
            Response::serverError('Error al obtener siguiente lección');
        }
    }
    
    /**
     * Registrar tiempo dedicado a una lección
     * POST /lessons/{id}/time
     * Requiere autenticación
     */
    public function recordTime($id) {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['tiempo_dedicado']) || $data['tiempo_dedicado'] <= 0) {
                Response::badRequest('Se requiere tiempo dedicado válido');
                return;
            }
            
            $result = $this->progresoModel->recordTimeSpent(
                $user['id_usuario'], 
                $id, 
                (int)$data['tiempo_dedicado']
            );
            
            if ($result) {
                Response::success('Tiempo registrado exitosamente');
            } else {
                Response::badRequest('No se pudo registrar el tiempo');
            }
        } catch (Exception $e) {
            Logger::error('Error al registrar tiempo: ' . $e->getMessage());
            Response::serverError('Error al registrar tiempo');
        }
    }
    
    /**
     * Generar certificado de curso
     * POST /courses/{id}/certificate
     * Requiere autenticación
     */
    public function generateCertificate($cursoId) {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $result = $this->inscripcionModel->generateCertificate($user['id_usuario'], $cursoId);
            
            if ($result['success']) {
                Response::success($result['message'], [
                    'fecha_certificado' => $result['fecha_certificado']
                ]);
            } else {
                Response::badRequest($result['message']);
            }
        } catch (Exception $e) {
            Logger::error('Error al generar certificado: ' . $e->getMessage());
            Response::serverError('Error al generar certificado');
        }
    }
    
    /**
     * Resetear progreso de un curso
     * POST /courses/{id}/reset-progress
     * Requiere autenticación
     */
    public function resetProgress($cursoId) {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $result = $this->progresoModel->resetCourseProgress($user['id_usuario'], $cursoId);
            
            if ($result['success']) {
                Response::success($result['message']);
            } else {
                Response::badRequest($result['message']);
            }
        } catch (Exception $e) {
            Logger::error('Error al resetear progreso: ' . $e->getMessage());
            Response::serverError('Error al resetear progreso');
        }
    }
    
    /**
     * Obtener estadísticas del usuario
     * GET /my-stats
     * Requiere autenticación
     */
    public function getMyStats() {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $stats = $this->inscripcionModel->getUserStats($user['id_usuario']);
            
            Response::success('Estadísticas obtenidas exitosamente', $stats);
        } catch (Exception $e) {
            Logger::error('Error al obtener estadísticas: ' . $e->getMessage());
            Response::serverError('Error al obtener estadísticas');
        }
    }
}
