<?php
/**
 * ============================================================================
 * CLASE PARA LOGGING DEL SISTEMA
 * ============================================================================
 * Registra eventos, errores y actividades del sistema
 * ============================================================================
 */

class Logger {
    
    private static $logPath;
    
    /**
     * Inicializa el logger
     */
    public static function init() {
        self::$logPath = LOG_PATH;
        
        // Crear directorio de logs si no existe
        if (!file_exists(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
    }
    
    /**
     * Registra un mensaje informativo
     * 
     * @param string $message Mensaje a registrar
     * @param array $context Contexto adicional
     */
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    /**
     * Registra un mensaje de error
     * 
     * @param string $message Mensaje a registrar
     * @param array $context Contexto adicional
     */
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * Registra un mensaje de advertencia
     * 
     * @param string $message Mensaje a registrar
     * @param array $context Contexto adicional
     */
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Registra un mensaje de debug
     * 
     * @param string $message Mensaje a registrar
     * @param array $context Contexto adicional
     */
    public static function debug($message, $context = []) {
        if (APP_DEBUG) {
            self::log('DEBUG', $message, $context);
        }
    }
    
    /**
     * Registra actividad de usuarios
     * 
     * @param int $userId ID del usuario
     * @param string $action Acción realizada
     * @param array $details Detalles adicionales
     */
    public static function activity($userId, $action, $details = []) {
        $message = "User ID: {$userId} - Action: {$action}";
        self::log('ACTIVITY', $message, $details, 'activity');
    }
    
    /**
     * Método privado para escribir en el log
     * 
     * @param string $level Nivel del log
     * @param string $message Mensaje
     * @param array $context Contexto
     * @param string $type Tipo de log
     */
    private static function log($level, $message, $context = [], $type = 'app') {
        if (self::$logPath === null) {
            self::init();
        }
        
        $date = date('Y-m-d');
        $timestamp = date('Y-m-d H:i:s');
        $logFile = self::$logPath . "{$type}_{$date}.log";
        
        $logMessage = "[{$timestamp}] [{$level}] {$message}";
        
        if (!empty($context)) {
            $logMessage .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        $logMessage .= "\n";
        
        error_log($logMessage, 3, $logFile);
    }
    
    /**
     * Limpia logs antiguos (más de 30 días)
     */
    public static function cleanup($days = 30) {
        if (self::$logPath === null) {
            self::init();
        }
        
        $files = glob(self::$logPath . '*.log');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    unlink($file);
                }
            }
        }
    }
}

// Inicializar el logger
Logger::init();
