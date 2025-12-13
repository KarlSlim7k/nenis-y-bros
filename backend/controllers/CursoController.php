<?php
/**
 * ============================================================================
 * CONTROLADOR: CURSOS
 * ============================================================================
 * Maneja todas las operaciones relacionadas con cursos
 * Fase 2A - Sistema de Cursos Básico
 * ============================================================================
 */

class CursoController {
    
    private $cursoModel;
    private $inscripcionModel;
    
    public function __construct() {
        $this->cursoModel = new Curso();
        $this->inscripcionModel = new Inscripcion();
    }
    
    /**
     * Obtener listado de cursos con filtros
     * GET /courses
     */
    public function getCourses() {
        try {
            $filters = [];
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            
            // Aplicar filtros
            if (isset($_GET['categoria'])) {
                $filters['id_categoria'] = (int)$_GET['categoria'];
            }
            
            if (isset($_GET['nivel'])) {
                $filters['nivel'] = $_GET['nivel'];
            }
            
            if (isset($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }
            
            if (isset($_GET['order_by'])) {
                $filters['order_by'] = $_GET['order_by'];
            }
            
            // Los usuarios normales solo ven cursos publicados
            // Los admins/mentores pueden ver todos los estados
            $user = AuthMiddleware::getCurrentUser();
            if (!$user || ($user['tipo_usuario'] !== 'administrador' && $user['tipo_usuario'] !== 'mentor')) {
                $filters['estado'] = 'publicado';
            } elseif (isset($_GET['estado'])) {
                $filters['estado'] = $_GET['estado'];
            } else {
                // Mentores y admins ven todos los estados - pasar null para no aplicar filtro
                $filters['sin_filtro_estado'] = true;
            }
            
            // Si es mentor, filtrar por sus cursos (a menos que sea admin o pase all=true)
            if ($user && $user['tipo_usuario'] === 'mentor' && !isset($_GET['all'])) {
                $filters['id_instructor'] = $user['id_usuario'];
            }
            
            $result = $this->cursoModel->findAll($filters, $page, $limit);
            
            Response::success('Cursos obtenidos exitosamente', $result);
        } catch (Exception $e) {
            Logger::error('Error al obtener cursos: ' . $e->getMessage());
            Response::serverError('Error al obtener cursos');
        }
    }
    
    /**
     * Obtener detalle de un curso
     * GET /courses/{id}
     */
    public function getCourseById($id) {
        try {
            $curso = $this->cursoModel->findById($id, true);
            
            if (!$curso) {
                Response::notFound('Curso no encontrado');
                return;
            }
            
            // Verificar permisos de visualización
            $user = AuthMiddleware::getCurrentUser();
            
            // Si el curso no está publicado, solo administrador/mentor pueden verlo
            if ($curso['estado'] !== 'publicado') {
                if (!$user) {
                    Response::unauthorized('Debes iniciar sesión para ver este curso');
                    return;
                }
                
                if ($user['tipo_usuario'] !== 'administrador' && $user['tipo_usuario'] !== 'mentor') {
                    Response::forbidden('No tienes permiso para ver este curso');
                    return;
                }
                
                if ($user['tipo_usuario'] === 'mentor' && $curso['id_instructor'] != $user['id_usuario']) {
                    Response::forbidden('No tienes permiso para ver este curso');
                    return;
                }
            }
            
            // Obtener módulos con lecciones
            $moduloModel = new Modulo();
            $curso['modulos'] = $moduloModel->findByCourse($id, true);
            
            // Si hay usuario autenticado, verificar inscripción y progreso
            if ($user) {
                $curso['inscrito'] = $this->inscripcionModel->isEnrolled($user['id_usuario'], $id);
                
                if ($curso['inscrito']) {
                    $inscripcion = $this->inscripcionModel->getEnrollment($user['id_usuario'], $id);
                    $curso['inscripcion'] = $inscripcion;
                }
            }
            
            Response::success('Curso obtenido exitosamente', $curso);
        } catch (Exception $e) {
            Logger::error('Error al obtener curso: ' . $e->getMessage());
            Response::serverError('Error al obtener curso');
        }
    }
    
    /**
     * Crear un nuevo curso
     * POST /courses
     * Requiere autenticación: instructor o admin
     */
    public function createCourse() {
        try {
            $user = AuthMiddleware::requireAuth(['mentor', 'administrador']);
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validar datos requeridos
            $validator = new Validator($data, [
                'titulo' => 'required|min:5|max:200',
                'id_categoria' => 'required'
            ]);
            
            if (!$validator->validate()) {
                Response::validationError($validator->getErrors());
                return;
            }
            
            // Asignar instructor actual si no se especifica
            if (!isset($data['id_instructor'])) {
                $data['id_instructor'] = $user['id_usuario'];
            }
            
            // Solo administrador puede asignar otro instructor
            if ($user['tipo_usuario'] !== 'administrador' && $data['id_instructor'] != $user['id_usuario']) {
                Response::forbidden('No tienes permiso para asignar otro instructor');
                return;
            }
            
            $cursoId = $this->cursoModel->create($data);
            
            if ($cursoId) {
                $curso = $this->cursoModel->findById($cursoId);
                Response::success('Curso creado exitosamente', $curso, 201);
            } else {
                Response::serverError('Error al crear curso');
            }
        } catch (Exception $e) {
            Logger::error('Error al crear curso: ' . $e->getMessage());
            Response::serverError('Error al crear curso');
        }
    }
    
    /**
     * Actualizar un curso
     * PUT /courses/{id}
     * Requiere autenticación: instructor (propietario) o admin
     */
    public function updateCourse($id) {
        try {
            $user = AuthMiddleware::requireAuth(['mentor', 'administrador']);
            
            $curso = $this->cursoModel->findById($id, false);
            if (!$curso) {
                Response::notFound('Curso no encontrado');
                return;
            }
            
            // Verificar permisos (solo el instructor del curso o administrador)
            if ($user['tipo_usuario'] !== 'administrador' && $curso['id_instructor'] != $user['id_usuario']) {
                Response::forbidden('No tienes permiso para editar este curso');
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validar datos
            if (isset($data['titulo'])) {
                $validator = new Validator($data, [
                    'titulo' => 'min:5|max:200'
                ]);
                
                if (!$validator->validate()) {
                    Response::validationError($validator->getErrors());
                    return;
                }
            }
            
            // Solo administrador puede cambiar instructor
            if (isset($data['id_instructor']) && $user['tipo_usuario'] !== 'administrador') {
                unset($data['id_instructor']);
            }
            
            $result = $this->cursoModel->update($id, $data);
            
            if ($result) {
                $cursoActualizado = $this->cursoModel->findById($id);
                Response::success('Curso actualizado exitosamente', $cursoActualizado);
            } else {
                Response::serverError('Error al actualizar curso');
            }
        } catch (Exception $e) {
            Logger::error('Error al actualizar curso: ' . $e->getMessage());
            Response::serverError('Error al actualizar curso');
        }
    }
    
    /**
     * Eliminar un curso
     * DELETE /courses/{id}
     * Requiere autenticación: instructor (propietario) o admin
     */
    public function deleteCourse($id) {
        try {
            $user = AuthMiddleware::requireAuth(['mentor', 'administrador']);
            
            $curso = $this->cursoModel->findById($id, false);
            if (!$curso) {
                Response::notFound('Curso no encontrado');
                return;
            }
            
            // Verificar permisos
            if ($user['tipo_usuario'] !== 'administrador' && $curso['id_instructor'] != $user['id_usuario']) {
                Response::forbidden('No tienes permiso para eliminar este curso');
                return;
            }
            
            $result = $this->cursoModel->delete($id);
            
            if ($result) {
                Response::success('Curso eliminado exitosamente');
            } else {
                Response::serverError('Error al eliminar curso');
            }
        } catch (Exception $e) {
            Logger::error('Error al eliminar curso: ' . $e->getMessage());
            Response::serverError('Error al eliminar curso');
        }
    }
    
    /**
     * Inscribirse a un curso
     * POST /courses/{id}/enroll
     * Requiere autenticación
     */
    public function enrollCourse($id) {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $curso = $this->cursoModel->findById($id, false);
            if (!$curso) {
                Response::notFound('Curso no encontrado');
                return;
            }
            
            // Verificar que el curso esté publicado
            if ($curso['estado'] !== 'publicado') {
                Response::badRequest('Este curso no está disponible para inscripción');
                return;
            }
            
            $result = $this->inscripcionModel->enroll($user['id_usuario'], $id);
            
            if ($result['success']) {
                Response::success($result['message'], ['inscripcion_id' => $result['inscripcion_id'] ?? null]);
            } else {
                Response::badRequest($result['message']);
            }
        } catch (Exception $e) {
            Logger::error('Error al inscribir usuario: ' . $e->getMessage());
            Response::serverError('Error al procesar inscripción');
        }
    }
    
    /**
     * Desinscribirse de un curso
     * DELETE /courses/{id}/enroll
     * Requiere autenticación
     */
    public function unenrollCourse($id) {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $result = $this->inscripcionModel->unenroll($user['id_usuario'], $id);
            
            if ($result['success']) {
                Response::success($result['message']);
            } else {
                Response::badRequest($result['message']);
            }
        } catch (Exception $e) {
            Logger::error('Error al desinscribir usuario: ' . $e->getMessage());
            Response::serverError('Error al procesar desinscripción');
        }
    }
    
