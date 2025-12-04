-- ============================================================================
-- AGREGAR USUARIO INSTRUCTOR
-- ============================================================================
-- Agrega el usuario instructor@nenisybros.com para pruebas de login de mentor
-- Contraseña: Password123!
-- ============================================================================

USE formacion_empresarial;

-- Insertar usuario instructor (mentor)
INSERT INTO usuarios (
    nombre, apellido, email, telefono, password_hash, tipo_usuario, 
    foto_perfil, biografia, ciudad, pais, estado
) VALUES (
    'Instructor',
    'Demo',
    'instructor@nenisybros.com',
    '5551112222',
    '$2y$10$3N2VSsK2Dpospd2pQEi9aOvLUcLud1supqTE1/vRBgiW1ZRrh9NpG', -- Password123!
    'mentor',
    NULL,
    'Instructor demo para pruebas del sistema',
    'Ciudad de México',
    'México',
    'activo'
) ON DUPLICATE KEY UPDATE 
    password_hash = '$2y$10$3N2VSsK2Dpospd2pQEi9aOvLUcLud1supqTE1/vRBgiW1ZRrh9NpG',
    estado = 'activo';

-- Verificar inserción
SELECT id_usuario, nombre, email, tipo_usuario, estado 
FROM usuarios 
WHERE email = 'instructor@nenisybros.com';
