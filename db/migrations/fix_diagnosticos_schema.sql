-- ============================================================================
-- MIGRACIÓN: Ajustar tablas de diagnósticos al esquema del controlador
-- ============================================================================
-- Esta migración adapta el esquema existente para que funcione con el 
-- controlador DiagnosticoController que espera el modelo relacional
-- tipos_diagnostico -> areas_evaluacion -> preguntas_diagnostico
-- ============================================================================

-- 1. Verificar si la columna id_area ya existe en preguntas_diagnostico
-- Si no existe, necesitamos agregarla y migrar datos

-- Primero, asegurarnos de que areas_evaluacion existe y tiene datos
SELECT 'Verificando areas_evaluacion...' as paso;
SELECT COUNT(*) as total_areas FROM areas_evaluacion;

-- 2. Agregar columna id_area a preguntas_diagnostico si no existe
SELECT 'Agregando columna id_area si no existe...' as paso;

SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'formacion_empresarial' 
    AND TABLE_NAME = 'preguntas_diagnostico' 
    AND COLUMN_NAME = 'id_area'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE preguntas_diagnostico ADD COLUMN id_area INT NULL AFTER id_pregunta',
    'SELECT "Columna id_area ya existe" as mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Agregar índice y clave foránea si no existen
SELECT 'Agregando índice y FK...' as paso;

SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'formacion_empresarial' 
    AND TABLE_NAME = 'preguntas_diagnostico' 
    AND INDEX_NAME = 'idx_id_area'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE preguntas_diagnostico ADD INDEX idx_id_area (id_area)',
    'SELECT "Índice ya existe" as mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Agregar constraint si no existe
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'formacion_empresarial' 
    AND TABLE_NAME = 'preguntas_diagnostico' 
    AND CONSTRAINT_NAME = 'fk_preguntas_area'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE preguntas_diagnostico ADD CONSTRAINT fk_preguntas_area FOREIGN KEY (id_area) REFERENCES areas_evaluacion(id_area) ON DELETE CASCADE',
    'SELECT "FK ya existe" as mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Si hay preguntas sin id_area, intentar asignarlas basándose en id_diagnostico
SELECT 'Asignando áreas a preguntas existentes...' as paso;

UPDATE preguntas_diagnostico p
INNER JOIN areas_evaluacion a ON a.id_tipo_diagnostico = p.id_diagnostico
SET p.id_area = a.id_area
WHERE p.id_area IS NULL
AND a.orden = 1;  -- Asignar a la primera área por defecto

-- 6. Insertar preguntas de ejemplo si la tabla está vacía
INSERT IGNORE INTO preguntas_diagnostico 
(id_pregunta, id_area, id_diagnostico, pregunta, tipo_pregunta, orden, obligatoria)
SELECT 
    1, 1, 1, '¿Tu empresa cuenta con un plan estratégico definido?', 'escala', 1, 1
FROM areas_evaluacion WHERE id_area = 1
LIMIT 1;

-- 7. Verificar resultado final
SELECT 'Verificación final' as paso;
SELECT 
    'tipos_diagnostico' as tabla, 
    COUNT(*) as registros 
FROM tipos_diagnostico

UNION ALL

SELECT 
    'areas_evaluacion' as tabla, 
    COUNT(*) as registros 
FROM areas_evaluacion

UNION ALL

SELECT 
    'preguntas_diagnostico' as tabla, 
    COUNT(*) as registros 
FROM preguntas_diagnostico

UNION ALL

SELECT 
    'diagnosticos_realizados' as tabla, 
    COUNT(*) as registros 
FROM diagnosticos_realizados;

SELECT '✅ Migración completada' as resultado;
