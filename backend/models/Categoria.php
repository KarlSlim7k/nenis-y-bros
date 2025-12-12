<?php
/**
 * ============================================================================
 * MODELO: CATEGORIA
 * ============================================================================
 * Gestiona las categorías de cursos
 * ============================================================================
 */

class Categoria {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener todas las categorías
     * 
     * @param array $filters Filtros opcionales
     * @return array
     */
    public function getAll($filters = []) {
        $query = "SELECT * FROM categorias_cursos WHERE 1=1";
        $params = [];
        
        if (isset($filters['activo'])) {
            $query .= " AND activo = ?";
            $params[] = $filters['activo'];
        }
        
        $query .= " ORDER BY orden, nombre";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Obtener una categoría por ID
     * 
     * @param int $id
     * @return array|false
     */
    public function findById($id) {
        $query = "SELECT * FROM categorias_cursos WHERE id_categoria = ?";
        return $this->db->fetchOne($query, [$id]);
    }
    
    /**
     * Crear una nueva categoría
     * 
     * @param array $data
     * @return int|false ID de la categoría creada o false
     */
    public function create($data) {
        $query = "INSERT INTO categorias_cursos (
            nombre, descripcion, icono, color, orden, activo
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['icono'] ?? null,
            $data['color'] ?? '#6366f1',
            $data['orden'] ?? 0,
            $data['activo'] ?? 1
        ];
        
        return $this->db->insert($query, $params);
    }
    
    /**
     * Actualizar una categoría
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['nombre', 'descripcion', 'icono', 'color', 'orden', 'activo'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        
        $query = "UPDATE categorias_cursos SET " . implode(', ', $fields) . " WHERE id_categoria = ?";
        $affected = $this->db->execute($query, $params);
        
        return $affected > 0;
    }
    
    /**
     * Eliminar una categoría (soft delete)
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        // Verificar si hay cursos usando esta categoría
        $query = "SELECT COUNT(*) as count FROM cursos WHERE id_categoria = ?";
        $result = $this->db->fetchOne($query, [$id]);
        
        if ($result['count'] > 0) {
            return false; // No se puede eliminar si hay cursos
        }
        
        // Soft delete: marcar como inactiva
        $query = "UPDATE categorias_cursos SET activo = 0 WHERE id_categoria = ?";
        return $this->db->execute($query, [$id]) > 0;
    }
    
    /**
     * Reordenar categorías
     * 
     * @param array $orden Array de IDs en el nuevo orden
     * @return bool
     */
    public function reorder($orden) {
        try {
            $this->db->beginTransaction();
            
            foreach ($orden as $index => $id) {
                $query = "UPDATE categorias_cursos SET orden = ? WHERE id_categoria = ?";
                $this->db->execute($query, [$index + 1, $id]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * Contar cursos por categoría
     * 
     * @param int $id
     * @return int
     */
    public function countCursos($id) {
        $query = "SELECT COUNT(*) as count FROM cursos WHERE id_categoria = ? AND estado = 'publicado'";
        $result = $this->db->fetchOne($query, [$id]);
        return $result['count'];
    }
}
