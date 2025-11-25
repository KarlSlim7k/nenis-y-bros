<?php

/**
 * Modelo: CategoriaRecurso
 * 
 * Gestión de categorías para recursos descargables
 */
class CategoriaRecurso {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Obtener todas las categorías activas
     */
    public function getAll($includeInactivas = false) {
        $cacheKey = 'categorias:all:' . ($includeInactivas ? 'all' : 'active');
        
        $cached = Cache::getInstance()->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $where = $includeInactivas ? '' : 'WHERE activa = 1';
        
        $query = "
            SELECT * FROM categorias_recursos 
            {$where}
            ORDER BY orden ASC, nombre ASC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cachear por 15 minutos
        Cache::getInstance()->set($cacheKey, $result, 900);
        
        return $result;
    }
    
    /**
     * Obtener categoría por ID
     */
    public function getById($id) {
        $cacheKey = "categoria:$id";
        
        $cached = Cache::getInstance()->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $query = "SELECT * FROM categorias_recursos WHERE id_categoria = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            Cache::getInstance()->set($cacheKey, $result, 900);
        }
        
        return $result;
    }
    
    /**
     * Obtener categoría por slug
     */
    public function getBySlug($slug) {
        $query = "SELECT * FROM categorias_recursos WHERE slug = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$slug]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear nueva categoría
     */
    public function create($data) {
        $query = "
            INSERT INTO categorias_recursos 
            (nombre, slug, descripcion, icono, color, orden, activa)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($query);
        
        $success = $stmt->execute([
            $data['nombre'],
            $data['slug'],
            $data['descripcion'] ?? null,
            $data['icono'] ?? 'folder',
            $data['color'] ?? '#6366f1',
            $data['orden'] ?? 0,
            $data['activa'] ?? true
        ]);
        
        if ($success) {
            Cache::getInstance()->invalidateCategories();
        }
        
        return $success ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Actualizar categoría
     */
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        $allowedFields = ['nombre', 'slug', 'descripcion', 'icono', 'color', 'orden', 'activa'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $query = "UPDATE categorias_recursos SET " . implode(', ', $fields) . " WHERE id_categoria = ?";
        
        $stmt = $this->db->prepare($query);
        $success = $stmt->execute($values);
        
        if ($success) {
            Cache::getInstance()->delete("categoria:$id");
            Cache::getInstance()->invalidateCategories();
        }
        
        return $success;
    }
    
    /**
     * Eliminar categoría (solo si no tiene recursos)
     */
    public function delete($id) {
        // Verificar si tiene recursos
        $checkQuery = "SELECT COUNT(*) as total FROM recursos WHERE id_categoria = ?";
        $stmt = $this->db->prepare($checkQuery);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total'] > 0) {
            return false; // No se puede eliminar si tiene recursos
        }
        
        $query = "DELETE FROM categorias_recursos WHERE id_categoria = ?";
        $stmt = $this->db->prepare($query);
        $success = $stmt->execute([$id]);
        
        if ($success) {
            Cache::getInstance()->delete("categoria:$id");
            Cache::getInstance()->invalidateCategories();
        }
        
        return $success;
    }
    
    /**
     * Obtener categorías con estadísticas
     */
    public function getWithStats() {
        $query = "
            SELECT 
                c.*,
                COUNT(DISTINCT CASE WHEN r.estado = 'publicado' THEN r.id_recurso END) as recursos_publicados,
                COUNT(DISTINCT CASE WHEN r.estado = 'borrador' THEN r.id_recurso END) as recursos_borrador,
                COALESCE(SUM(r.total_descargas), 0) as total_descargas_categoria
            FROM categorias_recursos c
            LEFT JOIN recursos r ON c.id_categoria = r.id_categoria
            GROUP BY c.id_categoria
            ORDER BY c.orden ASC, c.nombre ASC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
