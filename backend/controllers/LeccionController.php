<?php
/**
 * ============================================================================
 * CONTROLADOR: LECCIONES
 * ============================================================================
 * Maneja operaciones relacionadas con lecciones
 * Fase 2A - Sistema de Cursos Básico
 * ============================================================================
 */

class LeccionController {
    
    private $leccionModel;
    private $moduloModel;
    private $cursoModel;
    
    public function __construct() {
        $this->leccionModel = new Leccion();
        $this->moduloModel = new Modulo();
        $this->cursoModel = new Curso();
    }
    
    /**
     * Obtener lecciones de un módulo
     * GET /modules/{id}/lessons
     */
    public function getLessonsByModule($moduloId) {
        try {
            $modulo = $this->moduloModel->findById($moduloId, false);
            if (!$modulo) {
                Response::notFound('Módulo no encontrado');
                return;
            }
            
            $user = AuthMiddleware::getCurrentUser();
            $includeProgress = $user !== null;
            
            $lecciones = $this->leccionModel->findByModule(
                $moduloId, 
                $includeProgress, 
                $user ? $user['id_usuario'] : null
            );
            
            Response::success('Lecciones obtenidas exitosamente', $lecciones);
        } catch (Exception $e) {
            Logger::error('Error al obtener lecciones: ' . $e->getMessage());
            Response::serverError('Error al obtener lecciones');
        }
    }
    
    /**
     * Obtener una lección específica
     * GET /lessons/{id}
     */
    public function getLessonById($id) {
        try {
            $user = AuthMiddleware::getCurrentUser();
            $includeProgress = $user !== null;
            
            $leccion = $this->leccionModel->findById(
                $id, 
                $includeProgress, 
                $user ? $user['id_usuario'] : null
            );
            
            if (!$leccion) {
                Response::notFound('Lección no encontrada');
                return;
            }
            
            // Obtener lección siguiente y anterior
            $leccion['siguiente'] = $this->leccionModel->getNext($id);
            $leccion['anterior'] = $this->leccionModel->getPrevious($id);
            
            Response::success('Lección obtenida exitosamente', $leccion);
        } catch (Exception $e) {
            Logger::error('Error al obtener lección: ' . $e->getMessage());
            Response::serverError('Error al obtener lección');
        }
    }
    
    /**
     * Crear una nueva lección
     * POST /modules/{id}/lessons
     * Requiere autenticación: instructor (propietario) o admin
     */
    public function createLesson($moduloId) {
        try {
            $user = AuthMiddleware::requireAuth(['instructor', 'admin']);
            
            $modulo = $this->moduloModel->findById($moduloId, false);
            if (!$modulo) {
                Response::notFound('Módulo no encontrado');
                return;
            }
            
            $curso = $this->cursoModel->findById($modulo['id_curso'], false);
            
            // Verificar permisos
            if ($user['rol'] !== 'admin' && $curso['id_instructor'] != $user['id_usuario']) {
                Response::forbidden('No tienes permiso para agregar lecciones a este módulo');
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validar datos
            $validator = new Validator($data, [
                'titulo' => 'required|min:3|max:200'
            ]);
            
            if (!$validator->validate()) {
                Response::validationError($validator->getErrors());
                return;
            }
            
            $data['id_modulo'] = $moduloId;
            $data['user_id'] = $user['id_usuario'];
            
            $leccionId = $this->leccionModel->create($data);
            
            if ($leccionId) {
                $leccion = $this->leccionModel->findById($leccionId);
                Response::success('Lección creada exitosamente', $leccion, 201);
            } else {
                Response::serverError('Error al crear lección');
            }
        } catch (Exception $e) {
            Logger::error('Error al crear lección: ' . $e->getMessage());
            Response::serverError('Error al crear lección');
        }
    }
    
    /**
     * Actualizar una lección
     * PUT /lessons/{id}
     * Requiere autenticación: instructor (propietario) o admin
     */
    public function updateLesson($id) {
        try {
            $user = AuthMiddleware::requireAuth(['instructor', 'admin']);
            
            $leccion = $this->leccionModel->findById($id);
            if (!$leccion) {
                Response::notFound('Lección no encontrada');
                return;
            }
            
            $curso = $this->cursoModel->findById($leccion['id_curso'], false);
            
            // Verificar permisos
            if ($user['rol'] !== 'admin' && $curso['id_instructor'] != $user['id_usuario']) {
                Response::forbidden('No tienes permiso para editar esta lección');
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $data['user_id'] = $user['id_usuario'];
            
            // Validar título si se proporciona
            if (isset($data['titulo'])) {
                $validator = new Validator($data, [
                    'titulo' => 'min:3|max:200'
                ]);
                
                if (!$validator->validate()) {
                    Response::validationError($validator->getErrors());
                    return;
                }
            }
            
            $result = $this->leccionModel->update($id, $data);
            
            if ($result) {
                $leccionActualizada = $this->leccionModel->findById($id);
                Response::success('Lección actualizada exitosamente', $leccionActualizada);
            } else {
                Response::serverError('Error al actualizar lección');
            }
        } catch (Exception $e) {
            Logger::error('Error al actualizar lección: ' . $e->getMessage());
            Response::serverError('Error al actualizar lección');
        }
    }
    
    /**
     * Eliminar una lección
     * DELETE /lessons/{id}
     * Requiere autenticación: instructor (propietario) o admin
     */
    public function deleteLesson($id) {
        try {
            $user = AuthMiddleware::requireAuth(['instructor', 'admin']);
            
            $leccion = $this->leccionModel->findById($id);
            if (!$leccion) {
                Response::notFound('Lección no encontrada');
                return;
            }
            
            $curso = $this->cursoModel->findById($leccion['id_curso'], false);
            
            // Verificar permisos
            if ($user['rol'] !== 'admin' && $curso['id_instructor'] != $user['id_usuario']) {
                Response::forbidden('No tienes permiso para eliminar esta lección');
                return;
            }
            
            $result = $this->leccionModel->delete($id);
            
            if ($result) {
                Response::success('Lección eliminada exitosamente');
            } else {
                Response::serverError('Error al eliminar lección');
            }
        } catch (Exception $e) {
            Logger::error('Error al eliminar lección: ' . $e->getMessage());
            Response::serverError('Error al eliminar lección');
        }
    }
    
    /**
     * Reordenar lecciones de un módulo
     * PUT /modules/{id}/lessons/reorder
     * Requiere autenticación: instructor (propietario) o admin
     */
    public function reorderLessons($moduloId) {
        try {
            $user = AuthMiddleware::requireAuth(['instructor', 'admin']);
            
            $modulo = $this->moduloModel->findById($moduloId, false);
            if (!$modulo) {
                Response::notFound('Módulo no encontrado');
                return;
            }
            
            $curso = $this->cursoModel->findById($modulo['id_curso'], false);
            
            // Verificar permisos
            if ($user['rol'] !== 'admin' && $curso['id_instructor'] != $user['id_usuario']) {
                Response::forbidden('No tienes permiso para reordenar lecciones en este módulo');
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['lecciones']) || !is_array($data['lecciones'])) {
                Response::badRequest('Se requiere un array de lecciones con su nuevo orden');
                return;
            }
            
            $result = $this->leccionModel->reorder($moduloId, $data['lecciones']);
            
            if ($result) {
                Response::success('Lecciones reordenadas exitosamente');
            } else {
                Response::serverError('Error al reordenar lecciones');
            }
        } catch (Exception $e) {
            Logger::error('Error al reordenar lecciones: ' . $e->getMessage());
            Response::serverError('Error al reordenar lecciones');
        }
    }
}
