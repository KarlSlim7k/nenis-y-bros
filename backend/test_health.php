<?php
/**
 * Test directo de la ruta health
 */

// Mostrar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$result = [
    'test' => 'health_direct',
    'timestamp' => date('Y-m-d H:i:s'),
    'steps' => []
];

try {
    // Step 1: Cargar config
    $result['steps'][] = 'Loading config...';
    require_once __DIR__ . '/config/config.php';
    $result['steps'][] = 'Config loaded - APP_NAME: ' . (defined('APP_NAME') ? APP_NAME : 'NOT DEFINED');
    
    // Step 2: Cargar Response
    $result['steps'][] = 'Loading Response...';
    require_once __DIR__ . '/utils/Response.php';
    $result['steps'][] = 'Response loaded';
    
    // Step 3: Cargar Database
    $result['steps'][] = 'Loading Database...';
    require_once __DIR__ . '/config/database.php';
    $result['steps'][] = 'Database loaded';
    
    // Step 4: Probar conexión DB
    $result['steps'][] = 'Testing DB connection...';
    $db = Database::getInstance();
    $result['steps'][] = 'DB connection successful';
    
    // Step 5: Verificar constantes
    $result['constants'] = [
        'APP_NAME' => defined('APP_NAME') ? APP_NAME : 'NOT DEFINED',
        'APP_ENV' => defined('APP_ENV') ? APP_ENV : 'NOT DEFINED',
        'API_PREFIX' => defined('API_PREFIX') ? API_PREFIX : 'NOT DEFINED',
        'API_VERSION' => defined('API_VERSION') ? API_VERSION : 'NOT DEFINED',
        'DB_HOST' => defined('DB_HOST') ? substr(DB_HOST, 0, 10) . '...' : 'NOT DEFINED',
        'DB_DATABASE' => defined('DB_DATABASE') ? DB_DATABASE : 'NOT DEFINED',
    ];
    
    $result['success'] = true;
    $result['message'] = 'All tests passed!';
    
} catch (Exception $e) {
    $result['success'] = false;
    $result['error'] = $e->getMessage();
    $result['error_file'] = $e->getFile();
    $result['error_line'] = $e->getLine();
    $result['trace'] = $e->getTraceAsString();
} catch (Error $e) {
    $result['success'] = false;
    $result['error'] = $e->getMessage();
    $result['error_file'] = $e->getFile();
    $result['error_line'] = $e->getLine();
    $result['trace'] = $e->getTraceAsString();
}

echo json_encode($result, JSON_PRETTY_PRINT);
