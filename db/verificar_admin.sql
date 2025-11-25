-- ============================================================================
-- SCRIPT: Verificación y creación de usuario administrador
-- ============================================================================
-- Descripción: Verifica que exista al menos un usuario administrador
--              y crea uno si no existe
-- Contraseña por defecto: Password123!
-- ============================================================================

USE formacion_empresarial;

-- ============================================================================
-- 1. VERIFICAR SI EXISTE UN ADMINISTRADOR
-- ============================================================================

SELECT 
    'USUARIOS ADMINISTRADORES EXISTENTES:' as mensaje,
    '' as separador;

SELECT 
    id_usuario,
    nombre,
    apellido,
    email,
    tipo_usuario,
    estado,
    fecha_registro
FROM usuarios
WHERE tipo_usuario = 'administrador';

-- ============================================================================
-- 2. CREAR ADMINISTRADOR SI NO EXISTE
-- ============================================================================

-- Insertar admin@test.com si no existe
INSERT INTO usuarios (
    nombre, 
    apellido, 
    email, 
    password_hash, 
    tipo_usuario, 
    estado,
    telefono,
    ciudad,
    pais
)
SELECT 
    'Admin',
    'Sistema',
    'admin@test.com',
    '$2y$12$LQv3c1yycz6dUW6V3Y8wYOqhTvTx8qN9qLvPtQT.pIGE8HlDYBQQe', -- Password123!
    'administrador',
    'activo',
    '5551234567',
    'Ciudad de México',
    'México'
WHERE NOT EXISTS (
    SELECT 1 FROM usuarios WHERE email = 'admin@test.com'
);

-- ============================================================================
-- 3. VERIFICAR RESULTADO
-- ============================================================================

SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN CONCAT('✓ Se encontraron ', COUNT(*), ' administrador(es)')
        ELSE '✗ NO HAY ADMINISTRADORES EN EL SISTEMA'
    END as resultado
FROM usuarios
WHERE tipo_usuario = 'administrador';

-- ============================================================================
-- 4. MOSTRAR INFORMACIÓN DE ACCESO
-- ============================================================================

SELECT 
    '===========================================' as separador,
    'CREDENCIALES DE ADMINISTRADOR' as titulo,
    '===========================================' as separador2;

SELECT 
    email as 'Email',
    'Password123!' as 'Contraseña',
    tipo_usuario as 'Tipo',
    estado as 'Estado',
    fecha_registro as 'Fecha Registro'
FROM usuarios
WHERE tipo_usuario = 'administrador'
ORDER BY fecha_registro DESC
LIMIT 5;

-- ============================================================================
-- 5. ACTUALIZAR CONTRASEÑA DE admin@test.com (OPCIONAL)
-- ============================================================================
-- Descomenta las siguientes líneas si necesitas resetear la contraseña

-- UPDATE usuarios 
-- SET password_hash = '$2y$12$LQv3c1yycz6dUW6V3Y8wYOqhTvTx8qN9qLvPtQT.pIGE8HlDYBQQe'
-- WHERE email = 'admin@test.com';

-- SELECT 'Contraseña actualizada para admin@test.com' as resultado;

-- ============================================================================
-- 6. PROMOVER UN USUARIO EXISTENTE A ADMINISTRADOR (OPCIONAL)
-- ============================================================================
-- Descomenta y modifica el email para promover un usuario existente

-- UPDATE usuarios 
-- SET tipo_usuario = 'administrador'
-- WHERE email = 'tu_email@example.com';

-- SELECT CONCAT('Usuario ', email, ' promovido a administrador') as resultado
-- FROM usuarios 
-- WHERE email = 'tu_email@example.com';

-- ============================================================================
-- NOTAS IMPORTANTES:
-- ============================================================================
-- 1. La contraseña por defecto es: Password123!
-- 2. El hash es generado con PASSWORD_BCRYPT en PHP
-- 3. Cambia la contraseña después del primer acceso
-- 4. Asegúrate de que el estado sea 'activo'
-- ============================================================================
