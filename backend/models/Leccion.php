<?php
/**
 * ============================================================================
 * MODELO: LECCION
 * ============================================================================
 * Gestiona la lógica de negocio relacionada con las lecciones
 * Fase 2A - Sistema de Cursos Básico
 * ============================================================================
 */

class Leccion {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear una nueva lección
     */
    public function create($data) {
        // Si no se especifica orden, obtener el siguiente disponible
        if (!isset($data['orden'])) {
            $data['orden'] = $this->getNextOrder($data['id_modulo']);
        }
        
        $query = "INSERT INTO lecciones (
            id_modulo, titulo, contenido, tipo_contenido, url_recurso, orden, duracion_minutos
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['id_modulo'],
            $data['titulo'],
            $data['contenido'] ?? null,
            $data['tipo_contenido'] ?? 'texto',
            $data['url_recurso'] ?? null,
            $data['orden'],
            $data['duracion_minutos'] ?? 0
        ];
        
        try {
            $leccionId = $this->db->insert($query, $params);
            if ($leccionId) {
                Logger::activity($data['user_id'] ?? 0, "Lección creada: {$data['titulo']} (ID: $leccionId)");
                return $leccionId;
            }
            return false;
        } catch (Exception $e) {
            Logger::error("Error al crear lección: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener una lección por ID
     */
    public function findById($id, $includeProgress = false, $userId = null) {
        $query = "SELECT 
            l.*,
            m.titulo as modulo_titulo,
            m.id_curso,
            c.titulo as curso_titulo,
            c.slug as curso_slug";
        
        if ($includeProgress && $userId) {
            $query .= ",
                (SELECT completada FROM progreso_lecciones pl
                 INNER JOIN inscripciones i ON pl.id_inscripcion = i.id_inscripcion
                 WHERE pl.id_leccion = l.id_leccion 
                 AND i.id_usuario = ? AND i.id_curso = m.id_curso) as completada,
                (SELECT tiempo_dedicado FROM progreso_lecciones pl
                 INNER JOIN inscripciones i ON pl.id_inscripcion = i.id_inscripcion
                 WHERE pl.id_leccion = l.id_leccion 
                 AND i.id_usuario = ? AND i.id_curso = m.id_curso) as tiempo_dedicado";
        }
        
        $query .= " FROM lecciones l
            INNER JOIN modulos m ON l.id_modulo = m.id_modulo
            INNER JOIN cursos c ON m.id_curso = c.id_curso
            WHERE l.id_leccion = ?";
        
        $params = [];
        if ($includeProgress && $userId) {
            $params[] = $userId;
            $params[] = $userId;
        }
        $params[] = $id;
        
        return $this->db->fetchOne($query, $params);
    }
    
    /**
     * Obtener lecciones de un módulo
     */
    public function findByModule($moduloId, $includeProgress = false, $userId = null) {
        $query = "SELECT l.*";
        
        if ($includeProgress && $userId) {
            $query .= ",
                COALESCE(pl.completada, FALSE) as completada,
                COALESCE(pl.tiempo_dedicado, 0) as tiempo_dedicado";
        }
        
        $query .= " FROM lecciones l";
        
        if ($includeProgress && $userId) {
            $query .= " LEFT JOIN progreso_lecciones pl ON l.id_leccion = pl.id_leccion
                LEFT JOIN inscripciones i ON pl.id_inscripcion = i.id_inscripcion AND i.id_usuario = ?";
        }
        
        $query .= " WHERE l.id_modulo = ? ORDER BY l.orden ASC";
        
        $params = [];
        if ($includeProgress && $userId) {
            $params[] = $userId;
        }
        $params[] = $moduloId;
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Obtener todas las lecciones de un curso
     */
    public function findByCourse($cursoId, $includeProgress = false, $userId = null) {
        $query = "SELECT 
            l.*,
            m.titulo as modulo_titulo,
            m.orden as modulo_orden";
        
        if ($includeProgress && $userId) {
            $query .= ",
                COALESCE(pl.completada, FALSE) as completada,
                COALESCE(pl.tiempo_dedicado, 0) as tiempo_dedicado";
        }
        
        $query .= " FROM lecciones l
            INNER JOIN modulos m ON l.id_modulo = m.id_modulo";
        
        if ($includeProgress && $userId) {
            $query .= " LEFT JOIN progreso_lecciones pl ON l.id_leccion = pl.id_leccion
                LEFT JOIN inscripciones i ON pl.id_inscripcion = i.id_inscripcion AND i.id_usuario = ?";
        }
        
        $query .= " WHERE m.id_curso = ?
            ORDER BY m.orden ASC, l.orden ASC";
        
        $params = [];
        if ($includeProgress && $userId) {
            $params[] = $userId;
        }
        $params[] = $cursoId;
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Actualizar una lección
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['titulo', 'contenido', 'tipo_contenido', 'url_recurso', 'orden', 'duracion_minutos'];
        
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
        
        $query = "UPDATE lecciones SET " . implode(", ", $fields) . " WHERE id_leccion = ?";
        
        try {
            $result = $this->db->query($query, $params);
            if ($result) {
                Logger::activity("Lección actualizada (ID: $id)", $data['user_id'] ?? null);
            }
            return $result;
        } catch (Exception $e) {
            Logger::error("Error al actualizar lección: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Eliminar una lección
     */
    public function delete($id) {
        try {
            $query = "DELETE FROM lecciones WHERE id_leccion = ?";
            $result = $this->db->query($query, [$id]);
            
            if ($result) {
                Logger::activity(0, "Lección eliminada (ID: $id)");
            }
            
            return $result;
        } catch (Exception $e) {
            Logger::error("Error al eliminar lección: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Reordenar lecciones de un módulo
     */
    public function reorder($moduloId, $ordenArray) {
        try {
            // $ordenArray debe ser: [['id_leccion' => 1, 'orden' => 1], ['id_leccion' => 2, 'orden' => 2], ...]
            $this->db->beginTransaction();
            
            foreach ($ordenArray as $item) {
                $query = "UPDATE lecciones SET orden = ? WHERE id_leccion = ? AND id_modulo = ?";
                $this->db->query($query, [$item['orden'], $item['id_leccion'], $moduloId]);
            }
            
            $this->db->commit();
            Logger::activity(0, "Lecciones reordenadas en módulo ID: $moduloId");
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error("Error al reordenar lecciones: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Mover lección hacia arriba
     */
    public function moveUp($id) {
        $leccion = $this->findById($id);
        if (!$leccion || $leccion['orden'] <= 1) {
            return false;
        }
        
        $moduloId = $leccion['id_modulo'];
        $ordenActual = $leccion['orden'];
        $ordenNuevo = $ordenActual - 1;
        
        try {
            $this->db->beginTransaction();
            
            // Intercambiar orden con la lección anterior
            $query = "UPDATE lecciones SET orden = ? WHERE id_modulo = ? AND orden = ?";
            $this->db->query($query, [$ordenActual, $moduloId, $ordenNuevo]);
            
            $query = "UPDATE lecciones SET orden = ? WHERE id_leccion = ?";
            $this->db->query($query, [$ordenNuevo, $id]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error("Error al mover lección: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mover lección hacia abajo
     */
    public function moveDown($id) {
        $leccion = $this->findById($id);
        if (!$leccion) {
            return false;
        }
        
        $moduloId = $leccion['id_modulo'];
        $ordenActual = $leccion['orden'];
        $ordenNuevo = $ordenActual + 1;
        
        // Verificar que existe una lección siguiente
        $query = "SELECT COUNT(*) as count FROM lecciones WHERE id_modulo = ? AND orden = ?";
        $result = $this->db->fetchOne($query, [$moduloId, $ordenNuevo]);
        
        if ($result['count'] == 0) {
            return false;
        }
        
        try {
            $this->db->beginTransaction();
            
            // Intercambiar orden con la lección siguiente
            $query = "UPDATE lecciones SET orden = ? WHERE id_modulo = ? AND orden = ?";
            $this->db->query($query, [$ordenActual, $moduloId, $ordenNuevo]);
            
            $query = "UPDATE lecciones SET orden = ? WHERE id_leccion = ?";
            $this->db->query($query, [$ordenNuevo, $id]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error("Error al mover lección: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener el siguiente número de orden para un módulo
     */
    private function getNextOrder($moduloId) {
        $query = "SELECT COALESCE(MAX(orden), 0) + 1 as next_order FROM lecciones WHERE id_modulo = ?";
        $result = $this->db->fetchOne($query, [$moduloId]);
        return $result['next_order'];
    }
    
    /**
     * Obtener lección siguiente
     */
    public function getNext($id) {
        $leccion = $this->findById($id);
        if (!$leccion) {
            return null;
        }
        
        // Intentar obtener siguiente lección del mismo módulo
        $query = "SELECT * FROM lecciones 
                  WHERE id_modulo = ? AND orden > ? 
                  ORDER BY orden ASC LIMIT 1";
        $siguiente = $this->db->fetchOne($query, [$leccion['id_modulo'], $leccion['orden']]);
        
        if ($siguiente) {
            return $siguiente;
        }
        
        // Si no hay siguiente en el módulo, buscar primera lección del siguiente módulo
        $query = "SELECT l.* FROM lecciones l
                  INNER JOIN modulos m ON l.id_modulo = m.id_modulo
                  WHERE m.id_curso = ? AND m.orden > ?
                  ORDER BY m.orden ASC, l.orden ASC LIMIT 1";
        
        $moduloModel = new Modulo();
        $modulo = $moduloModel->findById($leccion['id_modulo']);
        
        return $this->db->fetchOne($query, [$modulo['id_curso'], $modulo['orden']]);
    }
    
    /**
     * Obtener lección anterior
     */
    public function getPrevious($id) {
        $leccion = $this->findById($id);
        if (!$leccion) {
            return null;
        }
        
        // Intentar obtener lección anterior del mismo módulo
        $query = "SELECT * FROM lecciones 
                  WHERE id_modulo = ? AND orden < ? 
                  ORDER BY orden DESC LIMIT 1";
        $anterior = $this->db->fetchOne($query, [$leccion['id_modulo'], $leccion['orden']]);
        
        if ($anterior) {
            return $anterior;
        }
        
        // Si no hay anterior en el módulo, buscar última lección del módulo anterior
        $query = "SELECT l.* FROM lecciones l
                  INNER JOIN modulos m ON l.id_modulo = m.id_modulo
                  WHERE m.id_curso = ? AND m.orden < ?
                  ORDER BY m.orden DESC, l.orden DESC LIMIT 1";
        
        $moduloModel = new Modulo();
        $modulo = $moduloModel->findById($leccion['id_modulo']);
        
        return $this->db->fetchOne($query, [$modulo['id_curso'], $modulo['orden']]);
    }
    
    /**
     * Verificar si una lección existe
     */
    public function exists($id) {
        $query = "SELECT COUNT(*) as count FROM lecciones WHERE id_leccion = ?";
        $result = $this->db->fetchOne($query, [$id]);
        return $result['count'] > 0;
    }
    
    /**
     * Verificar si una lección pertenece a un módulo
     */
    public function belongsToModule($leccionId, $moduloId) {
        $query = "SELECT COUNT(*) as count FROM lecciones WHERE id_leccion = ? AND id_modulo = ?";
        $result = $this->db->fetchOne($query, [$leccionId, $moduloId]);
        return $result['count'] > 0;
    }
}
