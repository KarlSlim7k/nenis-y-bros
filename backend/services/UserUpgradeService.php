<?php
/**
 * UserUpgradeService
 * 
 * Servicio para gestionar el ascenso de usuarios (Emprendedor -> Empresario)
 */

class UserUpgradeService {
    
    private $usuarioModel;
    private $db;
    
    public function __construct() {
        $this->usuarioModel = new Usuario();
        $this->db = Database::getInstance();
    }
    
    /**
     * Valida si un usuario cumple los requisitos para ser Empresario
     * 
     * @param int $userId ID del usuario
     * @return array ['eligible' => bool, 'reason' => string]
     */
    public function checkRequirements($userId) {
        // POR AHORA: No hay requisitos restrictivos (cursos, logros, etc.)
        // La única condición implícita es que esté creando su perfil de empresa
        
        // Futura expansión: leer de configuracion_sistema
        // $config = $this->db->fetchOne("SELECT valor FROM configuracion_sistema WHERE clave = 'upgrade_requirements'");
        
        return [
            'eligible' => true,
            'reason' => 'Cumple con los requisitos básicos'
        ];
    }
    
    /**
     * Realiza el ascenso del usuario a Empresario
     * 
     * @param int $userId ID del usuario
     * @return bool
     */
    public function upgradeToEmpresario($userId) {
        $user = $this->usuarioModel->findById($userId);
        
        if (!$user) {
            Logger::error("Intento de upgrade a usuario inexistente: $userId");
            return false;
        }
        
        // Si ya es empresario o rol superior, no hacer nada
        if (in_array($user['tipo_usuario'], ['empresario', 'mentor', 'administrador'])) {
            return true;
        }
        
        // Actualizar rol
        $success = $this->usuarioModel->updateRole($userId, 'empresario');
        
        if ($success) {
            Logger::activity($userId, 'Ascenso automático a Empresario por creación de perfil');
            
            // Aquí se podría agregar lógica adicional:
            // - Otorgar logro "Empresario Oficial"
            // - Enviar email de bienvenida al nuevo rol
            // - Asignar puntos de bonificación
        }
        
        return $success;
    }
}
