<?php
/**
 * Archivo de debug para verificar configuración en Railway
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server_vars' => [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'NOT SET',
        'PATH_INFO' => $_SERVER['PATH_INFO'] ?? 'NOT SET',
        'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET',
        'PHP_SELF' => $_SERVER['PHP_SELF'] ?? 'NOT SET',
    ],
    'env_vars' => [],
    'db_test' => null,
    'errors' => []
];

// Verificar variables de entorno
$envVars = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'APP_ENV', 'APP_DEBUG'];
foreach ($envVars as $var) {
    $value = getenv($var);
    if ($var === 'DB_USERNAME' || $var === 'DB_HOST') {
        $debug['env_vars'][$var] = $value ? substr($value, 0, 5) . '...' : 'NOT SET';
    } else {
        $debug['env_vars'][$var] = $value ?: 'NOT SET';
    }
}

// Verificar si DB_PASSWORD está configurado
$debug['env_vars']['DB_PASSWORD'] = getenv('DB_PASSWORD') ? 'SET (hidden)' : 'NOT SET';

// Intentar conexión a base de datos
try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_DATABASE') ?: 'formacion_empresarial';
    $username = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    
    $debug['db_connection_string'] = "mysql:host={$host};port={$port};dbname={$database}";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $debug['db_test'] = 'SUCCESS';
    
    // Verificar tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $debug['tables_count'] = count($tables);
    $debug['tables'] = array_slice($tables, 0, 5); // Mostrar solo 5 primeras
    
} catch (PDOException $e) {
    $debug['db_test'] = 'FAILED';
    $debug['db_error'] = $e->getMessage();
}

// Verificar archivos críticos
$criticalFiles = [
    'config/config.php',
    'config/database.php',
    'routes/Router.php',
    'routes/api.php',
    'index.php',
    '.htaccess'
];

$debug['files'] = [];
foreach ($criticalFiles as $file) {
    $debug['files'][$file] = file_exists(__DIR__ . '/' . $file) ? 'EXISTS' : 'MISSING';
}

// Verificar contenido de .htaccess
if (file_exists(__DIR__ . '/.htaccess')) {
    $debug['htaccess_content'] = file_get_contents(__DIR__ . '/.htaccess');
}

echo json_encode($debug, JSON_PRETTY_PRINT);
