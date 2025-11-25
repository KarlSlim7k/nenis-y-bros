-- Actualizar contraseñas de usuarios de prueba
-- Contraseña: Password123!
-- Hash generado con: password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12])

USE formacion_empresarial;

UPDATE usuarios 
SET password_hash = '$2y$12$6oKnkQSN8J1vjzVDVj998eocaMm/OPMDZ6okwdhYbZsz.VGqhWyk.'
WHERE email IN (
    'admin@test.com',
    'instructor@test.com',
    'instructor.test@nyd.com',
    'emprendedor@test.com',
    'alumno.test@nyd.com',
    'frontend@test.com'
);

-- Verificar
SELECT id_usuario, nombre, email, tipo_usuario, LEFT(password_hash, 20) as hash_preview
FROM usuarios 
WHERE email IN (
    'admin@test.com',
    'instructor@test.com',
    'instructor.test@nyd.com',
    'emprendedor@test.com',
    'alumno.test@nyd.com',
    'frontend@test.com'
);
