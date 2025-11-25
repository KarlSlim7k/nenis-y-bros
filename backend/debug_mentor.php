<?php
/**
 * Debug script to test mentor login credentials
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$email = 'instructor@test.com';
$password = 'Password123!';

try {
    $db = Database::getInstance();
    
    // Get user from database
    $user = $db->fetchOne(
        "SELECT id_usuario, nombre, email, password_hash, tipo_usuario, estado FROM usuarios WHERE email = ?",
        [$email]
    );
    
    if (!$user) {
        echo "❌ Usuario no encontrado con email: $email\n";
        exit;
    }
    
    echo "✅ Usuario encontrado:\n";
    echo "  ID: {$user['id_usuario']}\n";
    echo "  Nombre: {$user['nombre']}\n";
    echo "  Email: {$user['email']}\n";
    echo "  Tipo: {$user['tipo_usuario']}\n";
    echo "  Estado: {$user['estado']}\n";
    echo "  Hash: {$user['password_hash']}\n\n";
    
    // Test password verification
    echo "🔐 Probando password_verify()...\n";
    $isValid = password_verify($password, $user['password_hash']);
    
    if ($isValid) {
        echo "✅ ¡Contraseña CORRECTA! password_verify() retornó TRUE\n";
    } else {
        echo "❌ Contraseña INCORRECTA. password_verify() retornó FALSE\n";
        
        // Try to generate a new hash for comparison
        echo "\n🔧 Generando nuevo hash para comparación...\n";
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        echo "  Nuevo hash: $newHash\n";
        
        // Test with new hash
        $testNew = password_verify($password, $newHash);
        echo "  Test con nuevo hash: " . ($testNew ? "✅ FUNCIONA" : "❌ FALLA") . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
