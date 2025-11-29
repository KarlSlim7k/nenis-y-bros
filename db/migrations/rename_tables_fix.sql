-- ============================================================================
-- MIGRACIÓN: Renombrar tablas para consistencia con código PHP
-- ============================================================================
-- Este script renombra las tablas antiguas a los nombres que usa el código PHP
-- 
-- EJECUTAR EN: Base de datos de producción (Railway MySQL)
-- FECHA: 2025-11-29
-- ============================================================================

-- Verificar antes de ejecutar
SELECT 'Verificando tablas existentes...' AS step;

-- Renombrar modulos_curso -> modulos
RENAME TABLE modulos_curso TO modulos;
SELECT 'Tabla modulos_curso renombrada a modulos' AS result;

-- Renombrar inscripciones_curso -> inscripciones
RENAME TABLE inscripciones_curso TO inscripciones;
SELECT 'Tabla inscripciones_curso renombrada a inscripciones' AS result;

-- Verificar resultado
SELECT 'Verificación final - tablas actuales:' AS step;
SHOW TABLES LIKE 'modulos';
SHOW TABLES LIKE 'inscripciones';

SELECT 'Migración completada exitosamente!' AS final_result;
