-- ============================================================================
-- DATOS DE PRUEBA PARA FASE 2A - SISTEMA DE CURSOS
-- ============================================================================
-- Inserta cursos, módulos y lecciones de ejemplo para testing
-- ============================================================================

USE formacion_empresarial;

-- ============================================================================
-- CREAR USUARIOS DE PRUEBA SI NO EXISTEN
-- ============================================================================

-- Crear un mentor/instructor si no existe
INSERT IGNORE INTO usuarios (nombre, apellido, email, password_hash, tipo_usuario, estado, biografia)
VALUES 
('Juan', 'Pérez', 'instructor@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mentor', 'activo', 'Instructor con 10 años de experiencia en emprendimiento.'),
('María', 'González', 'emprendedor@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'emprendedor', 'activo', 'Emprendedora apasionada por aprender.');

-- Obtener ID de un usuario mentor para ser instructor
SET @instructor_id = (SELECT id_usuario FROM usuarios WHERE tipo_usuario = 'mentor' LIMIT 1);

-- Si no hay mentor, usar admin
SET @instructor_id = IFNULL(@instructor_id, (SELECT id_usuario FROM usuarios WHERE tipo_usuario = 'administrador' LIMIT 1));

-- ============================================================================
-- CURSOS DE EJEMPLO
-- ============================================================================

INSERT INTO cursos (id_categoria, id_instructor, titulo, slug, descripcion, descripcion_corta, nivel, duracion_estimada, precio, estado, fecha_publicacion) VALUES
(1, @instructor_id, 'Fundamentos de Emprendimiento Digital', 'fundamentos-emprendimiento-digital', 
'Aprende los conceptos básicos para iniciar tu propio negocio digital. Este curso cubre desde la generación de ideas hasta la validación de mercado.', 
'Inicia tu negocio digital desde cero con este curso práctico y completo.', 
'principiante', 480, 0.00, 'publicado', NOW()),

(1, @instructor_id, 'Plan de Negocios Avanzado', 'plan-negocios-avanzado',
'Desarrolla un plan de negocios profesional y completo. Incluye análisis financiero, estrategias de marketing y proyecciones.', 
'Crea un plan de negocios que atraiga inversores y guíe tu emprendimiento.', 
'intermedio', 600, 0.00, 'publicado', NOW()),

(2, @instructor_id, 'Contabilidad Básica para Emprendedores', 'contabilidad-basica-emprendedores',
'Domina los conceptos esenciales de contabilidad para llevar las finanzas de tu negocio de forma eficiente.', 
'Aprende a gestionar las finanzas de tu empresa sin ser contador.', 
'principiante', 420, 0.00, 'publicado', NOW()),

(3, @instructor_id, 'Marketing en Redes Sociales', 'marketing-redes-sociales',
'Estrategias efectivas para promocionar tu negocio en Facebook, Instagram, LinkedIn y TikTok.', 
'Domina las redes sociales y atrae clientes a tu negocio.', 
'principiante', 360, 0.00, 'publicado', NOW()),

(4, @instructor_id, 'Liderazgo y Gestión de Equipos', 'liderazgo-gestion-equipos',
'Desarrolla habilidades de liderazgo efectivo para inspirar y motivar a tu equipo de trabajo.', 
'Conviértete en el líder que tu equipo necesita.', 
'intermedio', 540, 0.00, 'publicado', NOW());

-- ============================================================================
-- MÓDULOS Y LECCIONES PARA: Fundamentos de Emprendimiento Digital
-- ============================================================================

SET @curso_id = (SELECT id_curso FROM cursos WHERE slug = 'fundamentos-emprendimiento-digital');

-- Módulo 1
INSERT INTO modulos (id_curso, titulo, descripcion, orden) VALUES
(@curso_id, 'Introducción al Emprendimiento', 'Conoce los conceptos básicos y la mentalidad emprendedora.', 1);

SET @modulo_id = LAST_INSERT_ID();

INSERT INTO lecciones (id_modulo, titulo, contenido, tipo_contenido, orden, duracion_minutos) VALUES
(@modulo_id, 'Bienvenida al curso', 
'# Bienvenida\n\nEn este curso aprenderás todo lo necesario para iniciar tu negocio digital. ¡Comencemos!', 
'texto', 1, 5),

(@modulo_id, '¿Qué es el emprendimiento?', 
'# Definición de Emprendimiento\n\nEl emprendimiento es la capacidad de identificar oportunidades y crear soluciones innovadoras...', 
'texto', 2, 15),

(@modulo_id, 'Mentalidad emprendedora', 
'# Mindset del Emprendedor\n\n- Resiliencia\n- Visión a largo plazo\n- Aprendizaje continuo\n- Toma de riesgos calculados', 
'texto', 3, 20);

-- Módulo 2
INSERT INTO modulos (id_curso, titulo, descripcion, orden) VALUES
(@curso_id, 'Generación de Ideas de Negocio', 'Aprende técnicas para identificar oportunidades y generar ideas viables.', 2);

SET @modulo_id = LAST_INSERT_ID();

INSERT INTO lecciones (id_modulo, titulo, contenido, tipo_contenido, orden, duracion_minutos) VALUES
(@modulo_id, 'Identificación de problemas', 
'# Encuentra Problemas que Resolver\n\nLos mejores negocios nacen de solucionar problemas reales...', 
'texto', 1, 25),

(@modulo_id, 'Análisis de mercado', 
'# ¿Existe demanda para tu idea?\n\nAntes de invertir tiempo y dinero, valida que exista un mercado...', 
'texto', 2, 30),

(@modulo_id, 'Validación de tu idea', 
'# Técnicas de Validación\n\n- Encuestas\n- Entrevistas\n- MVP (Producto Mínimo Viable)\n- Landing page de prueba', 
'texto', 3, 35);

-- Módulo 3
INSERT INTO modulos (id_curso, titulo, descripcion, orden) VALUES
(@curso_id, 'Modelo de Negocio', 'Diseña el modelo de negocio para tu emprendimiento.', 3);

SET @modulo_id = LAST_INSERT_ID();

INSERT INTO lecciones (id_modulo, titulo, contenido, tipo_contenido, orden, duracion_minutos) VALUES
(@modulo_id, 'Canvas de modelo de negocio', 
'# Business Model Canvas\n\nUna herramienta visual para diseñar y validar tu modelo de negocio...', 
'texto', 1, 40),

(@modulo_id, 'Propuesta de valor', 
'# Tu Propuesta Única de Valor\n\n¿Qué te hace diferente de la competencia?', 
'texto', 2, 30),

(@modulo_id, 'Canales de distribución', 
'# Cómo Llegar a tus Clientes\n\n- Venta directa\n- E-commerce\n- Marketplaces\n- Distribuidores', 
'texto', 3, 25);

-- ============================================================================
-- MÓDULOS Y LECCIONES PARA: Marketing en Redes Sociales
-- ============================================================================

SET @curso_id = (SELECT id_curso FROM cursos WHERE slug = 'marketing-redes-sociales');

-- Módulo 1
INSERT INTO modulos (id_curso, titulo, descripcion, orden) VALUES
(@curso_id, 'Fundamentos de Marketing Digital', 'Conceptos básicos que debes dominar.', 1);

SET @modulo_id = LAST_INSERT_ID();

INSERT INTO lecciones (id_modulo, titulo, contenido, tipo_contenido, orden, duracion_minutos) VALUES
(@modulo_id, 'Introducción al marketing digital', 
'# Marketing Digital en el Siglo XXI\n\nEl marketing ha evolucionado...', 
'texto', 1, 15),

(@modulo_id, 'Público objetivo y buyer persona', 
'# Conoce a tu Cliente Ideal\n\nDefinir tu buyer persona es fundamental...', 
'texto', 2, 25),

(@modulo_id, 'Estrategia de contenidos', 
'# Planifica tu Contenido\n\n- Calendario editorial\n- Tipos de contenido\n- Frecuencia de publicación', 
'texto', 3, 30);

-- Módulo 2
INSERT INTO modulos (id_curso, titulo, descripcion, orden) VALUES
(@curso_id, 'Instagram para Negocios', 'Domina Instagram y atrae clientes.', 2);

SET @modulo_id = LAST_INSERT_ID();

INSERT INTO lecciones (id_modulo, titulo, contenido, tipo_contenido, orden, duracion_minutos) VALUES
(@modulo_id, 'Optimización de perfil', 
'# Perfil Profesional en Instagram\n\n- Foto de perfil\n- Bio atractiva\n- Link en bio\n- Highlights', 
'texto', 1, 20),

(@modulo_id, 'Creación de contenido visual', 
'# Diseño para Instagram\n\nHerramientas: Canva, Adobe Spark, Unfold...', 
'texto', 2, 35),

(@modulo_id, 'Stories e Instagram Reels', 
'# Contenido Efímero y Videos Cortos\n\nLos Reels son el formato del momento...', 
'texto', 3, 40);

-- ============================================================================
-- INSCRIPCIONES DE PRUEBA
-- ============================================================================

-- Inscribir al primer emprendedor disponible en algunos cursos
SET @estudiante_id = (SELECT id_usuario FROM usuarios WHERE tipo_usuario = 'emprendedor' LIMIT 1);

INSERT INTO inscripciones (id_usuario, id_curso, fecha_ultima_actividad) 
SELECT @estudiante_id, id_curso, NOW() 
FROM cursos 
WHERE slug IN ('fundamentos-emprendimiento-digital', 'marketing-redes-sociales')
LIMIT 2;

-- ============================================================================
-- PROGRESO DE PRUEBA
-- ============================================================================

-- Marcar algunas lecciones como completadas para el estudiante
SET @inscripcion_id = (SELECT id_inscripcion FROM inscripciones WHERE id_usuario = @estudiante_id LIMIT 1);

INSERT INTO progreso_lecciones (id_inscripcion, id_leccion, completada, tiempo_dedicado, fecha_completado)
SELECT @inscripcion_id, id_leccion, TRUE, duracion_minutos, NOW()
FROM lecciones l
INNER JOIN modulos m ON l.id_modulo = m.id_modulo
WHERE m.id_curso = (SELECT id_curso FROM inscripciones WHERE id_inscripcion = @inscripcion_id)
AND l.orden <= 2
LIMIT 3;

-- Actualizar progreso en la inscripción
UPDATE inscripciones SET 
    porcentaje_avance = (
        SELECT ROUND((COUNT(CASE WHEN pl.completada = TRUE THEN 1 END) / COUNT(*)) * 100, 2)
        FROM lecciones l
        INNER JOIN modulos m ON l.id_modulo = m.id_modulo
        LEFT JOIN progreso_lecciones pl ON l.id_leccion = pl.id_leccion AND pl.id_inscripcion = @inscripcion_id
        WHERE m.id_curso = inscripciones.id_curso
    ),
    lecciones_completadas = (
        SELECT COUNT(*)
        FROM progreso_lecciones pl
        INNER JOIN lecciones l ON pl.id_leccion = l.id_leccion
        INNER JOIN modulos m ON l.id_modulo = m.id_modulo
        WHERE pl.id_inscripcion = @inscripcion_id AND m.id_curso = inscripciones.id_curso AND pl.completada = TRUE
    ),
    fecha_inicio = NOW(),
    fecha_ultima_actividad = NOW()
WHERE id_inscripcion = @inscripcion_id;

-- ============================================================================
-- VERIFICACIÓN
-- ============================================================================

SELECT 'Datos de prueba insertados exitosamente' AS resultado;
SELECT COUNT(*) AS total_cursos FROM cursos;
SELECT COUNT(*) AS total_modulos FROM modulos;
SELECT COUNT(*) AS total_lecciones FROM lecciones;
SELECT COUNT(*) AS total_inscripciones FROM inscripciones;
SELECT COUNT(*) AS progreso_registrado FROM progreso_lecciones;
