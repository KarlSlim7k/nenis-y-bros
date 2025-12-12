<?php
/**
 * ============================================================================
 * CONTROLADOR: CATEGORIAS
 * ============================================================================
 * Gestiona las categorías de cursos
 * ============================================================================
 */

class CategoriaController {
    
    private $categoriaModel;
    
    public function __construct() {
        $this->categoriaModel = new Categoria();
    }
    
    /**
     * Obtener todas las categorías
     * GET /categories
     */
    public function getAll() {
        try {
            $filters = [];
            
            if (isset($_GET['activo'])) {
                $filters['activo'] = $_GET['activo'] === 'true' ? 1 : 0;
            }
            
            $categorias = $this->categoriaModel->getAll($filters);
            
            // Agregar contador de cursos a cada categoría
            foreach ($categorias as &$categoria) {
                $categoria['total_cursos'] = $this->categoriaModel->countCursos($categoria['id_categoria']);
            }
            
            Response::success(['categorias' => $categorias]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener categorías: ' . $e->getMessage());
            Response::serverError('Error al obtener categorías');
        }
    }
    
    /**
     * Obtener una categoría específica
     * GET /categories/{id}
     */
    public function getById($id) {
        try {
            $categoria = $this->categoriaModel->findById($id);
            
            if (!$categoria) {
                Response::notFound('Categoría no encontrada');
            }
            
            $categoria['total_cursos'] = $this->categoriaModel->countCursos($id);
            
            Response::success(['categoria' => $categoria]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener categoría: ' . $e->getMessage());
            Response::serverError('Error al obtener categoría');
        }
    }
    
    /**
     * Crear una nueva categoría
     * POST /categories
     * Requiere: rol administrador
     */
    public function create() {
        $user = AuthMiddleware::verifyRole(['administrador']);
        
        if (!$user) {
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos
        $validator = new Validator($data, [
            'nombre' => 'required|min:3|max:100'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $id = $this->categoriaModel->create($data);
            
            if (!$id) {
                Response::error('No se pudo crear la categoría', 400);
            }
            
            $categoria = $this->categoriaModel->findById($id);
            
            Logger::activity($user['id_usuario'], 'Categoría creada', [
                'id_categoria' => $id,
                'nombre' => $data['nombre']
            ]);
            
            Response::success(['categoria' => $categoria], 'Categoría creada exitosamente', 201);
            
        } catch (Exception $e) {
            Logger::error('Error al crear categoría: ' . $e->getMessage());
            Response::serverError('Error al crear categoría');
        }
    }
    
    /**
     * Actualizar una categoría
     * PUT /categories/{id}
     * Requiere: rol administrador
     */
    public function update($id) {
        $user = AuthMiddleware::verifyRole(['administrador']);
        
        if (!$user) {
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos
        if (isset($data['nombre'])) {
            $validator = new Validator($data, [
                'nombre' => 'min:3|max:100'
            ]);
            
            if (!$validator->validate()) {
                Response::validationError($validator->getErrors());
            }
        }
        
        try {
            $categoria = $this->categoriaModel->findById($id);
            
            if (!$categoria) {
                Response::notFound('Categoría no encontrada');
            }
            
            $updated = $this->categoriaModel->update($id, $data);
            
            if (!$updated) {
                Response::error('No se pudo actualizar la categoría', 400);
            }
            
            Logger::activity($user['id_usuario'], 'Categoría actualizada', [
                'id_categoria' => $id,
                'cambios' => $data
            ]);
            
            $categoriaActualizada = $this->categoriaModel->findById($id);
            
            Response::success(['categoria' => $categoriaActualizada], 'Categoría actualizada exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al actualizar categoría: ' . $e->getMessage());
            Response::serverError('Error al actualizar categoría');
        }
    }
    
    /**
     * Eliminar una categoría
     * DELETE /categories/{id}
     * Requiere: rol administrador
     */
    public function delete($id) {
        $user = AuthMiddleware::verifyRole(['administrador']);
        
        if (!$user) {
            return;
        }
        
        try {
            $categoria = $this->categoriaModel->findById($id);
            
            if (!$categoria) {
                Response::notFound('Categoría no encontrada');
            }
            
            // Verificar si hay cursos usando esta categoría
            $totalCursos = $this->categoriaModel->countCursos($id);
            if ($totalCursos > 0) {
                Response::error("No se puede eliminar la categoría porque tiene {$totalCursos} cursos asociados", 400);
            }
            
            $deleted = $this->categoriaModel->delete($id);
            
            if (!$deleted) {
                Response::error('No se pudo eliminar la categoría', 400);
            }
            
            Logger::activity($user['id_usuario'], 'Categoría eliminada', [
                'id_categoria' => $id,
                'nombre' => $categoria['nombre']
            ]);
            
            Response::success(null, 'Categoría eliminada exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al eliminar categoría: ' . $e->getMessage());
            Response::serverError('Error al eliminar categoría');
        }
    }
    
    /**
     * Reordenar categorías
     * PUT /categories/reorder
     * Requiere: rol administrador
     */
    public function reorder() {
        $user = AuthMiddleware::verifyRole(['administrador']);
        
        if (!$user) {
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['orden']) || !is_array($data['orden'])) {
            Response::validationError(['orden' => ['El campo orden es requerido y debe ser un array']]);
        }
        
        try {
            $result = $this->categoriaModel->reorder($data['orden']);
            
            if (!$result) {
                Response::error('No se pudo reordenar las categorías', 400);
            }
            
            Logger::activity($user['id_usuario'], 'Categorías reordenadas');
            
            Response::success(null, 'Categorías reordenadas exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al reordenar categorías: ' . $e->getMessage());
            Response::serverError('Error al reordenar categorías');
        }
    }
}
