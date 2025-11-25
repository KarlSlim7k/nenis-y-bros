<?php
/**
 * ============================================================================
 * MODELO: CURSO
 * ============================================================================
 * Gestiona la lógica de negocio relacionada con los cursos
 * Fase 2A - Sistema de Cursos Básico
 * ============================================================================
 */

class Curso {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear un nuevo curso
     */
    public function create($data) {
        $query = "INSERT INTO cursos (
            id_categoria, id_instructor, titulo, slug, descripcion, descripcion_corta,
            imagen_portada, nivel, duracion_estimada, precio, estado, fecha_publicacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['id_categoria'],
            $data['id_instructor'],
            $data['titulo'],
            $data['slug'],
            $data['descripcion'] ?? null,
            $data['descripcion_corta'] ?? null,
            $data['imagen_portada'] ?? null,
            $data['nivel'] ?? 'principiante',
            $data['duracion_estimada'] ?? 0,
            $data['precio'] ?? 0.00,
            $data['estado'] ?? 'borrador',
            $data['fecha_publicacion'] ?? null
        ];
        
        try {
            $cursoId = $this->db->insert($query, $params);
            if ($cursoId) {
                Logger::activity($data['id_instructor'], "Curso creado: {$data['titulo']} (ID: $cursoId)");
                return $cursoId;
            }
            return false;
        } catch (Exception $e) {
            Logger::error("Error al crear curso: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener un curso por ID
     */
    public function findById($id, $includeStats = true) {
        $query = "SELECT 
            c.*,
            cat.nombre as categoria_nombre,
            cat.slug as categoria_slug,
            u.nombre as instructor_nombre,
            u.apellido as instructor_apellido,
            u.email as instructor_email,
            u.foto_perfil as instructor_foto";
        
        if ($includeStats) {
            $query .= ",
                (SELECT COUNT(*) FROM modulos WHERE id_curso = c.id_curso) as total_modulos,
                (SELECT COUNT(*) FROM lecciones l 
                 INNER JOIN modulos m ON l.id_modulo = m.id_modulo 
                 WHERE m.id_curso = c.id_curso) as total_lecciones";
        }
        
        $query .= " FROM cursos c
            LEFT JOIN categorias_cursos cat ON c.id_categoria = cat.id_categoria
            LEFT JOIN usuarios u ON c.id_instructor = u.id_usuario
            WHERE c.id_curso = ?";
        
        return $this->db->fetchOne($query, [$id]);
    }
    
    /**
     * Obtener todos los cursos con filtros
     */
    public function findAll($filters = [], $page = 1, $limit = 10) {
        $conditions = [];
        $params = [];
        
        // Filtro por categoría
        if (!empty($filters['id_categoria'])) {
            $conditions[] = "c.id_categoria = ?";
            $params[] = $filters['id_categoria'];
        }
        
        // Filtro por nivel
        if (!empty($filters['nivel'])) {
            $conditions[] = "c.nivel = ?";
            $params[] = $filters['nivel'];
        }
        
        // Filtro por estado (por defecto solo publicados para usuarios normales)
        if (isset($filters['estado'])) {
            $conditions[] = "c.estado = ?";
            $params[] = $filters['estado'];
        } else {
            $conditions[] = "c.estado = 'publicado'";
        }
        
        // Filtro por instructor
        if (!empty($filters['id_instructor'])) {
            $conditions[] = "c.id_instructor = ?";
            $params[] = $filters['id_instructor'];
        }
        
        // Búsqueda por texto
        if (!empty($filters['search'])) {
            $conditions[] = "(c.titulo LIKE ? OR c.descripcion LIKE ? OR c.descripcion_corta LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        // Query de conteo total
        $countQuery = "SELECT COUNT(*) as total FROM cursos c $where";
        $totalResult = $this->db->fetchOne($countQuery, $params);
        $total = $totalResult['total'];
        
        // Query principal con paginación
        $offset = ($page - 1) * $limit;
        
        // Ordenamiento
        $orderBy = "c.fecha_creacion DESC";
        if (!empty($filters['order_by'])) {
            switch ($filters['order_by']) {
                case 'popular':
                    $orderBy = "c.total_inscripciones DESC";
                    break;
                case 'rating':
                    $orderBy = "c.promedio_calificacion DESC";
                    break;
                case 'newest':
                    $orderBy = "c.fecha_publicacion DESC";
                    break;
                case 'title':
                    $orderBy = "c.titulo ASC";
                    break;
            }
        }
        
        $query = "SELECT 
            c.*,
            cat.nombre as categoria_nombre,
            cat.slug as categoria_slug,
            cat.color as categoria_color,
            u.nombre as instructor_nombre,
            u.apellido as instructor_apellido,
            (SELECT COUNT(*) FROM modulos WHERE id_curso = c.id_curso) as total_modulos,
            (SELECT COUNT(*) FROM lecciones l 
             INNER JOIN modulos m ON l.id_modulo = m.id_modulo 
             WHERE m.id_curso = c.id_curso) as total_lecciones
        FROM cursos c
        LEFT JOIN categorias_cursos cat ON c.id_categoria = cat.id_categoria
        LEFT JOIN usuarios u ON c.id_instructor = u.id_usuario
        $where
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
     * Actualizar un curso
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'id_categoria', 'titulo', 'slug', 'descripcion', 'descripcion_corta',
            'imagen_portada', 'nivel', 'duracion_estimada', 'precio', 'estado',
            'fecha_publicacion'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        
        $query = "UPDATE cursos SET " . implode(", ", $fields) . " WHERE id_curso = ?";
        
        try {
            $result = $this->db->query($query, $params);
            if ($result) {
                Logger::activity("Curso actualizado (ID: $id)", $data['id_instructor'] ?? null);
            }
            return $result;
        } catch (Exception $e) {
            Logger::error("Error al actualizar curso: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Eliminar un curso
     */
    public function delete($id) {
        try {
            $query = "DELETE FROM cursos WHERE id_curso = ?";
            $result = $this->db->query($query, [$id]);
            
            if ($result) {
                Logger::activity(0, "Curso eliminado (ID: $id)");
            }
            
            return $result;
        } catch (Exception $e) {
            Logger::error("Error al eliminar curso: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener cursos por categoría
     */
    public function getCoursesByCategory($categoryId, $page = 1, $limit = 10) {
        return $this->findAll(['id_categoria' => $categoryId], $page, $limit);
    }
    
    /**
     * Buscar cursos
     */
    public function search($searchTerm, $page = 1, $limit = 10) {
        return $this->findAll(['search' => $searchTerm], $page, $limit);
    }
    
    /**
     * Obtener estudiantes inscritos en un curso
     */
    public function getEnrolledStudents($cursoId, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $countQuery = "SELECT COUNT(*) as total FROM inscripciones WHERE id_curso = ?";
        $totalResult = $this->db->fetchOne($countQuery, [$cursoId]);
        $total = $totalResult['total'];
        
        $query = "SELECT 
            i.*,
            u.nombre,
            u.apellido,
            u.email,
            u.foto_perfil
        FROM inscripciones i
        INNER JOIN usuarios u ON i.id_usuario = u.id_usuario
        WHERE i.id_curso = ?
        ORDER BY i.fecha_inscripcion DESC
        LIMIT ? OFFSET ?";
        
        $estudiantes = $this->db->fetchAll($query, [$cursoId, $limit, $offset]);
        
        return [
            'data' => $estudiantes,
            'pagination' => [
                'total' => (int) $total,
                'per_page' => (int) $limit,
                'current_page' => (int) $page,
                'last_page' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Verificar si un curso existe
     */
    public function exists($id) {
        $query = "SELECT COUNT(*) as count FROM cursos WHERE id_curso = ?";
        $result = $this->db->fetchOne($query, [$id]);
        return $result['count'] > 0;
    }
    
    /**
     * Validar si un usuario es el instructor del curso
     */
    public function isInstructor($cursoId, $userId) {
        $query = "SELECT COUNT(*) as count FROM cursos WHERE id_curso = ? AND id_instructor = ?";
        $result = $this->db->fetchOne($query, [$cursoId, $userId]);
        return $result['count'] > 0;
    }
    
    /**
     * Generar slug único para un curso
     */
    public function generateUniqueSlug($titulo, $excludeId = null) {
        $baseSlug = $this->slugify($titulo);
        $slug = $baseSlug;
        $counter = 1;
        
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Verificar si un slug existe
     */
    private function slugExists($slug, $excludeId = null) {
        $query = "SELECT COUNT(*) as count FROM cursos WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $query .= " AND id_curso != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->fetchOne($query, $params);
        return $result['count'] > 0;
    }
    
    /**
     * Convertir texto a slug
     */
    private function slugify($text) {
        // Reemplazar caracteres especiales
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'n'],
            $text
        );
        
        // Convertir a minúsculas
        $text = strtolower($text);
        
        // Reemplazar todo lo que no sea letra, número o guión por guión
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        
        // Reemplazar múltiples guiones por uno solo
        $text = preg_replace('/-+/', '-', $text);
        
        // Eliminar guiones al inicio y final
        $text = trim($text, '-');
        
        return $text;
    }
}
