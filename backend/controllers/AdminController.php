<?php
/**
 * ============================================================================
 * CONTROLADOR DE ADMINISTRACIÓN
 * ============================================================================
 * Gestiona operaciones administrativas del sistema
 * ============================================================================
 */

class AdminController {
    
    private $usuarioModel;
    
    public function __construct() {
        $this->usuarioModel = new Usuario();
    }
    
    /**
     * Obtiene dashboard con estadísticas
     * 
     * GET /api/admin/dashboard
     * Headers: Authorization: Bearer {token}
     * Requiere: rol administrador
     */
    public function getDashboard() {
        $user = AuthMiddleware::verifyRole(['administrador']);
        
        if (!$user) {
            return;
        }
        
        try {
            $stats = $this->usuarioModel->getStatistics();
            
            // Obtener estadísticas adicionales (si existen otros modelos)
            $cursoModel = class_exists('Curso') ? new Curso() : null;
            $diagnosticoModel = class_exists('DiagnosticoRealizado') ? new DiagnosticoRealizado() : null;
            $productoModel = class_exists('Producto') ? new Producto() : null;
            
            $estadisticas = [
                'total_usuarios' => $stats['total'],
                'total_cursos' => $cursoModel ? $this->getCursosCount() : 0,
                'total_diagnosticos' => $diagnosticoModel ? $this->getDiagnosticosCount() : 0,
                'total_productos' => $productoModel ? $this->getProductosCount() : 0,
                'usuarios_por_tipo' => $stats['por_tipo'],
                'usuarios_por_estado' => $stats['por_estado'],
                'registros_recientes' => $stats['registros_recientes']
            ];
            
            Response::success([
                'statistics' => $estadisticas
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener dashboard: ' . $e->getMessage());
            Response::serverError('Error al obtener estadísticas');
        }
    }
    
    private function getCursosCount() {
        try {
            $db = Database::getInstance();
            $result = $db->fetchOne("SELECT COUNT(*) as total FROM cursos WHERE estado = 'activo'");
            return $result['total'];
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getDiagnosticosCount() {
        try {
            $db = Database::getInstance();
            $result = $db->fetchOne("SELECT COUNT(*) as total FROM diagnosticos_realizados");
            return $result['total'];
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getProductosCount() {
        try {
            $db = Database::getInstance();
            $result = $db->fetchOne("SELECT COUNT(*) as total FROM productos WHERE estado = 'publicado'");
            return $result['total'];
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Obtiene listado de todos los usuarios con paginación
     * 
     * GET /api/admin/users
     * Headers: Authorization: Bearer {token}
     * Params: page, limit, tipo_usuario, estado, search
     * Requiere: rol administrador
     */
    public function getUsers() {
        $user = AuthMiddleware::verifyRole(['administrador']);
        
        if (!$user) {
            return;
        }
        
        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            
            $filters = [];
            
            if (isset($_GET['tipo_usuario'])) {
                $filters['tipo_usuario'] = $_GET['tipo_usuario'];
            }
            
            if (isset($_GET['estado'])) {
                $filters['estado'] = $_GET['estado'];
            }
            
            if (isset($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }
            
            $result = $this->usuarioModel->getAll($page, $limit, $filters);
            
            // Adaptar formato de respuesta
            Response::success([
                'usuarios' => $result['data'],
                'page' => $result['pagination']['page'],
                'limit' => $result['pagination']['limit'],
                'total' => $result['pagination']['total'],
                'total_pages' => $result['pagination']['pages']
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener usuarios: ' . $e->getMessage());
            Response::serverError('Error al obtener usuarios');
        }
    }
    
    /**
     * Obtiene un usuario específico
     * 
     * GET /api/admin/users/{id}
     * Headers: Authorization: Bearer {token}
     * Requiere: rol administrador
     */
    public function getUserDetails($id) {
        $user = AuthMiddleware::verifyRole(['administrador']);
        
        if (!$user) {
            return;
        }
        
        try {
            $targetUser = $this->usuarioModel->findById($id);
            
            if (!$targetUser) {
                Response::notFound('Usuario no encontrado');
            }
            
            unset($targetUser['password_hash']);
            
            Response::success(['user' => $targetUser]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener usuario: ' . $e->getMessage());
            Response::serverError('Error al obtener usuario');
        }
    }
    
    /**
     * Actualiza el estado de un usuario
     * 
     * PUT /api/admin/users/{id}/status
     * Headers: Authorization: Bearer {token}
     * Body: estado (activo, inactivo, suspendido)
     * Requiere: rol administrador
     */
    public function updateUserStatus($id) {
        $user = AuthMiddleware::verifyRole(['administrador']);
        
        if (!$user) {
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar estado
        $validator = new Validator($data, [
            'estado' => 'required|in:activo,inactivo,suspendido'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $targetUser = $this->usuarioModel->findById($id);
            
            if (!$targetUser) {
                Response::notFound('Usuario no encontrado');
            }
            
            // No permitir cambiar el estado de uno mismo
            if ($targetUser['id_usuario'] == $user['id_usuario']) {
                Response::error('No puedes cambiar tu propio estado', 400);
            }
            
            $updated = $this->usuarioModel->changeStatus($id, $data['estado']);
            
            if (!$updated) {
                Response::error('No se pudo actualizar el estado', 400);
            }
            
            Logger::activity($user['id_usuario'], 'Estado de usuario actualizado', [
                'target_user_id' => $id,
                'nuevo_estado' => $data['estado']
            ]);
            
            Response::success(null, 'Estado actualizado exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al actualizar estado: ' . $e->getMessage());
            Response::serverError('Error al actualizar estado');
        }
    }
    
    /**
     * Elimina un usuario (desactiva)
     * 
     * DELETE /api/admin/users/{id}
     * Headers: Authorization: Bearer {token}
     * Requiere: rol administrador
     */
    public function deleteUser($id) {
        $user = AuthMiddleware::verifyRole(['administrador']);
        
        if (!$user) {
            return;
        }
        
        try {
            $targetUser = $this->usuarioModel->findById($id);
            
            if (!$targetUser) {
                Response::notFound('Usuario no encontrado');
            }
            
            // No permitir eliminar a uno mismo
            if ($targetUser['id_usuario'] == $user['id_usuario']) {
                Response::error('No puedes eliminar tu propia cuenta', 400);
            }
            
            $deleted = $this->usuarioModel->delete($id);
            
            if (!$deleted) {
                Response::error('No se pudo eliminar el usuario', 400);
            }
            
            Logger::activity($user['id_usuario'], 'Usuario eliminado', [
                'target_user_id' => $id
            ]);
            
            Response::success(null, 'Usuario eliminado exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al eliminar usuario: ' . $e->getMessage());
            Response::serverError('Error al eliminar usuario');
        }
    }
    /**
     * Actualiza un usuario completo
     * 
     * PUT /api/admin/users/{id}
     * Headers: Authorization: Bearer {token}
     * Body: nombre, email, tipo_usuario, estado
     * Requiere: rol administrador
     */
    public function updateUser($id) {
        $user = AuthMiddleware::verifyRole(['administrador']);
        
        if (!$user) {
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos
        $validator = new Validator($data, [
            'nombre' => 'required|min:2|max:100',
            'email' => 'required|email',
            'tipo_usuario' => 'required|in:emprendedor,mentor,empresario,administrador',
            'estado' => 'required|in:activo,inactivo,pendiente,suspendido'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $targetUser = $this->usuarioModel->findById($id);
            
            if (!$targetUser) {
                Response::notFound('Usuario no encontrado');
            }
            
            // Verificar si el email ya existe en otro usuario
            $existingUser = $this->usuarioModel->findByEmail($data['email']);
            if ($existingUser && $existingUser['id_usuario'] != $id) {
                Response::error('El correo electrónico ya está en uso', 400);
            }
            
            $updated = $this->usuarioModel->update($id, $data);
            
            if (!$updated) {
                Response::error('No se pudo actualizar el usuario', 400);
            }
            
            Logger::activity($user['id_usuario'], 'Usuario actualizado por admin', [
                'target_user_id' => $id,
                'changes' => $data
            ]);
            
            Response::success(null, 'Usuario actualizado exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al actualizar usuario: ' . $e->getMessage());
            Response::serverError('Error al actualizar usuario');
        }
    }
}
