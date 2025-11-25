<?php
/**
 * ============================================================================
 * CONTROLADOR DE AUTENTICACIÓN
 * ============================================================================
 * Maneja registro, login, logout y recuperación de contraseña
 * ============================================================================
 */

class AuthController {
    
    private $usuarioModel;
    private $cuestionarioModel;
    private $inscripcionModel;
    
    public function __construct() {
        $this->usuarioModel = new Usuario();
        // Inicializar modelos si existen (para evitar errores si no se han cargado)
        if (class_exists('CuestionarioInicial')) {
            $this->cuestionarioModel = new CuestionarioInicial();
        }
        if (class_exists('Inscripcion')) {
            $this->inscripcionModel = new Inscripcion();
        }
    }
    
    /**
     * Registro de nuevo usuario
     * 
     * POST /api/auth/register
     * Body: nombre, apellido, email, password, password_confirmation, telefono, tipo_usuario
     */
    public function register() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos
        $validator = new Validator($data, [
            'nombre' => 'required|min:2|max:100',
            'apellido' => 'required|min:2|max:100',
            'email' => 'required|email|unique:usuarios,email',
            'password' => 'required|min:8|confirmed',
            'telefono' => 'phone',
            'tipo_usuario' => 'in:emprendedor,empresario,mentor'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            // Hashear contraseña
            $data['password_hash'] = Security::hashPassword($data['password']);
            
            // Crear usuario
            $userId = $this->usuarioModel->create($data);
            
            // Obtener datos del usuario creado
            $user = $this->usuarioModel->findById($userId);
            
            // Generar token JWT
            $token = Security::generateJWT([
                'user_id' => $user['id_usuario'],
                'email' => $user['email'],
                'tipo_usuario' => $user['tipo_usuario']
            ]);
            
            // PROCESAMIENTO DE ONBOARDING
            if (isset($data['token_cuestionario']) && !empty($data['token_cuestionario']) && $this->cuestionarioModel) {
                try {
                    // Buscar cuestionario
                    $cuestionario = $this->cuestionarioModel->findByToken($data['token_cuestionario']);
                    
                    if ($cuestionario) {
                        // Asociar al usuario
                        $this->cuestionarioModel->asociarUsuario($data['token_cuestionario'], $userId);
                        
                        // Inscripción automática si se solicita
                        if (isset($data['auto_enroll']) && $data['auto_enroll'] && $this->inscripcionModel) {
                            $cursosRecomendados = $this->cuestionarioModel->getCursosRecomendados($cuestionario['nivel_determinado']);
                            
                            foreach ($cursosRecomendados as $curso) {
                                $this->inscripcionModel->enroll($userId, $curso['id_curso']);
                            }
                            
                            Logger::activity($userId, 'Inscripción automática post-onboarding completada');
                        }
                    }
                } catch (Exception $e) {
                    // No interrumpir el registro si falla el onboarding
                    Logger::error('Error procesando onboarding en registro: ' . $e->getMessage());
                }
            }
            
            Response::created([
                'user' => $this->formatUserResponse($user),
                'token' => $token
            ], 'Usuario registrado exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error en registro: ' . $e->getMessage());
            Response::serverError('Error al registrar usuario');
        }
    }
    
    /**
     * Login de usuario
     * 
     * POST /api/auth/login
     * Body: email, password
     */
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos
        $validator = new Validator($data, [
            'email' => 'required|email',
            'password' => 'required'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            // Buscar usuario por email
            $user = $this->usuarioModel->findByEmail($data['email']);
            
            if (!$user) {
                Response::unauthorized('Credenciales inválidas');
            }
            
            // Verificar contraseña
            if (!Security::verifyPassword($data['password'], $user['password_hash'])) {
                Logger::warning('Intento de login fallido', ['email' => $data['email']]);
                Response::unauthorized('Credenciales inválidas');
            }
            
            // Verificar estado del usuario
            if ($user['estado'] !== 'activo') {
                Response::forbidden('Usuario inactivo o suspendido');
            }
            
            // Actualizar último acceso
            $this->usuarioModel->updateLastAccess($user['id_usuario']);
            
            // Generar token JWT
            $token = Security::generateJWT([
                'user_id' => $user['id_usuario'],
                'email' => $user['email'],
                'tipo_usuario' => $user['tipo_usuario']
            ]);
            
            Logger::activity($user['id_usuario'], 'Login exitoso');
            
            Response::success([
                'user' => $this->formatUserResponse($user),
                'token' => $token
            ], 'Login exitoso');
            
        } catch (Exception $e) {
            Logger::error('Error en login: ' . $e->getMessage());
            Response::serverError('Error al iniciar sesión');
        }
    }
    
