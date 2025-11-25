<?php
/**
 * ============================================================================
 * UTILIDADES DE SEGURIDAD Y ENCRIPTACIÓN
 * ============================================================================
 * Funciones para hash de contraseñas, generación de tokens, etc.
 * ============================================================================
 */

class Security {
    
    /**
     * Genera un hash seguro de una contraseña
     * 
     * @param string $password Contraseña en texto plano
     * @return string Hash de la contraseña
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verifica una contraseña contra su hash
     * 
     * @param string $password Contraseña en texto plano
     * @param string $hash Hash almacenado
     * @return bool
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Genera un token aleatorio seguro
     * 
     * @param int $length Longitud del token
     * @return string Token generado
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Genera un JWT (JSON Web Token)
     * 
     * @param array $payload Datos a incluir en el token
     * @param int $expiresIn Tiempo de expiración en segundos
     * @return string Token JWT
     */
    public static function generateJWT($payload, $expiresIn = 7200) {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiresIn;
        
        $base64UrlHeader = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac(
            'sha256',
            $base64UrlHeader . "." . $base64UrlPayload,
            JWT_SECRET,
            true
        );
        
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    /**
     * Verifica y decodifica un JWT
     * 
     * @param string $jwt Token JWT
     * @return array|false Payload decodificado o false si es inválido
     */
    public static function verifyJWT($jwt) {
        $tokenParts = explode('.', $jwt);
        
        if (count($tokenParts) !== 3) {
            return false;
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $tokenParts;
        
        $signature = self::base64UrlDecode($base64UrlSignature);
        $expectedSignature = hash_hmac(
            'sha256',
            $base64UrlHeader . "." . $base64UrlPayload,
            JWT_SECRET,
            true
        );
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        $payload = json_decode(self::base64UrlDecode($base64UrlPayload), true);
        
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Codifica en base64 URL-safe
     * 
     * @param string $data Datos a codificar
     * @return string Datos codificados
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Decodifica base64 URL-safe
     * 
     * @param string $data Datos a decodificar
     * @return string Datos decodificados
     */
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Sanitiza una cadena para prevenir XSS
     * 
     * @param string $data Datos a sanitizar
     * @return string Datos sanitizados
     */
    public static function sanitizeXSS($data) {
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Genera un código de recuperación de contraseña
     * 
     * @return string Código de 6 dígitos
     */
    public static function generateRecoveryCode() {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verifica si una solicitud proviene de una fuente confiable (CSRF protection)
     * 
     * @return bool
     */
    public static function verifyCSRFToken() {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
        
        if (!$token || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Genera un token CSRF y lo almacena en la sesión
     * 
     * @return string Token CSRF
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = self::generateToken(32);
        $_SESSION['csrf_token'] = $token;
        
        return $token;
    }
}
