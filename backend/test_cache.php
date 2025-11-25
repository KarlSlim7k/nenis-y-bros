<?php
/**
 * Test de Redis Cache
 * 
 * Script para verificar la instalación y funcionamiento del sistema de caché
 */

// Cargar dependencias
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/Cache.php';

echo "=== TEST DE REDIS CACHE ===\n\n";

// Test 1: Verificar que Redis está disponible
echo "1. Verificando conexión a Redis...\n";
try {
    $cache = Cache::getInstance();
    
    if (!CACHE_ENABLED) {
        echo "   ⚠️  CACHE_ENABLED está en false\n";
        echo "   Para activar el caché, cambia CACHE_ENABLED a true en config.php\n\n";
    }
    
    if ($cache->ping()) {
        echo "   ✅ Redis está activo y responde\n";
        echo "   Host: " . REDIS_HOST . ":" . REDIS_PORT . "\n";
        echo "   Prefix: " . CACHE_PREFIX . "\n\n";
    } else {
        echo "   ❌ Redis no responde al ping\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
    echo "SOLUCIÓN:\n";
    echo "- Instalar Redis: sudo apt install redis-server\n";
    echo "- Instalar PHP Redis: sudo apt install php-redis\n";
    echo "- Iniciar Redis: sudo systemctl start redis-server\n\n";
    exit(1);
}

// Test 2: Operaciones básicas
echo "2. Test de operaciones básicas...\n";
try {
    // SET
    $testData = [
        'message' => 'Hello from cache',
        'timestamp' => time(),
        'data' => ['foo' => 'bar', 'numbers' => [1, 2, 3]]
    ];
    
    $cache->set('test:basic', $testData, 60);
    echo "   ✅ SET funcionando\n";
    
    // GET
    $retrieved = $cache->get('test:basic');
    if ($retrieved && $retrieved['message'] === 'Hello from cache') {
        echo "   ✅ GET funcionando\n";
    } else {
        echo "   ❌ GET falló - datos no coinciden\n";
    }
    
    // EXISTS
    $exists = $cache->get('test:basic') !== null;
    if ($exists) {
        echo "   ✅ EXISTS funcionando\n";
    } else {
        echo "   ❌ EXISTS falló\n";
    }
    
    // DELETE
    $cache->delete('test:basic');
    $deleted = $cache->get('test:basic') === null;
    if ($deleted) {
        echo "   ✅ DELETE funcionando\n";
    } else {
        echo "   ❌ DELETE falló\n";
    }
    
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Error en operaciones básicas: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 3: Patrón Cache-Aside (remember)
echo "3. Test de patrón remember (cache-aside)...\n";
try {
    $callCount = 0;
    
    // Primera llamada - ejecuta callback
    $result1 = $cache->remember('test:remember', 60, function() use (&$callCount) {
        $callCount++;
        return ['computed' => true, 'value' => 42];
    });
    
    // Segunda llamada - usa caché
    $result2 = $cache->remember('test:remember', 60, function() use (&$callCount) {
        $callCount++;
        return ['computed' => true, 'value' => 42];
    });
    
    if ($callCount === 1) {
        echo "   ✅ Remember funciona - callback ejecutado 1 vez\n";
        echo "   ✅ Segunda llamada usó caché\n";
    } else {
        echo "   ❌ Remember falló - callback ejecutado {$callCount} veces\n";
    }
    
    $cache->delete('test:remember');
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Error en remember: " . $e->getMessage() . "\n\n";
}

// Test 4: Patrones de invalidación
echo "4. Test de invalidación por patrón...\n";
try {
    // Crear múltiples claves
    $cache->set('recursos:list:a', ['data' => 1], 60);
    $cache->set('recursos:list:b', ['data' => 2], 60);
    $cache->set('recursos:list:c', ['data' => 3], 60);
    $cache->set('categoria:1', ['id' => 1], 60);
    
    // Invalidar solo recursos
    $cache->invalidateResources();
    
    $listA = $cache->get('recursos:list:a');
    $cat1 = $cache->get('categoria:1');
    
    if ($listA === null && $cat1 !== null) {
        echo "   ✅ Invalidación selectiva funciona\n";
        echo "   ✅ Recursos borrados, categoría intacta\n";
    } else {
        echo "   ❌ Invalidación selectiva falló\n";
    }
    
    $cache->delete('categoria:1');
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Error en invalidación: " . $e->getMessage() . "\n\n";
}

// Test 5: TTL (Time To Live)
echo "5. Test de TTL...\n";
try {
    $cache->set('test:ttl', ['expire' => 'soon'], 2); // 2 segundos
    
    $immediate = $cache->get('test:ttl');
    if ($immediate) {
        echo "   ✅ Clave existe inmediatamente\n";
    }
    
    echo "   ⏳ Esperando 3 segundos...\n";
    sleep(3);
    
    $expired = $cache->get('test:ttl');
    if ($expired === null) {
        echo "   ✅ Clave expiró correctamente\n";
    } else {
        echo "   ❌ Clave no expiró\n";
    }
    
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Error en TTL: " . $e->getMessage() . "\n\n";
}

// Test 6: Estadísticas
echo "6. Test de estadísticas...\n";
try {
    // Resetear stats
    $cache->increment('nyd:cache:hits', -$cache->get('nyd:cache:hits') ?: 0);
    $cache->increment('nyd:cache:misses', -$cache->get('nyd:cache:misses') ?: 0);
    
    // Generar hits y misses
    $cache->set('test:stats', ['data' => 123], 60);
    
    $cache->get('test:stats'); // HIT
    $cache->get('test:stats'); // HIT
    $cache->get('test:nonexistent'); // MISS
    
    $stats = $cache->getStats();
    
    echo "   Hits: " . $stats['hits'] . "\n";
    echo "   Misses: " . $stats['misses'] . "\n";
    echo "   Total: " . $stats['total'] . "\n";
    echo "   Hit Rate: " . number_format($stats['hit_rate'], 2) . "%\n";
    
    if ($stats['hits'] > 0 && $stats['total'] > 0) {
        echo "   ✅ Estadísticas funcionando\n";
    } else {
        echo "   ⚠️  Estadísticas pueden no ser precisas\n";
    }
    
    $cache->delete('test:stats');
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Error en estadísticas: " . $e->getMessage() . "\n\n";
}

// Test 7: Integración con modelos (si existen datos)
echo "7. Test de integración con modelos...\n";
try {
    require_once __DIR__ . '/models/CategoriaRecurso.php';
    $categoriaModel = new CategoriaRecurso();
    
    // Primera llamada (sin caché)
    $start1 = microtime(true);
    $categorias1 = $categoriaModel->getAll();
    $time1 = (microtime(true) - $start1) * 1000;
    
    // Segunda llamada (con caché)
    $start2 = microtime(true);
    $categorias2 = $categoriaModel->getAll();
    $time2 = (microtime(true) - $start2) * 1000;
    
    echo "   Primera llamada (DB): " . number_format($time1, 2) . " ms\n";
    echo "   Segunda llamada (Cache): " . number_format($time2, 2) . " ms\n";
    
    if ($time2 < $time1) {
        $speedup = round($time1 / $time2, 2);
        echo "   ✅ Caché es {$speedup}x más rápido\n";
    } else {
        echo "   ⚠️  Caché no mostró mejora (puede ser normal si DB es muy rápida)\n";
    }
    
    // Limpiar caché de prueba
    $cache->invalidateCategories();
    
} catch (Exception $e) {
    echo "   ⚠️  No se pudo probar integración: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE TESTS ===\n\n";

if (CACHE_ENABLED) {
    echo "✅ CACHE ACTIVADO - El sistema está usando Redis\n";
} else {
    echo "⚠️  CACHE DESACTIVADO - Para activar:\n";
    echo "   1. Asegúrate de que Redis está instalado y corriendo\n";
    echo "   2. Cambia CACHE_ENABLED a true en config.php o .env\n";
}

echo "\nPara monitorear Redis en tiempo real:\n";
echo "redis-cli MONITOR\n\n";