    /**
     * Obtener estudiantes inscritos en un curso
     * GET /courses/{id}/students
     * Requiere autenticación: instructor (propietario) o admin
     */
    public function getCourseStudents($id) {
        try {
            $user = AuthMiddleware::requireAuth(['mentor', 'administrador']);
            
            $curso = $this->cursoModel->findById($id, false);
            if (!$curso) {
                Response::notFound('Curso no encontrado');
                return;
            }
            
            // Verificar permisos
            if ($user['tipo_usuario'] !== 'administrador' && $curso['id_instructor'] != $user['id_usuario']) {
                Response::forbidden('No tienes permiso para ver los estudiantes de este curso');
                return;
            }
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            
            $result = $this->cursoModel->getEnrolledStudents($id, $page, $limit);
            
            Response::success('Estudiantes obtenidos exitosamente', $result);
        } catch (Exception $e) {
            Logger::error('Error al obtener estudiantes: ' . $e->getMessage());
            Response::serverError('Error al obtener estudiantes');
        }
    }
    
    /**
     * Obtener mis cursos inscritos
     * GET /my-courses
     * Requiere autenticación
     */
    public function getMyCourses() {
        try {
            $user = AuthMiddleware::requireAuth();
            
            $filters = [];
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            
            if (isset($_GET['completado'])) {
                $filters['completado'] = $_GET['completado'] === 'true';
            }
            
            if (isset($_GET['categoria'])) {
                $filters['id_categoria'] = (int)$_GET['categoria'];
            }
            
            if (isset($_GET['order_by'])) {
                $filters['order_by'] = $_GET['order_by'];
            }
            
            $result = $this->inscripcionModel->getUserCourses($user['id_usuario'], $filters, $page, $limit);
            
            Response::success('Tus cursos obtenidos exitosamente', $result);
        } catch (Exception $e) {
            Logger::error('Error al obtener mis cursos: ' . $e->getMessage());
            Response::serverError('Error al obtener tus cursos');
        }
    }
}
