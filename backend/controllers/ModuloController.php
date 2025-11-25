<?php
/**
 * ============================================================================
 * CONTROLADOR: MÓDULOS
 * ============================================================================
 * Maneja operaciones relacionadas con módulos de cursos
 * Fase 2A - Sistema de Cursos Básico
 * ============================================================================
 */

class ModuloController {
    
    private $moduloModel;
    private $cursoModel;
    
    public function __construct() {
        $this->moduloModel = new Modulo();
        $this->cursoModel = new Curso();
    }
    
    /**
     * Obtener módulos de un curso
     * GET /courses/{id}/modules
     */
    public function getModulesByCourse($cursoId) {
        try {
            $curso = $this->cursoModel->findById($cursoId, false);
            if (!$curso) {
                Response::notFound('Curso no encontrado');
                return;
            }
            
            $includeLessons = isset($_GET['with_lessons']) && $_GET['with_lessons'] === 'true';
            $modulos = $this->moduloModel->findByCourse($cursoId, $includeLessons);
            
            Response::success('Módulos obtenidos exitosamente', $modulos);
        } catch (Exception $e) {
            Logger::error('Error al obtener módulos: ' . $e->getMessage());
            Response::serverError('Error al obtener módulos');
        }
    }
    
    /**
     * Obtener un módulo específico
     * GET /modules/{id}
     */
    public function getModuleById($id) {
        try {
            $includeLessons = isset($_GET['with_lessons']) && $_GET['with_lessons'] === 'true';
            $modulo = $this->moduloModel->findById($id, $includeLessons);
            
            if (!$modulo) {
                Response::notFound('Módulo no encontrado');
                return;
            }
            
            Response::success('Módulo obtenido exitosamente', $modulo);
        } catch (Exception $e) {
            Logger::error('Error al obtener módulo: ' . $e->getMessage());
            Response::serverError('Error al obtener módulo');
        }
    }
    
    /**
     * Crear un nuevo módulo
     * POST /courses/{id}/modules
     * Requiere autenticación: instructor (propietario) o admin
     */
    public function createModule($cursoId) {
        try {
            $user = AuthMiddleware::requireAuth(['instructor', 'admin']);
            
            $curso = $this->cursoModel->findById($cursoId, false);
            if (!$curso) {
                Response::notFound('Curso no encontrado');
                return;
            }
            
            // Verificar permisos
            if ($user['rol'] !== 'admin' && $curso['id_instructor'] != $user['id_usuario']) {
                Response::forbidden('No tienes permiso para agregar módulos a este curso');
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
            
            $data['id_curso'] = $cursoId;
            $data['user_id'] = $user['id_usuario'];
            
            $moduloId = $this->moduloModel->create($data);
            
            if ($moduloId) {
                $modulo = $this->moduloModel->findById($moduloId);
                Response::success('Módulo creado exitosamente', $modulo, 201);
            } else {
                Response::serverError('Error al crear módulo');
            }
        } catch (Exception $e) {
            Logger::error('Error al crear módulo: ' . $e->getMessage());
            Response::serverError('Error al crear módulo');
        }
    }
    
    /**
     * Actualizar un módulo
     * PUT /modules/{id}
     * Requiere autenticación: instructor (propietario) o admin
     */
    public function updateModule($id) {
        try {
            $user = AuthMiddleware::requireAuth(['instructor', 'admin']);
            
            $modulo = $this->moduloModel->findById($id, false);
            if (!$modulo) {
                Response::notFound('Módulo no encontrado');
                return;
            }
            
            $curso = $this->cursoModel->findById($modulo['id_curso'], false);
            
            // Verificar permisos
            if ($user['rol'] !== 'admin' && $curso['id_instructor'] != $user['id_usuario']) {
                Response::forbidden('No tienes permiso para editar este módulo');
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
            
            $result = $this->moduloModel->update($id, $data);
            
            if ($result) {
                $moduloActualizado = $this->moduloModel->findById($id);
                Response::success('Módulo actualizado exitosamente', $moduloActualizado);
            } else {
                Response::serverError('Error al actualizar módulo');
            }
        } catch (Exception $e) {
            Logger::error('Error al actualizar módulo: ' . $e->getMessage());
            Response::serverError('Error al actualizar módulo');
        }
    }
    
    /**
     * Eliminar un módulo
     * DELETE /modules/{id}
     * Requiere autenticación: instructor (propietario) o admin
     */
    public function deleteModule($id) {
        try {
            $user = AuthMiddleware::requireAuth(['instructor', 'admin']);
            
            $modulo = $this->moduloModel->findById($id, false);
            if (!$modulo) {
                Response::notFound('Módulo no encontrado');
                return;
            }
            
            $curso = $this->cursoModel->findById($modulo['id_curso'], false);
            
            // Verificar permisos
            if ($user['rol'] !== 'admin' && $curso['id_instructor'] != $user['id_usuario']) {
                Response::forbidden('No tienes permiso para eliminar este módulo');
                return;
            }
            
            $result = $this->moduloModel->delete($id);
            
            if ($result) {
                Response::success('Módulo eliminado exitosamente');
            } else {
                Response::serverError('Error al eliminar módulo');
            }
        } catch (Exception $e) {
            Logger::error('Error al eliminar módulo: ' . $e->getMessage());
            Response::serverError('Error al eliminar módulo');
        }
    }
    
    /**
     * Reordenar módulos de un curso
     * PUT /courses/{id}/modules/reorder
     * Requiere autenticación: instructor (propietario) o admin
     */
    public function reorderModules($cursoId) {
        try {
            $user = AuthMiddleware::requireAuth(['instructor', 'admin']);
            
            $curso = $this->cursoModel->findById($cursoId, false);
            if (!$curso) {
                Response::notFound('Curso no encontrado');
                return;
            }
            
            // Verificar permisos
            if ($user['rol'] !== 'admin' && $curso['id_instructor'] != $user['id_usuario']) {
                Response::forbidden('No tienes permiso para reordenar módulos en este curso');
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['modulos']) || !is_array($data['modulos'])) {
                Response::badRequest('Se requiere un array de módulos con su nuevo orden');
                return;
            }
            
            $result = $this->moduloModel->reorder($cursoId, $data['modulos']);
            
            if ($result) {
                Response::success('Módulos reordenados exitosamente');
            } else {
                Response::serverError('Error al reordenar módulos');
            }
        } catch (Exception $e) {
            Logger::error('Error al reordenar módulos: ' . $e->getMessage());
            Response::serverError('Error al reordenar módulos');
        }
    }
}
