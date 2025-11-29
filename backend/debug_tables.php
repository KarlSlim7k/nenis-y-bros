<?php
/**
 * Script de diagnóstico para verificar tablas en la base de datos
 * TEMPORAL - Eliminar después de usar
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tables' => [],
    'errors' => []
];

try {
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/config/database.php';
    
    $db = Database::getInstance();
    
    // Listar todas las tablas
    $tables = $db->fetchAll("SHOW TABLES");
    $result['all_tables'] = $tables;
    
    // Verificar tablas específicas de cursos
    $tablesToCheck = [
        'cursos',
        'categorias_cursos', 
        'modulos',
        'modulos_curso',
        'lecciones',
        'inscripciones',
        'inscripciones_curso',
        'progreso_lecciones'
    ];
    
    foreach ($tablesToCheck as $table) {
        try {
            $count = $db->fetchOne("SELECT COUNT(*) as total FROM $table");
            $result['tables'][$table] = [
                'exists' => true,
                'count' => $count['total']
            ];
        } catch (Exception $e) {
            $result['tables'][$table] = [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    $result['success'] = true;
    
} catch (Exception $e) {
    $result['success'] = false;
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);
