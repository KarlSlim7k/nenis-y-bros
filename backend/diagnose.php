<?php
/**
 * Script de diagnóstico simple para Railway
 * Acceder via: /diagnose.php
 */

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'checks' => []
];

// 1. Verificar variables de entorno
$envVars = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'API_PREFIX', 'API_VERSION'];
foreach ($envVars as $var) {
    $value = getenv($var);
    $results['checks']['env_' . $var] = [
        'status' => $value !== false ? 'ok' : 'missing',
        'value' => $var === 'DB_PASSWORD' ? '***' : ($value !== false ? substr($value, 0, 30) : 'NOT SET')
    ];
}

// 2. Verificar extensiones PHP
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    $results['checks']['ext_' . $ext] = [
        'status' => extension_loaded($ext) ? 'ok' : 'missing'
    ];
}

// 3. Intentar conexión a base de datos
try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_DATABASE') ?: 'test';
    $username = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    
    $results['checks']['database_connection'] = ['status' => 'ok'];
    
    // 4. Verificar tablas críticas
    $tables = ['usuarios', 'cursos', 'preguntas_cuestionario_inicial'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $results['checks']['table_' . $table] = [
                'status' => 'ok',
                'row_count' => $count
            ];
        } catch (PDOException $e) {
            $results['checks']['table_' . $table] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
} catch (PDOException $e) {
    $results['checks']['database_connection'] = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// 5. Verificar archivos críticos
$criticalFiles = [
    'config/config.php',
    'config/database.php',
    'controllers/OnboardingController.php',
    'models/CuestionarioInicial.php'
];

foreach ($criticalFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    $results['checks']['file_' . str_replace('/', '_', $file)] = [
        'status' => file_exists($fullPath) ? 'ok' : 'missing'
    ];
}

// Output
echo json_encode($results, JSON_PRETTY_PRINT);