    /**
     * Obtiene información del usuario autenticado
     * 
     * GET /api/auth/me
     * Headers: Authorization: Bearer {token}
     */
    public function me() {
        $user = AuthMiddleware::verify();
        
        if (!$user) {
            return;
        }
        
        Response::success([
            'user' => $this->formatUserResponse($user)
        ]);
    }
    
    /**
     * Logout de usuario
     * 
     * POST /api/auth/logout
     * Headers: Authorization: Bearer {token}
     */
    public function logout() {
        $user = AuthMiddleware::verify();
        
        if (!$user) {
            return;
        }
        
        Logger::activity($user['id_usuario'], 'Logout');
        
        Response::success(null, 'Sesión cerrada exitosamente');
    }
    
    /**
     * Solicita recuperación de contraseña
     * 
     * POST /api/auth/forgot-password
     * Body: email
     */
    public function forgotPassword() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar email
        $validator = new Validator($data, [
            'email' => 'required|email'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $user = $this->usuarioModel->findByEmail($data['email']);
            
            // Por seguridad, siempre respondemos exitosamente aunque el email no exista
            if ($user) {
                $recoveryCode = Security::generateRecoveryCode();
                
                // TODO: Guardar código en una tabla de recuperación con expiración
                // TODO: Enviar email con el código
                
                Logger::activity($user['id_usuario'], 'Solicitud de recuperación de contraseña');
            }
            
            Response::success(null, 'Si el email existe, recibirás instrucciones para recuperar tu contraseña');
            
        } catch (Exception $e) {
            Logger::error('Error en forgot password: ' . $e->getMessage());
            Response::serverError('Error al procesar solicitud');
        }
    }
    
    /**
     * Restablece la contraseña con código de recuperación
     * 
     * POST /api/auth/reset-password
     * Body: email, code, password, password_confirmation
     */
    public function resetPassword() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos
        $validator = new Validator($data, [
            'email' => 'required|email',
            'code' => 'required',
            'password' => 'required|min:8|confirmed'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            // TODO: Verificar código de recuperación en la tabla correspondiente
            // TODO: Verificar que no haya expirado
            
            $user = $this->usuarioModel->findByEmail($data['email']);
            
            if (!$user) {
                Response::error('Código de recuperación inválido', 400);
            }
            
            // Actualizar contraseña
            $newPasswordHash = Security::hashPassword($data['password']);
            $this->usuarioModel->updatePassword($user['id_usuario'], $newPasswordHash);
            
            Logger::activity($user['id_usuario'], 'Contraseña restablecida');
            
            Response::success(null, 'Contraseña restablecida exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error en reset password: ' . $e->getMessage());
            Response::serverError('Error al restablecer contraseña');
        }
    }
    
    /**
     * Cambia la contraseña del usuario autenticado
     * 
     * POST /api/auth/change-password
     * Headers: Authorization: Bearer {token}
     * Body: current_password, new_password, new_password_confirmation
     */
    public function changePassword() {
        $user = AuthMiddleware::verify();
        
        if (!$user) {
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos
        $validator = new Validator($data, [
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            // Obtener datos completos del usuario (con password_hash)
            $fullUser = $this->usuarioModel->findByEmail($user['email']);
            
            // Verificar contraseña actual
            if (!Security::verifyPassword($data['current_password'], $fullUser['password_hash'])) {
                Response::error('Contraseña actual incorrecta', 400);
            }
            
            // Actualizar contraseña
            $newPasswordHash = Security::hashPassword($data['new_password']);
            $this->usuarioModel->updatePassword($user['id_usuario'], $newPasswordHash);
            
            Response::success(null, 'Contraseña actualizada exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error en change password: ' . $e->getMessage());
            Response::serverError('Error al cambiar contraseña');
        }
    }
    
    /**
     * Formatea la respuesta del usuario (omite datos sensibles)
     * 
     * @param array $user Datos del usuario
     * @return array Usuario formateado
     */
    private function formatUserResponse($user) {
        unset($user['password_hash']);
        return $user;
    }
}
