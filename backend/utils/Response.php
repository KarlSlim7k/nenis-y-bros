<?php
/**
 * ============================================================================
 * CLASE PARA RESPUESTAS HTTP ESTANDARIZADAS
 * ============================================================================
 * Maneja las respuestas JSON de la API con formato consistente
 * ============================================================================
 */

class Response {
    
    /**
     * Envía una respuesta JSON exitosa
     * 
     * @param mixed $data Datos a enviar
     * @param string $message Mensaje opcional
     * @param int $statusCode Código HTTP
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Envía una respuesta JSON de error
     * 
     * @param string $message Mensaje de error
     * @param int $statusCode Código HTTP
     * @param array $errors Errores específicos opcionales
     */
    public static function error($message = 'Error', $statusCode = 400, $errors = null) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Envía una respuesta de validación fallida
     * 
     * @param array $errors Array de errores de validación
     */
    public static function validationError($errors) {
        self::error('Errores de validación', 422, $errors);
    }
    
    /**
     * Envía una respuesta de no autorizado
     * 
     * @param string $message Mensaje opcional
     */
    public static function unauthorized($message = 'No autorizado') {
        self::error($message, 401);
    }
    
    /**
     * Envía una respuesta de prohibido
     * 
     * @param string $message Mensaje opcional
     */
    public static function forbidden($message = 'Acceso denegado') {
        self::error($message, 403);
    }
    
    /**
     * Envía una respuesta de no encontrado
     * 
     * @param string $message Mensaje opcional
     */
    public static function notFound($message = 'Recurso no encontrado') {
        self::error($message, 404);
    }
    
    /**
     * Envía una respuesta de error interno del servidor
     * 
     * @param string $message Mensaje opcional
     */
    public static function serverError($message = 'Error interno del servidor') {
        self::error($message, 500);
    }
    
    /**
     * Envía una respuesta de petición incorrecta
     * 
     * @param string $message Mensaje opcional
     * @param array $errors Errores específicos opcionales
     */
    public static function badRequest($message = 'Petición incorrecta', $errors = null) {
        self::error($message, 400, $errors);
    }
    
    /**
     * Envía una respuesta de creación exitosa
     * 
     * @param mixed $data Datos del recurso creado
     * @param string $message Mensaje opcional
     */
    public static function created($data, $message = 'Recurso creado exitosamente') {
        self::success($data, $message, 201);
    }
    
    /**
     * Envía una respuesta sin contenido
     * 
     * @param string $message Mensaje opcional
     */
    public static function noContent($message = 'Operación exitosa') {
        http_response_code(204);
        exit;
    }
}
