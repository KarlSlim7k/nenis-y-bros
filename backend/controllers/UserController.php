<?php
/**
 * ============================================================================
 * CONTROLADOR DE USUARIOS
 * ============================================================================
 * Gestiona operaciones CRUD de usuarios y perfiles
 * ============================================================================
 */

class UserController {
    
    private $usuarioModel;
    
    public function __construct() {
        $this->usuarioModel = new Usuario();
    }
    
    /**
     * Obtiene el perfil del usuario autenticado
     * 
     * GET /api/users/profile
     * Headers: Authorization: Bearer {token}
     */
    public function getProfile() {
        $user = AuthMiddleware::verify();
        
        if (!$user) {
            return;
        }
        
        Response::success([
            'user' => $this->formatUserResponse($user)
        ]);
    }
    
    /**
     * Actualiza el perfil del usuario autenticado
     * 
     * PUT /api/users/profile
     * Headers: Authorization: Bearer {token}
     * Body: nombre, apellido, telefono, biografia, ciudad, pais
     */
    public function updateProfile() {
        $user = AuthMiddleware::verify();
        
        if (!$user) {
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos
        $validator = new Validator($data, [
            'nombre' => 'min:2|max:100',
            'apellido' => 'min:2|max:100',
            'telefono' => 'phone',
            'biografia' => 'max:1000',
            'ciudad' => 'max:100',
            'pais' => 'max:100'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $updated = $this->usuarioModel->update($user['id_usuario'], $data);
            
            if (!$updated) {
                Response::error('No se pudo actualizar el perfil', 400);
            }
            
            // Obtener datos actualizados
            $updatedUser = $this->usuarioModel->findById($user['id_usuario']);
            
            Response::success([
                'user' => $this->formatUserResponse($updatedUser)
            ], 'Perfil actualizado exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al actualizar perfil: ' . $e->getMessage());
            Response::serverError('Error al actualizar perfil');
        }
    }
    
    /**
     * Sube foto de perfil
     * 
     * POST /api/users/profile/photo
     * Headers: Authorization: Bearer {token}
     * FormData: photo (file)
     */
    public function uploadPhoto() {
        $user = AuthMiddleware::verify();
        
        if (!$user) {
            return;
        }
        
        if (!isset($_FILES['photo'])) {
            Response::error('No se proporcionó ninguna imagen', 400);
        }
        
        $file = $_FILES['photo'];
        
        // Validar archivo
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = UPLOAD_MAX_SIZE;
        
        if (!in_array($file['type'], $allowedTypes)) {
            Response::error('Formato de imagen no válido. Usa JPG, PNG o GIF', 400);
        }
        
        if ($file['size'] > $maxSize) {
            Response::error('La imagen excede el tamaño máximo permitido', 400);
        }
        
        try {
            // Generar nombre único
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user['id_usuario'] . '_' . time() . '.' . $extension;
            $uploadPath = UPLOAD_PATH . 'profiles/' . $filename;
            
            // Eliminar foto anterior si existe
            if (!empty($user['foto_perfil'])) {
                $oldPhoto = UPLOAD_PATH . 'profiles/' . basename($user['foto_perfil']);
                if (file_exists($oldPhoto)) {
                    unlink($oldPhoto);
                }
            }
            
            // Mover archivo
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                Response::serverError('Error al subir la imagen');
            }
            
            // Actualizar en base de datos
            $this->usuarioModel->update($user['id_usuario'], [
                'foto_perfil' => $filename
            ]);
            
            Logger::activity($user['id_usuario'], 'Foto de perfil actualizada');
            
            Response::success([
                'photo_url' => APP_URL . '/uploads/profiles/' . $filename
            ], 'Foto de perfil actualizada exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al subir foto: ' . $e->getMessage());
            Response::serverError('Error al subir foto de perfil');
        }
    }
    
    /**
     * Obtiene un usuario por ID (público)
     * 
     * GET /api/users/{id}
     */
    public function getUserById($id) {
        try {
            $user = $this->usuarioModel->findById($id);
            
            if (!$user) {
                Response::notFound('Usuario no encontrado');
            }
            
            // Obtener ID del usuario que está viendo (si está autenticado)
            $viewerId = null;
            $token = $this->getTokenFromRequest();
            if ($token) {
                $payload = Security::verifyJWT($token);
                if ($payload) {
                    $viewerId = $payload['user_id'];
                }
            }
            
            // Aplicar filtros de privacidad
            $filteredUser = $this->usuarioModel->applyPrivacyFilters($user, $viewerId);
            
            Response::success(['user' => $this->formatUserResponse($filteredUser)]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener usuario: ' . $e->getMessage());
            Response::serverError('Error al obtener usuario');
        }
    }
    
    /**
     * Obtiene la configuración de privacidad del usuario autenticado
     * 
     * GET /api/users/privacy-settings
     * Headers: Authorization: Bearer {token}
     */
    public function getPrivacySettings() {
        $user = AuthMiddleware::verify();
        
        if (!$user) {
            return;
        }
        
        try {
            $settings = $this->usuarioModel->getPrivacySettings($user['id_usuario']);
            
            Response::success([
                'privacy_settings' => $settings
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener configuración de privacidad: ' . $e->getMessage());
            Response::serverError('Error al obtener configuración');
        }
    }
    
    /**
     * Actualiza la configuración de privacidad del usuario autenticado
     * 
     * PUT /api/users/privacy-settings
     * Headers: Authorization: Bearer {token}
     * Body: perfil_publico, mostrar_email, mostrar_telefono, mostrar_biografia, mostrar_ubicacion, permitir_mensajes
     */
    public function updatePrivacySettings() {
        $user = AuthMiddleware::verify();
        
        if (!$user) {
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar que al menos un campo esté presente
        $validFields = [
            'perfil_publico',
            'mostrar_email',
            'mostrar_telefono',
            'mostrar_biografia',
            'mostrar_ubicacion',
            'permitir_mensajes'
        ];
        
        $hasValidField = false;
        foreach ($validFields as $field) {
            if (isset($data[$field])) {
                $hasValidField = true;
                break;
            }
        }
        
        if (!$hasValidField) {
            Response::badRequest('Debe proporcionar al menos un campo de configuración');
        }
        
        try {
            // Actualizar configuración
            $result = $this->usuarioModel->updatePrivacySettings($user['id_usuario'], $data);
            
            if (!$result) {
                Response::serverError('Error al actualizar configuración');
            }
            
            // Obtener configuración actualizada
            $updatedSettings = $this->usuarioModel->getPrivacySettings($user['id_usuario']);
            
            Response::success([
                'message' => 'Configuración de privacidad actualizada exitosamente',
                'privacy_settings' => $updatedSettings
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al actualizar configuración de privacidad: ' . $e->getMessage());
            Response::serverError('Error al actualizar configuración');
        }
    }
    
    /**
     * Formatea la respuesta del usuario
     * 
     * @param array $user Datos del usuario
     * @return array Usuario formateado
     */
    private function formatUserResponse($user) {
        // No eliminar password_hash si ya fue eliminado por privacidad
        if (isset($user['password_hash'])) {
            unset($user['password_hash']);
        }
        
        // Agregar URL completa de la foto de perfil
        if (!empty($user['foto_perfil'])) {
            $user['foto_perfil_url'] = APP_URL . '/uploads/profiles/' . $user['foto_perfil'];
        } else {
            $user['foto_perfil_url'] = null;
        }
        
        return $user;
    }
    
    /**
     * Extrae el token JWT de la solicitud
     * 
     * @return string|null Token JWT
     */
    private function getTokenFromRequest() {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
}

