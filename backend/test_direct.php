<?php
/**
 * Archivo de prueba directo - Sin routing
 */

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => true,
    'message' => '✅ El backend está funcionando correctamente',
    'data' => [
        'php_version' => phpversion(),
        'directory' => __DIR__,
        'timestamp' => date('Y-m-d H:i:s'),
        'rewrite_enabled' => function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules()) ? 'Sí' : 'Desconocido'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
