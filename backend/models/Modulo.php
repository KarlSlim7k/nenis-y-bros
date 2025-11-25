<?php
/**
 * ============================================================================
 * MODELO: MODULO
 * ============================================================================
 * Gestiona la lógica de negocio relacionada con los módulos de cursos
 * Fase 2A - Sistema de Cursos Básico
 * ============================================================================
 */

class Modulo {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear un nuevo módulo
     */
    public function create($data) {
        // Si no se especifica orden, obtener el siguiente disponible
        if (!isset($data['orden'])) {
            $data['orden'] = $this->getNextOrder($data['id_curso']);
        }
        
        $query = "INSERT INTO modulos (id_curso, titulo, descripcion, orden) 
                  VALUES (?, ?, ?, ?)";
        
        $params = [
            $data['id_curso'],
            $data['titulo'],
            $data['descripcion'] ?? null,
            $data['orden']
        ];
        
        try {
            $moduloId = $this->db->insert($query, $params);
            if ($moduloId) {
                Logger::activity($data['user_id'] ?? 0, "Módulo creado: {$data['titulo']} (ID: $moduloId)");
                return $moduloId;
            }
            return false;
        } catch (Exception $e) {
            Logger::error("Error al crear módulo: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener un módulo por ID
     */
    public function findById($id, $includeLessons = false) {
        $query = "SELECT 
            m.*,
            c.titulo as curso_titulo,
            c.slug as curso_slug";
        
        if ($includeLessons) {
            $query .= ",
                (SELECT COUNT(*) FROM lecciones WHERE id_modulo = m.id_modulo) as total_lecciones,
                (SELECT SUM(duracion_minutos) FROM lecciones WHERE id_modulo = m.id_modulo) as duracion_total";
        }
        
        $query .= " FROM modulos m
            LEFT JOIN cursos c ON m.id_curso = c.id_curso
            WHERE m.id_modulo = ?";
        
        $modulo = $this->db->fetchOne($query, [$id]);
        
        if ($modulo && $includeLessons) {
            // Obtener lecciones del módulo
            $leccionModel = new Leccion();
            $modulo['lecciones'] = $leccionModel->findByModule($id);
        }
        
        return $modulo;
    }
    
    /**
     * Obtener módulos de un curso
     */
    public function findByCourse($cursoId, $includeLessons = false) {
        $query = "SELECT 
            m.*,
            (SELECT COUNT(*) FROM lecciones WHERE id_modulo = m.id_modulo) as total_lecciones,
            (SELECT SUM(duracion_minutos) FROM lecciones WHERE id_modulo = m.id_modulo) as duracion_total
        FROM modulos m
        WHERE m.id_curso = ?
        ORDER BY m.orden ASC";
        
        $modulos = $this->db->fetchAll($query, [$cursoId]);
        
        if ($modulos && $includeLessons) {
            $leccionModel = new Leccion();
            foreach ($modulos as &$modulo) {
                $modulo['lecciones'] = $leccionModel->findByModule($modulo['id_modulo']);
            }
        }
        
        return $modulos;
    }
    
    /**
     * Actualizar un módulo
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['titulo', 'descripcion', 'orden'];
        
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
        
        $query = "UPDATE modulos SET " . implode(", ", $fields) . " WHERE id_modulo = ?";
        
        try {
            $result = $this->db->query($query, $params);
            if ($result) {
                Logger::activity("Módulo actualizado (ID: $id)", $data['user_id'] ?? null);
            }
            return $result;
        } catch (Exception $e) {
            Logger::error("Error al actualizar módulo: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Eliminar un módulo
     */
    public function delete($id) {
        try {
            $query = "DELETE FROM modulos WHERE id_modulo = ?";
            $result = $this->db->query($query, [$id]);
            
            if ($result) {
                Logger::activity(0, "Módulo eliminado (ID: $id)");
            }
            
            return $result;
        } catch (Exception $e) {
            Logger::error("Error al eliminar módulo: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Reordenar módulos de un curso
     */
    public function reorder($cursoId, $ordenArray) {
        try {
            // $ordenArray debe ser: [['id_modulo' => 1, 'orden' => 1], ['id_modulo' => 2, 'orden' => 2], ...]
            $this->db->beginTransaction();
            
            foreach ($ordenArray as $item) {
                $query = "UPDATE modulos SET orden = ? WHERE id_modulo = ? AND id_curso = ?";
                $this->db->query($query, [$item['orden'], $item['id_modulo'], $cursoId]);
            }
            
            $this->db->commit();
            Logger::activity(0, "Módulos reordenados en curso ID: $cursoId");
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error("Error al reordenar módulos: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Mover módulo hacia arriba
     */
    public function moveUp($id) {
        $modulo = $this->findById($id);
        if (!$modulo || $modulo['orden'] <= 1) {
            return false;
        }
        
        $cursoId = $modulo['id_curso'];
        $ordenActual = $modulo['orden'];
        $ordenNuevo = $ordenActual - 1;
        
        try {
            $this->db->beginTransaction();
            
            // Intercambiar orden con el módulo anterior
            $query = "UPDATE modulos SET orden = ? WHERE id_curso = ? AND orden = ?";
            $this->db->query($query, [$ordenActual, $cursoId, $ordenNuevo]);
            
            $query = "UPDATE modulos SET orden = ? WHERE id_modulo = ?";
            $this->db->query($query, [$ordenNuevo, $id]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error("Error al mover módulo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mover módulo hacia abajo
     */
    public function moveDown($id) {
        $modulo = $this->findById($id);
        if (!$modulo) {
            return false;
        }
        
        $cursoId = $modulo['id_curso'];
        $ordenActual = $modulo['orden'];
        $ordenNuevo = $ordenActual + 1;
        
        // Verificar que existe un módulo siguiente
        $query = "SELECT COUNT(*) as count FROM modulos WHERE id_curso = ? AND orden = ?";
        $result = $this->db->fetchOne($query, [$cursoId, $ordenNuevo]);
        
        if ($result['count'] == 0) {
            return false;
        }
        
        try {
            $this->db->beginTransaction();
            
            // Intercambiar orden con el módulo siguiente
            $query = "UPDATE modulos SET orden = ? WHERE id_curso = ? AND orden = ?";
            $this->db->query($query, [$ordenActual, $cursoId, $ordenNuevo]);
            
            $query = "UPDATE modulos SET orden = ? WHERE id_modulo = ?";
            $this->db->query($query, [$ordenNuevo, $id]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error("Error al mover módulo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener el siguiente número de orden para un curso
     */
    private function getNextOrder($cursoId) {
        $query = "SELECT COALESCE(MAX(orden), 0) + 1 as next_order FROM modulos WHERE id_curso = ?";
        $result = $this->db->fetchOne($query, [$cursoId]);
        return $result['next_order'];
    }
    
    /**
     * Verificar si un módulo existe
     */
    public function exists($id) {
        $query = "SELECT COUNT(*) as count FROM modulos WHERE id_modulo = ?";
        $result = $this->db->fetchOne($query, [$id]);
        return $result['count'] > 0;
    }
    
    /**
     * Verificar si un módulo pertenece a un curso
     */
    public function belongsToCourse($moduloId, $cursoId) {
        $query = "SELECT COUNT(*) as count FROM modulos WHERE id_modulo = ? AND id_curso = ?";
        $result = $this->db->fetchOne($query, [$moduloId, $cursoId]);
        return $result['count'] > 0;
    }
    
    /**
     * Obtener estadísticas de un módulo
     */
    public function getStats($moduloId) {
        $query = "SELECT 
            COUNT(*) as total_lecciones,
            SUM(duracion_minutos) as duracion_total,
            SUM(CASE WHEN tipo_contenido = 'video' THEN 1 ELSE 0 END) as total_videos,
            SUM(CASE WHEN tipo_contenido = 'texto' THEN 1 ELSE 0 END) as total_textos,
            SUM(CASE WHEN tipo_contenido = 'documento' THEN 1 ELSE 0 END) as total_documentos
        FROM lecciones
        WHERE id_modulo = ?";
        
        return $this->db->fetchOne($query, [$moduloId]);
    }
}
