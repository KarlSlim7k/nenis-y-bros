-- ============================================================================
-- MIGRACIÓN: ACTUALIZACIÓN DE CURSOS PARA ONBOARDING
-- ============================================================================

USE formacion_empresarial;

-- 1. Asignar niveles y recomendaciones a cursos existentes
-- Nota: Asumimos que existen cursos. Si no, insertamos algunos de ejemplo.

-- Limpiar flags anteriores
UPDATE cursos SET recomendado_onboarding = FALSE;

-- Configurar cursos para PRINCIPIANTES
UPDATE cursos 
SET nivel_curso = 'principiante', recomendado_onboarding = TRUE 
WHERE titulo LIKE '%básico%' OR titulo LIKE '%introducción%' OR titulo LIKE '%iniciar%' OR titulo LIKE '%principiante%';

-- Si no se actualizaron, intentar con IDs específicos o insertar
INSERT INTO cursos (titulo, descripcion, id_instructor, id_categoria, nivel, estado, precio, nivel_curso, recomendado_onboarding)
SELECT 'Fundamentos del Emprendimiento', 'Todo lo que necesitas saber para iniciar tu viaje emprendedor con bases sólidas.', 1, 1, 'básico', 'publicado', 0.00, 'principiante', TRUE
WHERE NOT EXISTS (SELECT 1 FROM cursos WHERE nivel_curso = 'principiante' AND recomendado_onboarding = TRUE);

INSERT INTO cursos (titulo, descripcion, id_instructor, id_categoria, nivel, estado, precio, nivel_curso, recomendado_onboarding)
SELECT 'Finanzas para No Financieros', 'Aprende a gestionar el dinero de tu negocio sin complicaciones.', 1, 3, 'básico', 'publicado', 0.00, 'principiante', TRUE
WHERE NOT EXISTS (SELECT 1 FROM cursos WHERE titulo = 'Finanzas para No Financieros');


-- Configurar cursos para INTERMEDIOS
UPDATE cursos 
SET nivel_curso = 'intermedio', recomendado_onboarding = TRUE 
WHERE titulo LIKE '%gestión%' OR titulo LIKE '%marketing%' OR titulo LIKE '%ventas%' OR titulo LIKE '%intermedio%';

INSERT INTO cursos (titulo, descripcion, id_instructor, id_categoria, nivel, estado, precio, nivel_curso, recomendado_onboarding)
SELECT 'Estrategias de Marketing Digital', 'Lleva tu negocio al siguiente nivel con estrategias digitales efectivas.', 1, 2, 'intermedio', 'publicado', 29.99, 'intermedio', TRUE
WHERE NOT EXISTS (SELECT 1 FROM cursos WHERE nivel_curso = 'intermedio' AND recomendado_onboarding = TRUE);


-- Configurar cursos para AVANZADOS
UPDATE cursos 
SET nivel_curso = 'avanzado', recomendado_onboarding = TRUE 
WHERE titulo LIKE '%estrategia%' OR titulo LIKE '%liderazgo%' OR titulo LIKE '%expansión%' OR titulo LIKE '%avanzado%';

INSERT INTO cursos (titulo, descripcion, id_instructor, id_categoria, nivel, estado, precio, nivel_curso, recomendado_onboarding)
SELECT 'Liderazgo y Gestión de Equipos', 'Domina el arte de liderar equipos de alto rendimiento.', 1, 4, 'avanzado', 'publicado', 49.99, 'avanzado', TRUE
WHERE NOT EXISTS (SELECT 1 FROM cursos WHERE nivel_curso = 'avanzado' AND recomendado_onboarding = TRUE);

-- Verificación
SELECT id_curso, titulo, nivel_curso, recomendado_onboarding FROM cursos WHERE recomendado_onboarding = TRUE;
