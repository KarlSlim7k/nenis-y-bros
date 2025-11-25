# Guía de Redis Cache - Sistema de Biblioteca de Recursos

## Introducción

Sistema de caché Redis implementado para optimizar el rendimiento del módulo de biblioteca de recursos. Reduce la carga en la base de datos MySQL y mejora significativamente los tiempos de respuesta para consultas frecuentes.

## Arquitectura

### Componente Principal: `Cache.php`

Clase singleton que gestiona todas las operaciones de caché con Redis:

```php
Cache::getInstance()->get($key);              // Obtener valor
Cache::getInstance()->set($key, $value, $ttl); // Guardar con TTL
Cache::getInstance()->delete($key);            // Eliminar específico
Cache::getInstance()->deletePattern($pattern); // Eliminar por patrón
Cache::getInstance()->remember($key, $ttl, $callback); // Cache-aside
```

### Configuración

Variables en `backend/config/config.php`:

```php
define('CACHE_ENABLED', false);        // Activar/desactivar caché
define('REDIS_HOST', '127.0.0.1');     // Host de Redis
define('REDIS_PORT', 6379);             // Puerto de Redis
define('REDIS_PASSWORD', '');           // Contraseña (si aplica)
define('REDIS_DB', 0);                  // Base de datos Redis (0-15)
define('CACHE_PREFIX', 'nyd');          // Prefijo para todas las claves
```

**IMPORTANTE**: Por defecto `CACHE_ENABLED` está en `false`. Para activar:
1. Instalar Redis en el servidor
2. Cambiar `CACHE_ENABLED` a `true` en `.env` o `config.php`

## Estrategias de Caché Implementadas

### 1. Cache-Aside (Lazy Loading)

Patrón usado en listados de recursos:

```php
// En Recurso::getAll()
$cacheKey = 'recursos:list:' . md5(json_encode($filters));
$cached = Cache::getInstance()->get($cacheKey);
if ($cached !== null) {
    return $cached;
}

// ... consulta a DB ...

Cache::getInstance()->set($cacheKey, $result, 600); // TTL 10 min
return $result;
```

### 2. Cache Individual

Recursos y categorías individuales:

```php
// Recurso específico - TTL 5 minutos
Cache::getInstance()->get("recurso:$id");

// Categoría específica - TTL 15 minutos
Cache::getInstance()->get("categoria:$id");
```

### 3. Cache con TTL Variable

- **Listados de recursos**: 10 minutos (600s) - datos dinámicos
- **Recursos individuales**: 5 minutos (300s) - vistas frecuentes
- **Categorías**: 15 minutos (900s) - datos más estables
- **Estadísticas globales**: 5 minutos (300s) - operaciones costosas

### 4. Invalidación Automática

#### En operaciones CUD (Create, Update, Delete):

**Al crear recurso:**
```php
// En Recurso::create()
Cache::getInstance()->invalidateResources();
```

**Al actualizar recurso:**
```php
// En Recurso::update()
Cache::getInstance()->delete("recurso:$id");
Cache::getInstance()->invalidateResources();
```

**Al eliminar recurso:**
```php
// En Recurso::delete()
Cache::getInstance()->delete("recurso:$id");
Cache::getInstance()->invalidateResources();
```

**Operaciones en categorías:**
```php
// En CategoriaRecurso::create/update/delete()
Cache::getInstance()->delete("categoria:$id");
Cache::getInstance()->invalidateCategories();
```

### 5. Patrón Remember (Helper)

Para operaciones costosas con caché automático:

```php
// En RecursoController::estadisticas()
$stats = Cache::getInstance()->remember('recursos:stats:global', 300, function() {
    return $this->recursoModel->getEstadisticas();
});
```

## Estructura de Claves

### Nomenclatura

Todas las claves siguen el patrón: `{PREFIX}:{NAMESPACE}:{IDENTIFIER}`

Ejemplos:
- `nyd:recursos:list:abc123def` - Lista de recursos con filtros
- `nyd:recurso:42` - Recurso ID 42
- `nyd:categoria:5` - Categoría ID 5
- `nyd:categorias:all:active` - Todas las categorías activas
- `nyd:recursos:stats:global` - Estadísticas globales

### Patrones de Invalidación

```php
// Invalidar todos los listados de recursos
Cache::getInstance()->deletePattern('nyd:recursos:list:*');

// Invalidar todas las listas de categorías
Cache::getInstance()->deletePattern('nyd:categorias:all:*');
```

## Métodos Cacheados

### Modelo Recurso

| Método | Clave | TTL | Invalidación |
|--------|-------|-----|--------------|
| `getAll()` | `recursos:list:{hash}` | 10m | En create/update/delete |
| `getById()` | `recurso:{id}` | 5m | En update/delete del recurso |
| `getEstadisticas()` | `recursos:stats:global` | 5m | En create/update/delete |

### Modelo CategoriaRecurso

