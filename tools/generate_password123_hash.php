<?php
// Generar hash para Password123!
$password = 'Password123!';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

echo "Password: $password\n";
echo "Hash: $hash\n";
echo "\nVerify test: " . (password_verify($password, $hash) ? 'SUCCESS' : 'FAILED') . "\n";

// Generar UPDATE SQL
echo "\n\n-- SQL para actualizar usuarios:\n";
echo "UPDATE usuarios SET password_hash = '$hash' WHERE email = 'admin@test.com';\n";
echo "UPDATE usuarios SET password_hash = '$hash' WHERE email = 'instructor@test.com';\n";
echo "UPDATE usuarios SET password_hash = '$hash' WHERE email = 'instructor.test@nyd.com';\n";
echo "UPDATE usuarios SET password_hash = '$hash' WHERE email = 'emprendedor@test.com';\n";
echo "UPDATE usuarios SET password_hash = '$hash' WHERE email = 'alumno.test@nyd.com';\n";
echo "UPDATE usuarios SET password_hash = '$hash' WHERE email = 'frontend@test.com';\n";
