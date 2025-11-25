<?php
// Actualizar password en DB
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';

$db = Database::getInstance();
$email = 'prueba@test.com';
$password = 'password';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Actualizando usuario: $email\n";
echo "Password: $password\n";
echo "Hash: $hash\n\n";

$query = "UPDATE usuarios SET password_hash = ? WHERE email = ?";
$result = $db->query($query, [$hash, $email]);

if ($result) {
    echo "✅ Password actualizado correctamente\n\n";
    
    // Verificar
    $user = $db->fetchOne("SELECT email, password_hash FROM usuarios WHERE email = ?", [$email]);
    echo "Verificación:\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Hash en DB: " . $user['password_hash'] . "\n";
    echo "Password verify: " . (password_verify($password, $user['password_hash']) ? '✅ SUCCESS' : '❌ FAILED') . "\n";
} else {
    echo "❌ Error al actualizar\n";
}
