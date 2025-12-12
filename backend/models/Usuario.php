<?php
/**
 * ============================================================================
 * MODELO DE USUARIO
 * ============================================================================
 * Gestiona las operaciones de base de datos relacionadas con usuarios
 * ============================================================================
 */

class Usuario {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crea un nuevo usuario
     * 
     * @param array $data Datos del usuario
     * @return int ID del usuario creado
     */
    public function create($data) {
        $query = "INSERT INTO usuarios (
            nombre, apellido, email, telefono, password_hash, tipo_usuario, 
            foto_perfil, biografia, ciudad, pais, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['nombre'],
            $data['apellido'],
            $data['email'],
            $data['telefono'] ?? null,
            $data['password_hash'],
            $data['tipo_usuario'] ?? 'emprendedor',
            $data['foto_perfil'] ?? null,
            $data['biografia'] ?? null,
            $data['ciudad'] ?? null,
            $data['pais'] ?? null,
            'activo'
        ];
        
        $userId = $this->db->insert($query, $params);
        
        Logger::activity($userId, 'Usuario registrado', ['email' => $data['email']]);
        
        return $userId;
    }
    
    /**
     * Obtiene un usuario por su email
     * 
     * @param string $email Email del usuario
     * @return array|false Datos del usuario o false
     */
    public function findByEmail($email) {
        $query = "SELECT * FROM usuarios WHERE email = ? LIMIT 1";
        return $this->db->fetchOne($query, [$email]);
    }
    
    /**
     * Obtiene un usuario por su ID
     * 
     * @param int $id ID del usuario
     * @return array|false Datos del usuario o false
     */
    public function findById($id) {
        $query = "SELECT id_usuario, nombre, apellido, email, telefono, tipo_usuario, 
                  foto_perfil, biografia, ciudad, pais, estado, fecha_registro, ultimo_acceso 
                  FROM usuarios WHERE id_usuario = ? LIMIT 1";
        return $this->db->fetchOne($query, [$id]);
    }
    
    /**
     * Actualiza información del usuario
     * 
     * @param int $id ID del usuario
     * @param array $data Datos a actualizar
     * @return bool
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['nombre', 'apellido', 'telefono', 'foto_perfil', 'biografia', 'ciudad', 'pais'];
        
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
        
        $query = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id_usuario = ?";
        $affected = $this->db->execute($query, $params);
        
        if ($affected > 0) {
            Logger::activity($id, 'Perfil actualizado');
        }
        
        return $affected > 0;
    }
    
    /**
     * Actualiza la contraseña de un usuario
     * 
     * @param int $id ID del usuario
     * @param string $newPasswordHash Nueva contraseña hasheada
     * @return bool
     */
    public function updatePassword($id, $newPasswordHash) {
        $query = "UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?";
        $affected = $this->db->execute($query, [$newPasswordHash, $id]);
        
        if ($affected > 0) {
            Logger::activity($id, 'Contraseña actualizada');
        }
        
        return $affected > 0;
    }
    
    /**
     * Actualiza el último acceso del usuario
     * 
     * @param int $id ID del usuario
     * @return bool
     */
    public function updateLastAccess($id) {
        $query = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?";
        return $this->db->execute($query, [$id]) > 0;
    }
    
    /**
     * Actualiza usuario desde el panel de administración
     * Permite actualizar campos adicionales como email, tipo_usuario y estado
     * 
     * @param int $id ID del usuario
     * @param array $data Datos a actualizar
     * @return bool
     */
    public function adminUpdate($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['nombre', 'apellido', 'email', 'telefono', 'tipo_usuario', 'estado', 'foto_perfil', 'biografia', 'ciudad', 'pais'];
        
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
        
        $query = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id_usuario = ?";
        $affected = $this->db->execute($query, $params);
        
        if ($affected > 0) {
            Logger::activity($id, 'Usuario actualizado por administrador');
        }
        
        return $affected > 0;
    }
    
    /**
     * Cambia el estado de un usuario
     * 
     * @param int $id ID del usuario
     * @param string $estado Nuevo estado (activo, inactivo, suspendido)
     * @return bool
     */
    public function changeStatus($id, $estado) {
        $query = "UPDATE usuarios SET estado = ? WHERE id_usuario = ?";
        $affected = $this->db->execute($query, [$estado, $id]);
        
        if ($affected > 0) {
            Logger::activity($id, 'Estado cambiado', ['nuevo_estado' => $estado]);
        }
        
        return $affected > 0;
    }
    
    /**
     * Obtiene todos los usuarios con paginación
     * 
     * @param int $page Página actual
     * @param int $limit Registros por página
     * @param array $filters Filtros opcionales
     * @return array
     */
    public function getAll($page = 1, $limit = 10, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where = ['1=1'];
        $params = [];
        
        if (isset($filters['tipo_usuario'])) {
            $where[] = "tipo_usuario = ?";
            $params[] = $filters['tipo_usuario'];
        }
        
        if (isset($filters['estado'])) {
            $where[] = "estado = ?";
            $params[] = $filters['estado'];
        }
        
        if (isset($filters['search'])) {
            $where[] = "(nombre LIKE ? OR apellido LIKE ? OR email LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Obtener total de registros
        $countQuery = "SELECT COUNT(*) as total FROM usuarios WHERE {$whereClause}";
        $countResult = $this->db->fetchOne($countQuery, $params);
        $total = $countResult['total'];
        
        // Obtener registros de la página actual
        $params[] = $offset;
        $params[] = $limit;
        
        $query = "SELECT id_usuario, nombre, apellido, email, telefono, tipo_usuario, 
                  estado, foto_perfil, fecha_registro, ultimo_acceso 
                  FROM usuarios 
                  WHERE {$whereClause}
                  ORDER BY fecha_registro DESC
                  LIMIT ?, ?";
        
        $users = $this->db->fetchAll($query, $params);
        
        return [
            'data' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Elimina un usuario (soft delete)
     * 
     * @param int $id ID del usuario
     * @return bool
     */
    public function delete($id) {
        // En lugar de eliminar, desactivamos
        return $this->changeStatus($id, 'inactivo');
    }
    
    /**
     * Verifica si un email ya existe
     * 
     * @param string $email Email a verificar
     * @param int $excludeId ID a excluir de la búsqueda (para updates)
     * @return bool
     */
    public function emailExists($email, $excludeId = null) {
        $query = "SELECT COUNT(*) as count FROM usuarios WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $query .= " AND id_usuario != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->fetchOne($query, $params);
        return $result['count'] > 0;
    }
    
    /**
     * Obtiene estadísticas de usuarios
     * 
     * @return array
     */
    public function getStatistics() {
        $stats = [];
        
        // Total de usuarios
        $query = "SELECT COUNT(*) as total FROM usuarios";
        $result = $this->db->fetchOne($query);
        $stats['total'] = $result['total'];
        
        // Usuarios por estado
        $query = "SELECT estado, COUNT(*) as count FROM usuarios GROUP BY estado";
        $results = $this->db->fetchAll($query);
        $stats['por_estado'] = [];
        foreach ($results as $row) {
            $stats['por_estado'][$row['estado']] = $row['count'];
        }
        
        // Usuarios por tipo
        $query = "SELECT tipo_usuario, COUNT(*) as count FROM usuarios GROUP BY tipo_usuario";
        $results = $this->db->fetchAll($query);
        $stats['por_tipo'] = [];
        foreach ($results as $row) {
            $stats['por_tipo'][$row['tipo_usuario']] = $row['count'];
        }
        
        // Registros recientes (últimos 30 días)
        $query = "SELECT COUNT(*) as count FROM usuarios 
                  WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = $this->db->fetchOne($query);
        $stats['registros_recientes'] = $result['count'];
        
        return $stats;
    }
    
    /**
     * Obtiene la configuración de privacidad del usuario
     * 
     * @param int $userId ID del usuario
     * @return array Configuración de privacidad
     */
    public function getPrivacySettings($userId) {
        $query = "SELECT configuracion_privacidad FROM usuarios WHERE id_usuario = ?";
        $result = $this->db->fetchOne($query, [$userId]);
        
        if ($result && $result['configuracion_privacidad']) {
            return json_decode($result['configuracion_privacidad'], true);
        }
        
        // Retornar configuración por defecto si no existe
        return [
            'perfil_publico' => true,
            'mostrar_email' => false,
            'mostrar_telefono' => false,
            'mostrar_biografia' => true,
            'mostrar_ubicacion' => true,
            'permitir_mensajes' => true
        ];
    }
    
    /**
     * Actualiza la configuración de privacidad del usuario
     * 
     * @param int $userId ID del usuario
     * @param array $settings Nueva configuración
     * @return bool
     */
    public function updatePrivacySettings($userId, $settings) {
        // Obtener configuración actual
        $currentSettings = $this->getPrivacySettings($userId);
        
        // Merge con nueva configuración (mantener valores no especificados)
        $newSettings = array_merge($currentSettings, $settings);
        
        // Validar campos booleanos
        $validFields = [
            'perfil_publico',
            'mostrar_email',
            'mostrar_telefono',
            'mostrar_biografia',
            'mostrar_ubicacion',
            'permitir_mensajes'
        ];
        
        $filteredSettings = [];
        foreach ($validFields as $field) {
            if (isset($newSettings[$field])) {
                $filteredSettings[$field] = (bool) $newSettings[$field];
            }
        }
        
        $query = "UPDATE usuarios 
                  SET configuracion_privacidad = ? 
                  WHERE id_usuario = ?";
        
        $result = $this->db->execute($query, [
            json_encode($filteredSettings),
            $userId
        ]);
        
        if ($result) {
            Logger::activity($userId, 'Configuración de privacidad actualizada');
        }
        
        return $result;
    }
    
    /**
     * Aplica filtros de privacidad a los datos del usuario
     * 
     * @param array $user Datos del usuario
     * @param int|null $viewerId ID del usuario que está viendo (null = público)
     * @return array Usuario con datos filtrados según privacidad
     */
    public function applyPrivacyFilters($user, $viewerId = null) {
        // Si es el mismo usuario o es admin, mostrar todo
        if ($viewerId && ($viewerId === $user['id_usuario'] || $this->isAdmin($viewerId))) {
            return $user;
        }
        
        // Obtener configuración de privacidad
        $privacy = $this->getPrivacySettings($user['id_usuario']);
        
        // Si el perfil no es público y no está autenticado, mostrar mínimo
        if (!$privacy['perfil_publico'] && !$viewerId) {
            return [
                'id_usuario' => $user['id_usuario'],
                'nombre' => $user['nombre'],
                'apellido' => substr($user['apellido'], 0, 1) . '.',
                'tipo_usuario' => $user['tipo_usuario'],
                'perfil_privado' => true
            ];
        }
        
        // Aplicar filtros de privacidad
        $filteredUser = $user;
        
        if (!$privacy['mostrar_email']) {
            unset($filteredUser['email']);
        }
        
        if (!$privacy['mostrar_telefono']) {
            unset($filteredUser['telefono']);
        }
        
        if (!$privacy['mostrar_biografia']) {
            unset($filteredUser['biografia']);
        }
        
        if (!$privacy['mostrar_ubicacion']) {
            unset($filteredUser['ciudad']);
            unset($filteredUser['pais']);
        }
        
        // Siempre ocultar datos sensibles
        unset($filteredUser['password_hash']);
        unset($filteredUser['configuracion_privacidad']);
        
        return $filteredUser;
    }
    
    /**
     * Actualiza el rol del usuario
     * 
     * @param int $id ID del usuario
     * @param string $nuevoRol Nuevo rol (emprendedor, empresario, mentor, administrador)
     * @return bool
     */
    public function updateRole($id, $nuevoRol) {
        $allowedRoles = ['emprendedor', 'empresario', 'mentor', 'administrador'];
        
        if (!in_array($nuevoRol, $allowedRoles)) {
            return false;
        }
        
        $query = "UPDATE usuarios SET tipo_usuario = ? WHERE id_usuario = ?";
        $affected = $this->db->execute($query, [$nuevoRol, $id]);
        
        return $affected > 0;
    }

    /**
     * Verifica si un usuario es administrador
     * 
     * @param int $userId ID del usuario
     * @return bool
     */
    private function isAdmin($userId) {
        $query = "SELECT tipo_usuario FROM usuarios WHERE id_usuario = ?";
        $result = $this->db->fetchOne($query, [$userId]);
        
        return $result && $result['tipo_usuario'] === 'administrador';
    }
}