| Método | Clave | TTL | Invalidación |
|--------|-------|-----|--------------|
| `getAll()` | `categorias:all:{type}` | 15m | En create/update/delete |
| `getById()` | `categoria:{id}` | 15m | En update/delete de categoría |

## Monitoreo y Estadísticas

### Obtener estadísticas de caché:

```php
$stats = Cache::getInstance()->getStats();

// Retorna:
[
    'hits' => 150,        // Aciertos de caché
    'misses' => 45,       // Fallos de caché
    'total' => 195,       // Total de peticiones
    'hit_rate' => 76.92   // Porcentaje de aciertos
]
```

### Verificar estado del servidor Redis:

```php
if (Cache::getInstance()->ping()) {
    echo "Redis está activo";
}
```

## Instalación de Redis

### Windows (Desarrollo)

1. **Opción 1: WSL2 (Recomendado)**
```bash
sudo apt update
sudo apt install redis-server
sudo service redis-server start
redis-cli ping  # Debe responder PONG
```

2. **Opción 2: Memurai (Redis para Windows)**
- Descargar de: https://www.memurai.com/
- Instalar y ejecutar como servicio
- Conectar en `127.0.0.1:6379`

### Linux (Producción)

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install redis-server
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Verificar
redis-cli ping
```

### Extensión PHP Redis

```bash
# Ubuntu/Debian
sudo apt install php-redis
sudo systemctl restart apache2

# Verificar
php -m | grep redis
```

## Pruebas

### Test básico de conexión:

```php
<?php
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';
require_once '../backend/utils/Cache.php';

try {
    $cache = Cache::getInstance();
    
    // Test ping
    if (!$cache->ping()) {
        die("Redis no está disponible\n");
    }
    
    // Test set/get
    $cache->set('test:key', ['data' => 'test'], 60);
    $value = $cache->get('test:key');
    
    echo "Cache funcionando correctamente\n";
    print_r($value);
    
    // Ver estadísticas
    print_r($cache->getStats());
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### Verificar caché en endpoints:

```bash
# Primera llamada (sin caché) - más lenta
curl http://localhost/nenis_y_bros/backend/api/v1/recursos

# Segunda llamada (con caché) - instantánea
curl http://localhost/nenis_y_bros/backend/api/v1/recursos
```

## Troubleshooting

### Redis no conecta

**Error**: `Connection refused`

**Solución**:
```bash
# Verificar que Redis esté corriendo
sudo systemctl status redis-server

# Iniciar si está detenido
sudo systemctl start redis-server

# Verificar puerto
sudo netstat -tulpn | grep 6379
```

### Extensión PHP no cargada

**Error**: `Class 'Redis' not found`

**Solución**:
```bash
# Instalar extensión
sudo apt install php-redis

# Reiniciar servidor web
sudo systemctl restart apache2

# Verificar
php -m | grep redis
```

### Caché no se invalida

**Síntoma**: Datos desactualizados después de updates

**Solución**:
1. Verificar que `CACHE_ENABLED` esté en `true`
2. Revisar logs de errores de PHP
3. Limpiar manualmente:
```bash
redis-cli FLUSHDB
```

### Performance no mejora

**Diagnóstico**:
```php
// Ver hit rate
$stats = Cache::getInstance()->getStats();
echo "Hit rate: " . $stats['hit_rate'] . "%\n";
```

Si el hit rate es bajo (<50%), revisar:
- TTLs muy cortos
- Invalidación muy agresiva
- Consultas con parámetros únicos (sin reuso)

## Best Practices

1. **TTL apropiado**: Balancear freshness vs performance
   - Datos estáticos: 15-30 minutos
   - Datos dinámicos: 5-10 minutos
   - Datos críticos en tiempo real: No cachear

2. **Invalidación selectiva**: Borrar solo lo necesario
   ```php
   // MAL: Invalidar todo
   Cache::getInstance()->deletePattern('nyd:*');
   
   // BIEN: Invalidar solo afectados
   Cache::getInstance()->delete("recurso:$id");
   Cache::getInstance()->invalidateResources();
   ```

3. **Monitoreo continuo**: Revisar hit rate regularmente
   - Objetivo: >70% hit rate
   - <50% indica problema de configuración

4. **Fallback graceful**: Siempre tener plan B si Redis falla
   ```php
   try {
       $cached = Cache::getInstance()->get($key);
   } catch (Exception $e) {
       // Continuar sin caché
       $cached = null;
   }
   ```

5. **Namespaces claros**: Usar prefijos consistentes para facilitar invalidación

## Próximas Mejoras (Fase 6B completa)

- [ ] Cache warming en deploy
- [ ] Invalidación por eventos (webhooks)
- [ ] Redis Sentinel para HA
- [ ] Métricas con Prometheus
- [ ] Cache de búsquedas con Elasticsearch

## Referencias

- [Redis Best Practices](https://redis.io/docs/manual/patterns/)
- [PHP Redis Extension](https://github.com/phpredis/phpredis)
- [Cache-Aside Pattern](https://docs.microsoft.com/en-us/azure/architecture/patterns/cache-aside)
