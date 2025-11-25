<?php

/**
 * Cache - Redis Cache Manager
 * 
 * Gestión de caché con Redis para optimizar consultas frecuentes
 * Requiere: extension php_redis y Redis server corriendo en localhost:6379
 */
class Cache {
    private static $instance = null;
    private $redis = null;
    private $enabled = false;
    private $defaultTTL = 600; // 10 minutos por defecto
    
    private function __construct() {
        $this->enabled = extension_loaded('redis') && CACHE_ENABLED;
        
        if ($this->enabled) {
            try {
                $this->redis = new Redis();
                $this->redis->connect(REDIS_HOST, REDIS_PORT);
                
                // Autenticación si está configurada
                if (defined('REDIS_PASSWORD') && REDIS_PASSWORD) {
                    $this->redis->auth(REDIS_PASSWORD);
                }
                
                // Seleccionar base de datos
                $this->redis->select(REDIS_DB);
                
                // Test de conexión
                $this->redis->ping();
                
            } catch (Exception $e) {
                Logger::error('Redis connection failed: ' . $e->getMessage());
                $this->enabled = false;
                $this->redis = null;
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Verificar si el caché está habilitado y funcionando
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * Obtener valor del caché
     */
    public function get($key) {
        if (!$this->enabled) {
            return null;
        }
        
        try {
            $value = $this->redis->get($this->prefixKey($key));
            
            if ($value === false) {
                return null;
            }
            
            // Intentar deserializar JSON
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
            
        } catch (Exception $e) {
            Logger::error('Cache get error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Guardar valor en el caché
     */
    public function set($key, $value, $ttl = null) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            $ttl = $ttl ?? $this->defaultTTL;
            
            // Serializar a JSON si no es string
            $serialized = is_string($value) ? $value : json_encode($value);
            
            return $this->redis->setex($this->prefixKey($key), $ttl, $serialized);
            
        } catch (Exception $e) {
            Logger::error('Cache set error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Eliminar valor del caché
     */
    public function delete($key) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->del($this->prefixKey($key)) > 0;
        } catch (Exception $e) {
            Logger::error('Cache delete error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Eliminar múltiples claves por patrón
     */
    public function deletePattern($pattern) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            $keys = $this->redis->keys($this->prefixKey($pattern));
            
            if (empty($keys)) {
                return true;
            }
            
            return $this->redis->del($keys) > 0;
            
        } catch (Exception $e) {
            Logger::error('Cache deletePattern error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar si una clave existe
     */
    public function exists($key) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->exists($this->prefixKey($key)) > 0;
        } catch (Exception $e) {
            Logger::error('Cache exists error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Incrementar un contador
     */
    public function increment($key, $amount = 1) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->incrBy($this->prefixKey($key), $amount);
        } catch (Exception $e) {
            Logger::error('Cache increment error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decrementar un contador
     */
    public function decrement($key, $amount = 1) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->decrBy($this->prefixKey($key), $amount);
        } catch (Exception $e) {
            Logger::error('Cache decrement error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpiar todo el caché
     */
    public function flush() {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->flushDB();
        } catch (Exception $e) {
            Logger::error('Cache flush error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener estadísticas del caché
     */
    public function getStats() {
        if (!$this->enabled) {
            return [
                'enabled' => false,
                'message' => 'Cache is disabled'
            ];
        }
        
        try {
            $info = $this->redis->info();
            
            return [
                'enabled' => true,
                'keys' => $this->redis->dbSize(),
                'used_memory' => $info['used_memory_human'] ?? 'N/A',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'total_commands' => $info['total_commands_processed'] ?? 0,
                'hits' => $info['keyspace_hits'] ?? 0,
                'misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info)
            ];
            
        } catch (Exception $e) {
            Logger::error('Cache getStats error: ' . $e->getMessage());
            return [
                'enabled' => true,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Ejecutar método con caché (cache-aside pattern)
     */
    public function remember($key, $callback, $ttl = null) {
        // Intentar obtener del caché
        $cached = $this->get($key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        // Si no existe, ejecutar callback
        $value = $callback();
        
        // Guardar en caché
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Invalidar caché de recursos
     */
    public function invalidateResources($idRecurso = null) {
        if ($idRecurso) {
            // Invalidar recurso específico
            $this->delete("recurso:$idRecurso");
            $this->delete("recurso:slug:*");
            $this->delete("recursos:relacionados:$idRecurso");
            $this->delete("calificaciones:$idRecurso");
        }
        
        // Invalidar listados
        $this->deletePattern('recursos:list:*');
        $this->deletePattern('recursos:destacados:*');
        $this->deletePattern('recursos:busqueda:*');
        
        // Invalidar estadísticas
        $this->deletePattern('recursos:stats:*');
    }
    
    /**
     * Invalidar caché de categorías
     */
    public function invalidateCategories($idCategoria = null) {
        if ($idCategoria) {
            $this->delete("categoria:$idCategoria");
        }
        
        $this->deletePattern('categorias:*');
        $this->invalidateResources(); // Categorías afectan listados de recursos
    }
    
    /**
     * Agregar prefijo a las claves
     */
    private function prefixKey($key) {
        return CACHE_PREFIX . ':' . $key;
    }
    
    /**
     * Calcular tasa de aciertos del caché
     */
    private function calculateHitRate($info) {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        if ($total === 0) {
            return '0%';
        }
        
        $rate = ($hits / $total) * 100;
        return number_format($rate, 2) . '%';
    }
    
    /**
     * Prevenir clonación
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
