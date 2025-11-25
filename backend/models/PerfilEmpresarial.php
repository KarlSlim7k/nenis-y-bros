<?php
/**
 * ============================================================================
 * MODELO: PERFIL EMPRESARIAL
 * ============================================================================
 * Gestiona los perfiles de negocios de los usuarios
 * Fase 3 - Perfiles Empresariales y Diagnósticos
 * ============================================================================
 */

class PerfilEmpresarial {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear un nuevo perfil empresarial
     */
    public function create($data) {
        $query = "INSERT INTO perfiles_empresariales (
            id_usuario, nombre_empresa, logo_empresa, eslogan, descripcion,
            sector, tipo_negocio, etapa_negocio, anio_fundacion, numero_empleados, facturacion_anual,
            email_empresa, telefono_empresa, sitio_web,
            direccion, ciudad, estado, pais, codigo_postal,
            redes_sociales, perfil_publico
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['id_usuario'],
            $data['nombre_empresa'],
            $data['logo_empresa'] ?? null,
            $data['eslogan'] ?? null,
            $data['descripcion'] ?? null,
            $data['sector'] ?? null,
            $data['tipo_negocio'] ?? 'emprendimiento',
            $data['etapa_negocio'] ?? 'idea',
            $data['anio_fundacion'] ?? null,
            $data['numero_empleados'] ?? 0,
            $data['facturacion_anual'] ?? null,
            $data['email_empresa'] ?? null,
            $data['telefono_empresa'] ?? null,
            $data['sitio_web'] ?? null,
            $data['direccion'] ?? null,
            $data['ciudad'] ?? null,
            $data['estado'] ?? null,
            $data['pais'] ?? null,
            $data['codigo_postal'] ?? null,
            isset($data['redes_sociales']) ? json_encode($data['redes_sociales']) : null,
            $data['perfil_publico'] ?? true
        ];
        
