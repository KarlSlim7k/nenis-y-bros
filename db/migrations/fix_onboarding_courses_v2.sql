-- ============================================================================
-- MIGRACIÓN: ACTUALIZACIÓN DE CURSOS PARA ONBOARDING (FIXED)
-- ============================================================================

USE formacion_empresarial;

-- 1. Asignar niveles y recomendaciones a cursos existentes
UPDATE cursos SET recomendado_onboarding = FALSE;

UPDATE cursos 
SET nivel_curso = 'principiante', recomendado_onboarding = TRUE 
WHERE titulo LIKE '%básico%' OR titulo LIKE '%introducción%' OR titulo LIKE '%iniciar%' OR titulo LIKE '%principiante%';

-- Insertar cursos de ejemplo si no existen (con SLUGS)
INSERT INTO cursos (titulo, slug, descripcion, id_instructor, id_categoria, nivel, estado, precio, nivel_curso, recomendado_onboarding)
SELECT 'Fundamentos del Emprendimiento', 'fundamentos-emprendimiento-onboarding', 'Todo lo que necesitas saber para iniciar tu viaje emprendedor con bases sólidas.', 1, 1, 'básico', 'publicado', 0.00, 'principiante', TRUE
WHERE NOT EXISTS (SELECT 1 FROM cursos WHERE slug = 'fundamentos-emprendimiento-onboarding');

INSERT INTO cursos (titulo, slug, descripcion, id_instructor, id_categoria, nivel, estado, precio, nivel_curso, recomendado_onboarding)
SELECT 'Finanzas para No Financieros', 'finanzas-no-financieros-onboarding', 'Aprende a gestionar el dinero de tu negocio sin complicaciones.', 1, 3, 'básico', 'publicado', 0.00, 'principiante', TRUE
WHERE NOT EXISTS (SELECT 1 FROM cursos WHERE slug = 'finanzas-no-financieros-onboarding');

-- Intermedio
UPDATE cursos 
SET nivel_curso = 'intermedio', recomendado_onboarding = TRUE 
WHERE titulo LIKE '%gestión%' OR titulo LIKE '%marketing%' OR titulo LIKE '%ventas%' OR titulo LIKE '%intermedio%';

INSERT INTO cursos (titulo, slug, descripcion, id_instructor, id_categoria, nivel, estado, precio, nivel_curso, recomendado_onboarding)
SELECT 'Estrategias de Marketing Digital', 'marketing-digital-onboarding', 'Lleva tu negocio al siguiente nivel con estrategias digitales efectivas.', 1, 2, 'intermedio', 'publicado', 29.99, 'intermedio', TRUE
WHERE NOT EXISTS (SELECT 1 FROM cursos WHERE slug = 'marketing-digital-onboarding');

-- Avanzado
UPDATE cursos 
SET nivel_curso = 'avanzado', recomendado_onboarding = TRUE 
WHERE titulo LIKE '%estrategia%' OR titulo LIKE '%liderazgo%' OR titulo LIKE '%expansión%' OR titulo LIKE '%avanzado%';

INSERT INTO cursos (titulo, slug, descripcion, id_instructor, id_categoria, nivel, estado, precio, nivel_curso, recomendado_onboarding)
SELECT 'Liderazgo y Gestión de Equipos', 'liderazgo-equipos-onboarding', 'Domina el arte de liderar equipos de alto rendimiento.', 1, 4, 'avanzado', 'publicado', 49.99, 'avanzado', TRUE
WHERE NOT EXISTS (SELECT 1 FROM cursos WHERE slug = 'liderazgo-equipos-onboarding');

SELECT id_curso, titulo, slug, nivel_curso, recomendado_onboarding FROM cursos WHERE recomendado_onboarding = TRUE;
