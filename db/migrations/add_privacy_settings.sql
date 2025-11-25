-- ============================================================================
-- MIGRACIÓN: Agregar configuración de privacidad a usuarios
-- ============================================================================
-- Fecha: 15 de Noviembre 2025
-- Fase: 1 - Fundamentos y Autenticación
-- Descripción: Añade columna JSON para gestionar configuración de privacidad
-- ============================================================================

USE formacion_empresarial;

-- Agregar columna de configuración de privacidad
ALTER TABLE usuarios 
ADD COLUMN configuracion_privacidad JSON 
DEFAULT '{"perfil_publico": true, "mostrar_email": false, "mostrar_telefono": false, "mostrar_biografia": true, "mostrar_ubicacion": true, "permitir_mensajes": true}' 
COMMENT 'Configuración de privacidad del usuario'
AFTER pais;

-- Actualizar usuarios existentes con configuración por defecto
UPDATE usuarios 
SET configuracion_privacidad = JSON_OBJECT(
    'perfil_publico', true,
    'mostrar_email', false,
    'mostrar_telefono', false,
    'mostrar_biografia', true,
    'mostrar_ubicacion', true,
    'permitir_mensajes', true
)
WHERE configuracion_privacidad IS NULL;

-- Verificación
SELECT 
    id_usuario, 
    email, 
    configuracion_privacidad 
FROM usuarios 
LIMIT 5;

-- ============================================================================
-- ROLLBACK (en caso de necesitar revertir)
-- ============================================================================
-- ALTER TABLE usuarios DROP COLUMN configuracion_privacidad;
