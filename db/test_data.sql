-- ============================================================================
-- DATOS DE PRUEBA - FASE 1
-- ============================================================================
-- Script para insertar usuarios de prueba en el sistema
-- Contraseña para todos: "Password123!"
-- Hash generado con: password_hash('Password123!', PASSWORD_BCRYPT)
-- ============================================================================

USE formacion_empresarial;

-- Limpiar datos de prueba previos (opcional)
-- DELETE FROM usuarios WHERE email LIKE '%@test.com' OR email LIKE '%@nenisybros.com';

-- ============================================================================
-- INSERTAR USUARIOS DE PRUEBA
-- ============================================================================

-- 1. Usuario Administrador
INSERT INTO usuarios (
    nombre, apellido, email, telefono, password_hash, tipo_usuario, 
    foto_perfil, biografia, ciudad, pais, estado
) VALUES (
    'Admin',
    'Sistema',
    'admin@nenisybros.com',
    '5551234567',
    '$2y$12$LQv3c1yycz6dUW6V3Y8wYOqhTvTx8qN9qLvPtQT.pIGE8HlDYBQQe', -- Password123!
    'administrador',
    NULL,
    'Administrador del sistema Nenis y Bros',
    'Ciudad de México',
    'México',
    'activo'
);

-- 2. Usuario Mentor
INSERT INTO usuarios (
    nombre, apellido, email, telefono, password_hash, tipo_usuario, 
    foto_perfil, biografia, ciudad, pais, estado
) VALUES (
    'Carlos',
    'Mentor',
    'carlos.mentor@nenisybros.com',
    '5559876543',
    '$2y$12$LQv3c1yycz6dUW6V3Y8wYOqhTvTx8qN9qLvPtQT.pIGE8HlDYBQQe', -- Password123!
    'mentor',
    NULL,
    'Mentor con 10 años de experiencia en emprendimiento',
    'Guadalajara',
    'México',
    'activo'
);

-- 2b. Usuario Instructor (Mentor para pruebas de login)
INSERT INTO usuarios (
    nombre, apellido, email, telefono, password_hash, tipo_usuario, 
    foto_perfil, biografia, ciudad, pais, estado
) VALUES (
    'Instructor',
    'Demo',
    'instructor@nenisybros.com',
    '5551112222',
    '$2y$12$LQv3c1yycz6dUW6V3Y8wYOqhTvTx8qN9qLvPtQT.pIGE8HlDYBQQe', -- Password123!
    'mentor',
    NULL,
    'Instructor demo para pruebas del sistema',
    'Ciudad de México',
    'México',
    'activo'
);

-- 3. Usuario Empresario
INSERT INTO usuarios (
    nombre, apellido, email, telefono, password_hash, tipo_usuario, 
    foto_perfil, biografia, ciudad, pais, estado
) VALUES (
    'María',
    'Empresaria',
    'maria.empresaria@test.com',
    '5558765432',
    '$2y$12$LQv3c1yycz6dUW6V3Y8wYOqhTvTx8qN9qLvPtQT.pIGE8HlDYBQQe', -- Password123!
    'empresario',
    NULL,
    'CEO de una empresa de tecnología',
    'Monterrey',
    'México',
    'activo'
);

-- 4. Usuario Emprendedor 1
INSERT INTO usuarios (
    nombre, apellido, email, telefono, password_hash, tipo_usuario, 
    foto_perfil, biografia, ciudad, pais, estado
) VALUES (
    'Juan',
    'Pérez',
    'juan.perez@test.com',
    '5557654321',
    '$2y$12$LQv3c1yycz6dUW6V3Y8wYOqhTvTx8qN9qLvPtQT.pIGE8HlDYBQQe', -- Password123!
    'emprendedor',
    NULL,
    'Emprendedor apasionado por la innovación',
    'Puebla',
    'México',
    'activo'
);

-- 5. Usuario Emprendedor 2
INSERT INTO usuarios (
    nombre, apellido, email, telefono, password_hash, tipo_usuario, 
    foto_perfil, biografia, ciudad, pais, estado
) VALUES (
    'Ana',
    'García',
    'ana.garcia@test.com',
    '5556543210',
    '$2y$12$LQv3c1yycz6dUW6V3Y8wYOqhTvTx8qN9qLvPtQT.pIGE8HlDYBQQe', -- Password123!
    'emprendedor',
    NULL,
    'Emprendedora en el sector retail',
    'Querétaro',
    'México',
    'activo'
);

-- 6. Usuario Emprendedor 3
INSERT INTO usuarios (
    nombre, apellido, email, telefono, password_hash, tipo_usuario, 
    foto_perfil, biografia, ciudad, pais, estado
) VALUES (
    'Luis',
    'Martínez',
    'luis.martinez@test.com',
    '5555432109',
    '$2y$12$LQv3c1yycz6dUW6V3Y8wYOqhTvTx8qN9qLvPtQT.pIGE8HlDYBQQe', -- Password123!
    'emprendedor',
    NULL,
    'Desarrollador y emprendedor tech',
    'Ciudad de México',
    'México',
    'activo'
);

-- 7. Usuario Inactivo (para pruebas)
INSERT INTO usuarios (
    nombre, apellido, email, telefono, password_hash, tipo_usuario, 
    foto_perfil, biografia, ciudad, pais, estado
) VALUES (
    'Usuario',
    'Inactivo',
    'inactivo@test.com',
    '5554321098',
    '$2y$12$LQv3c1yycz6dUW6V3Y8wYOqhTvTx8qN9qLvPtQT.pIGE8HlDYBQQe', -- Password123!
    'emprendedor',
    NULL,
    NULL,
    NULL,
    NULL,
    'inactivo'
);

-- ============================================================================
-- VERIFICAR DATOS INSERTADOS
-- ============================================================================

SELECT 
    id_usuario,
    nombre,
    apellido,
    email,
    tipo_usuario,
    estado,
    fecha_registro
FROM usuarios
ORDER BY id_usuario DESC
LIMIT 10;

-- ============================================================================
-- INFORMACIÓN DE ACCESO
-- ============================================================================
-- 
-- Todos los usuarios tienen la contraseña: Password123!
--
-- Usuarios disponibles:
-- 1. admin@nenisybros.com (Administrador)
-- 2. carlos.mentor@nenisybros.com (Mentor)
-- 2b. instructor@nenisybros.com (Instructor/Mentor)
-- 3. maria.empresaria@test.com (Empresario)
-- 4. juan.perez@test.com (Emprendedor)
-- 5. ana.garcia@test.com (Emprendedor)
-- 6. luis.martinez@test.com (Emprendedor)
-- 7. inactivo@test.com (Inactivo - para pruebas de estado)
--
-- ============================================================================