        try {
            $perfilId = $this->db->insert($query, $params);
            if ($perfilId) {
                Logger::activity($data['id_usuario'], "Perfil empresarial creado", [
                    'id_perfil' => $perfilId,
                    'nombre_empresa' => $data['nombre_empresa']
                ]);
                return $perfilId;
            }
            return false;
        } catch (Exception $e) {
            Logger::error("Error al crear perfil empresarial: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener un perfil por ID
     */
    public function findById($id) {
        $query = "SELECT 
            p.*,
            u.nombre as usuario_nombre,
            u.apellido as usuario_apellido,
            u.email as usuario_email,
            u.foto_perfil as usuario_foto
        FROM perfiles_empresariales p
        LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE p.id_perfil = ?";
        
        $perfil = $this->db->fetchOne($query, [$id]);
        
        if ($perfil && $perfil['redes_sociales']) {
            $perfil['redes_sociales'] = json_decode($perfil['redes_sociales'], true);
        }
        
        return $perfil;
    }
    
    /**
     * Obtener perfil de un usuario
     */
    public function findByUser($userId) {
        $query = "SELECT * FROM perfiles_empresariales WHERE id_usuario = ?";
        $perfil = $this->db->fetchOne($query, [$userId]);
        
        if ($perfil && $perfil['redes_sociales']) {
            $perfil['redes_sociales'] = json_decode($perfil['redes_sociales'], true);
        }
        
        return $perfil;
    }
    
    /**
     * Obtener todos los perfiles con filtros
     */
    public function findAll($filters = [], $page = 1, $limit = 12) {
        $conditions = ["p.perfil_publico = TRUE"];
        $params = [];
        
        // Filtro por sector
        if (!empty($filters['sector'])) {
            $conditions[] = "p.sector = ?";
            $params[] = $filters['sector'];
        }
        
        // Filtro por tipo de negocio
        if (!empty($filters['tipo_negocio'])) {
            $conditions[] = "p.tipo_negocio = ?";
            $params[] = $filters['tipo_negocio'];
        }
        
        // Filtro por etapa
        if (!empty($filters['etapa_negocio'])) {
            $conditions[] = "p.etapa_negocio = ?";
            $params[] = $filters['etapa_negocio'];
        }
        
        // Filtro por ubicación
        if (!empty($filters['ciudad'])) {
            $conditions[] = "p.ciudad LIKE ?";
            $params[] = "%{$filters['ciudad']}%";
        }
        
        if (!empty($filters['pais'])) {
            $conditions[] = "p.pais = ?";
            $params[] = $filters['pais'];
        }
        
        // Búsqueda por texto
        if (!empty($filters['search'])) {
            $conditions[] = "(p.nombre_empresa LIKE ? OR p.descripcion LIKE ? OR p.sector LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $where = implode(" AND ", $conditions);
        
        // Conteo total
        $countQuery = "SELECT COUNT(*) as total FROM perfiles_empresariales p WHERE $where";
        $totalResult = $this->db->fetchOne($countQuery, $params);
        $total = $totalResult['total'];
        
        // Query principal
        $offset = ($page - 1) * $limit;
        
        $orderBy = "p.fecha_creacion DESC";
        if (!empty($filters['order_by'])) {
            switch ($filters['order_by']) {
                case 'nombre':
                    $orderBy = "p.nombre_empresa ASC";
                    break;
                case 'sector':
                    $orderBy = "p.sector ASC, p.nombre_empresa ASC";
                    break;
            }
        }
        
        $query = "SELECT 
            p.*,
            u.nombre as usuario_nombre,
            u.apellido as usuario_apellido
        FROM perfiles_empresariales p
        LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE $where
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $perfiles = $this->db->fetchAll($query, $params);
        
        // Decodificar JSON
        foreach ($perfiles as &$perfil) {
            if ($perfil['redes_sociales']) {
                $perfil['redes_sociales'] = json_decode($perfil['redes_sociales'], true);
            }
        }
        
        return [
            'data' => $perfiles,
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
     * Actualizar perfil empresarial
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'nombre_empresa', 'logo_empresa', 'eslogan', 'descripcion',
            'sector', 'tipo_negocio', 'etapa_negocio', 'anio_fundacion', 
            'numero_empleados', 'facturacion_anual',
            'email_empresa', 'telefono_empresa', 'sitio_web',
            'direccion', 'ciudad', 'estado', 'pais', 'codigo_postal',
            'perfil_publico'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        // Redes sociales (JSON)
        if (array_key_exists('redes_sociales', $data)) {
            $fields[] = "redes_sociales = ?";
            $params[] = json_encode($data['redes_sociales']);
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        
        $query = "UPDATE perfiles_empresariales SET " . implode(", ", $fields) . " WHERE id_perfil = ?";
        
        try {
            $result = $this->db->query($query, $params);
            if ($result) {
                Logger::activity("Perfil empresarial actualizado (ID: $id)", $data['id_usuario'] ?? null);
            }
            return $result;
        } catch (Exception $e) {
            Logger::error("Error al actualizar perfil empresarial: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Eliminar perfil empresarial
     */
    public function delete($id) {
        try {
            // Obtener datos del perfil antes de eliminar para el log
            $perfil = $this->findById($id);
            
            $query = "DELETE FROM perfiles_empresariales WHERE id_perfil = ?";
            $result = $this->db->query($query, [$id]);
            
            if ($result && $perfil) {
                Logger::activity($perfil['id_usuario'], "Perfil empresarial eliminado", ['id_perfil' => $id]);
            }
            
            return $result;
        } catch (Exception $e) {
            Logger::error("Error al eliminar perfil empresarial: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verificar si un perfil existe
     */
    public function exists($id) {
        $query = "SELECT COUNT(*) as count FROM perfiles_empresariales WHERE id_perfil = ?";
        $result = $this->db->fetchOne($query, [$id]);
        return $result['count'] > 0;
    }
    
    /**
     * Verificar si un perfil pertenece a un usuario
     */
    public function belongsToUser($perfilId, $userId) {
        $query = "SELECT COUNT(*) as count FROM perfiles_empresariales WHERE id_perfil = ? AND id_usuario = ?";
        $result = $this->db->fetchOne($query, [$perfilId, $userId]);
        return $result['count'] > 0;
    }
    
    /**
     * Obtener estadísticas de perfiles
     */
    public function getStats() {
        $query = "SELECT 
            COUNT(*) as total_perfiles,
            COUNT(CASE WHEN tipo_negocio = 'emprendimiento' THEN 1 END) as emprendimientos,
            COUNT(CASE WHEN tipo_negocio = 'microempresa' THEN 1 END) as microempresas,
            COUNT(CASE WHEN tipo_negocio = 'pequeña_empresa' THEN 1 END) as pequenas_empresas,
            COUNT(CASE WHEN etapa_negocio = 'idea' THEN 1 END) as en_idea,
            COUNT(CASE WHEN etapa_negocio = 'inicio' THEN 1 END) as en_inicio,
            COUNT(CASE WHEN etapa_negocio = 'crecimiento' THEN 1 END) as en_crecimiento,
            COUNT(DISTINCT sector) as sectores_diferentes
        FROM perfiles_empresariales";
        
        return $this->db->fetchOne($query);
    }
    
    /**
     * Obtener sectores disponibles
     */
    public function getSectores() {
        $query = "SELECT DISTINCT sector, COUNT(*) as total 
                  FROM perfiles_empresariales 
                  WHERE sector IS NOT NULL 
                  GROUP BY sector 
                  ORDER BY total DESC";
        
        return $this->db->fetchAll($query);
    }
}
