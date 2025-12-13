<?php
/**
 * ============================================================================
 * MIDDLEWARE DE AUTENTICACIÓN
 * ============================================================================
 * Verifica que el usuario esté autenticado mediante JWT
 * ============================================================================
 */

class AuthMiddleware {
    
    /**
     * Verifica la autenticación del usuario
     * 
     * @return array|false Datos del usuario autenticado o false
     */
    public static function verify() {
        $token = self::getTokenFromRequest();
        
        if (!$token) {
            Response::unauthorized('Token no proporcionado');
            return false;
        }
        
        $payload = Security::verifyJWT($token);
        
        if (!$payload) {
            Response::unauthorized('Token inválido o expirado');
            return false;
        }
        
        // Verificar que el usuario sigue existiendo y está activo
        $usuarioModel = new Usuario();
        $user = $usuarioModel->findById($payload['user_id']);
        
        if (!$user) {
            Response::unauthorized('Usuario no encontrado');
            return false;
        }
        
        if ($user['estado'] !== 'activo') {
            Response::forbidden('Usuario inactivo o suspendido');
            return false;
        }
        
        // Actualizar último acceso
        $usuarioModel->updateLastAccess($user['id_usuario']);
        
        return $user;
    }
    
    /**
     * Verifica que el usuario tenga un rol específico
     * 
     * @param array $allowedRoles Roles permitidos
     * @return array|false Datos del usuario o false
     */
    public static function verifyRole($allowedRoles = []) {
        $user = self::verify();
        
        if (!$user) {
            return false;
        }
        
        if (!empty($allowedRoles) && !in_array($user['tipo_usuario'], $allowedRoles)) {
            Response::forbidden('No tienes permisos para acceder a este recurso');
            return false;
        }
        
        return $user;
    }
    
    /**
     * Extrae el token JWT de la solicitud
     * 
     * @return string|null Token JWT
     */
    private static function getTokenFromRequest() {
        // Intentar obtener headers de múltiples fuentes
        $authHeader = null;
        
        // Método 1: getallheaders() - case-insensitive search
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            // Buscar en case-insensitive
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $authHeader = $value;
                    break;
                }
            }
        }
        
        // Método 2: $_SERVER (Apache/Nginx)
        if (!$authHeader) {
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }
        }
        
        // Extraer el token del header Bearer
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // También verificar en parámetros GET (menos seguro, solo para desarrollo)
        if (isset($_GET['token'])) {
            return $_GET['token'];
        }
        
        return null;
    }
    
    /**
     * Middleware opcional: verifica autenticación pero no bloquea si falta
     * 
     * @return array|null Datos del usuario o null
     */
    public static function optional() {
        $token = self::getTokenFromRequest();
        
        if (!$token) {
            return null;
        }
        
        $payload = Security::verifyJWT($token);
        
        if (!$payload) {
            return null;
        }
        
        $usuarioModel = new Usuario();
        $user = $usuarioModel->findById($payload['user_id']);
        
        return $user ?: null;
    }
    
    /**
     * Obtener usuario actualmente autenticado (sin bloquear)
     * 
     * @return array|null Datos del usuario o null
     */
    public static function getCurrentUser() {
        return self::optional();
    }
    
    /**
     * Requiere autenticación (con roles opcionales)
     * 
     * @param array $allowedRoles Roles permitidos (vacío = cualquier usuario autenticado)
     * @return array Datos del usuario (termina ejecución si falla)
     */
    public static function requireAuth($allowedRoles = []) {
        $user = self::verify();
        
        if (!$user) {
            exit; // La respuesta ya fue enviada por verify()
        }
        
        if (!empty($allowedRoles)) {
            if (!in_array($user['tipo_usuario'], $allowedRoles)) {
                Response::forbidden('No tienes permisos para acceder a este recurso');
                exit;
            }
        }
        
        return $user;
    }
}
