<?php
/**
 * ============================================================================
 * ENRUTADOR DE LA API
 * ============================================================================
 * Maneja el enrutamiento de las peticiones HTTP
 * ============================================================================
 */

class Router {
    
    private $routes = [];
    private $notFoundCallback;
    
    /**
     * Define una ruta GET
     * 
     * @param string $path Ruta
     * @param callable $callback Función a ejecutar
     */
    public function get($path, $callback) {
        $this->addRoute('GET', $path, $callback);
    }
    
    /**
     * Define una ruta POST
     * 
     * @param string $path Ruta
     * @param callable $callback Función a ejecutar
     */
    public function post($path, $callback) {
        $this->addRoute('POST', $path, $callback);
    }
    
    /**
     * Define una ruta PUT
     * 
     * @param string $path Ruta
     * @param callable $callback Función a ejecutar
     */
    public function put($path, $callback) {
        $this->addRoute('PUT', $path, $callback);
    }
    
    /**
     * Define una ruta DELETE
     * 
     * @param string $path Ruta
     * @param callable $callback Función a ejecutar
     */
    public function delete($path, $callback) {
        $this->addRoute('DELETE', $path, $callback);
    }
    
    /**
     * Agrega una ruta al enrutador
     * 
     * @param string $method Método HTTP
     * @param string $path Ruta
     * @param callable $callback Función a ejecutar
     */
    private function addRoute($method, $path, $callback) {
        $pattern = $this->convertPathToRegex($path);
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'callback' => $callback
        ];
    }
    
    /**
     * Convierte una ruta en expresión regular
     * 
     * @param string $path Ruta
     * @return string Patrón regex
     */
    private function convertPathToRegex($path) {
        // Convertir parámetros {id} en grupos de captura
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    /**
     * Define callback para ruta no encontrada
     * 
     * @param callable $callback Función a ejecutar
     */
    public function setNotFound($callback) {
        $this->notFoundCallback = $callback;
    }
    
    /**
     * Ejecuta el enrutador
     * 
     * @param string $method Método HTTP
     * @param string $uri URI solicitada
     */
    public function dispatch($method, $uri) {
        // Limpiar la URI
        $uri = strtok($uri, '?');
        $uri = rtrim($uri, '/');
        if (empty($uri)) {
            $uri = '/';
        }
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            if (preg_match($route['pattern'], $uri, $matches)) {
                // Eliminar el match completo
                array_shift($matches);
                
                // Ejecutar callback con los parámetros capturados
                call_user_func_array($route['callback'], $matches);
                return;
            }
        }
        
        // Ruta no encontrada
        if ($this->notFoundCallback) {
            call_user_func($this->notFoundCallback);
        } else {
            Response::notFound('Ruta no encontrada');
        }
    }
}
